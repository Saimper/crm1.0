<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use App\Modules\Cobranza\Domain\Exceptions\DatosCasoCobranzaInvalidos;
use App\Modules\Cobranza\Domain\ValueObjects\DiasMora;
use App\Modules\Contactos\Domain\Contracts\AltaContactosEnLote;
use App\Modules\Contactos\Domain\ValueObjects\ExtractorDeContactos;
use App\Modules\Cx\Application\DTOs\RegistrarCasoTicketCxInput;
use App\Modules\Cx\Application\UseCases\RegistrarCasoTicketCx;
use App\Modules\Importaciones\Application\Services\DescriptorDeFalloImportacion;
use App\Modules\Importaciones\Application\Services\ResolverPersonaImportacion;
use App\Modules\Importaciones\Domain\Enums\EstadoFila;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\RolContacto;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\Exceptions\FilaNoImportable;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionNoProcesable;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ResultadoFila;
use App\Modules\Personas\Application\DTOs\RegistrarPersonaInput;
use App\Modules\Personas\Application\UseCases\RegistrarPersona;
use App\Modules\Personas\Domain\ValueObjects\Identificacion;
use App\Modules\Personas\Domain\ValueObjects\TipoPersona;
use App\Modules\Servicio\Application\DTOs\RegistrarCasoServicioInput;
use App\Modules\Servicio\Application\UseCases\RegistrarCasoServicio;
use App\Modules\Tenancy\Application\Services\RelojDelMandante;
use App\Modules\Venta\Application\DTOs\RegistrarCasoLeadVentaInput;
use App\Modules\Venta\Application\UseCases\RegistrarCasoLeadVenta;
use App\Support\Database\CarterasOperativas;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Procesa una sola fila de importación dinámica según el esquema configurado.
 *
 * NO persiste valores de campos personalizados directamente; los retorna
 * para que el job los acumule y llame a guardarValoresEnLote() en batch.
 *
 * No es `readonly` porque memoriza el mandante de cada proyecto: hace falta
 * para anclar la mora al calendario del cliente y no se va a consultar por
 * cada una de las 8.000 filas de un archivo.
 */
final class ProcesarFilaDinamica
{
    /** @var array<string, string> Mapeo target value → tabla CTI */
    private const TABLAS_CTI = [
        'caso_cobranza' => 'casos_cobranza',
        'caso_ticket_cx' => 'casos_ticket_cx',
        'caso_lead_venta' => 'casos_lead_venta',
        'caso_servicio' => 'casos_servicio',
    ];

    /** @var array<string, string> Columna unique por target value */
    private const COLUMNAS_UNIQUE = [
        'caso_cobranza' => 'numero_prestamo',
        'caso_ticket_cx' => 'codigo_ticket',
        'caso_lead_venta' => 'codigo_lead',
        'caso_servicio' => 'codigo_servicio',
    ];

    /** @var array<int, int|null> proyecto_id → mandante_id */
    private array $mandantePorProyecto = [];

    public function __construct(
        private readonly ResolverPersonaImportacion $personaResolver,
        private readonly RegistrarPersona $registrarPersona,
        private readonly RegistrarCasoCobranza $registrarCobranza,
        private readonly RegistrarCasoTicketCx $registrarCx,
        private readonly RegistrarCasoLeadVenta $registrarVenta,
        private readonly RegistrarCasoServicio $registrarServicio,
        private readonly ConnectionInterface $db,
        private readonly AltaContactosEnLote $altaContactos,
        private readonly DescriptorDeFalloImportacion $descriptor,
        private readonly RelojDelMandante $reloj,
    ) {}

    public function execute(ProcesarFilaInput $input): ResultadoFilaConValoresCp
    {
        $esquema = $input->esquema;
        $fila = $input->fila;

        $columnaIdentidad = $esquema->columnaIdentificador();
        $identKey = $columnaIdentidad?->clavePayload();
        $valorIdentidad = $identKey !== null ? ($fila[$identKey] ?? '') : '';

        $proyectoId = $esquema->proyectoId;
        $carteraId = $esquema->carteraId;
        if ($esquema->target !== TargetImportacion::PERSONA && $carteraId !== null
            && ! CarterasOperativas::carteraDisponible($this->db, $proyectoId, $carteraId)) {
            throw new ImportacionNoProcesable('La cartera no está activa en este proyecto.');
        }

        $tiposIdentificacion = $input->tiposIdentificacion;

        $tipoIdentId = $this->resolverTipoIdentificacion($fila, $esquema, $tiposIdentificacion);

        $personaId = null;
        $personaExistente = false;

        if ($valorIdentidad !== '' && $tipoIdentId !== null) {
            $personaKey = $tipoIdentId.':'.$valorIdentidad;
            if (isset($input->personasExistentes[$personaKey])) {
                $personaId = $input->personasExistentes[$personaKey];
                $personaExistente = true;
            } else {
                $personaId = $this->personaResolver->lookup($proyectoId, $tipoIdentId, $valorIdentidad);
                $personaExistente = $personaId !== null;
            }
        }

        $casoId = null;
        $casoExistente = false;

        if ($esquema->target !== TargetImportacion::PERSONA && $carteraId !== null) {
            $casoKey = $fila['id_cpelegido'] ?? '';
            if ($casoKey !== '' && isset($input->casosExistentes[$casoKey])) {
                $casoId = $input->casosExistentes[$casoKey];
                $casoExistente = true;
            } else {
                $casoId = $this->buscarCasoExistente($esquema->target, $proyectoId, $fila);
                $casoExistente = $casoId !== null;
            }
        }

        if ($casoId !== null && ! CarterasOperativas::casos($this->db, $proyectoId)->where('c.id', $casoId)->exists()) {
            throw new ImportacionNoProcesable('La cuenta pertenece a una cartera desactivada o eliminada.');
        }

        $resultado = match ($esquema->modo) {
            ModoImportacion::INSERT => $this->procesarInsert(
                $input, $personaId, $personaExistente, $casoId, $casoExistente,
            ),
            ModoImportacion::UPDATE => $this->procesarUpdate(
                $input, $fila, $esquema, $proyectoId, $casoId, $casoExistente,
            ),
            ModoImportacion::UPSERT, ModoImportacion::OVERWRITE => $this->procesarUpsert(
                $input, $fila, $esquema, $proyectoId, $carteraId,
                $personaId, $personaExistente, $casoId, $casoExistente,
                $tipoIdentId,
            ),
            ModoImportacion::MERGE => $this->procesarMerge(
                $input, $fila, $esquema, $proyectoId, $carteraId,
                $personaId, $personaExistente, $casoId, $casoExistente,
                $tipoIdentId,
            ),
            ModoImportacion::SKIP_DUPLICADOS => $this->procesarSkipDuplicados(
                $casoExistente, $personaExistente, $casoId, $personaId,
            ),
        };

        // Los contactos van después del modo, no dentro: la persona puede
        // haberla creado el propio modo, y los contactos cuelgan de ella y no
        // del caso. Es best-effort a propósito — que un teléfono mal escrito no
        // tumbe la fila entera de una importación de 8.000. Y sólo si la fila
        // ENTRÓ: una duplicada de `skip_duplicados` o una omitida prometen no
        // tocar nada, y colgarle teléfonos a la persona ya era tocarla.
        if ($resultado->resultadoFila->estado === EstadoFila::PROCESADA) {
            $this->generarContactos($input, $proyectoId, $personaId, $tipoIdentId, $valorIdentidad);
        }

        return $resultado;
    }

    /**
     * Da de alta como contactos de la persona lo que traigan las columnas
     * marcadas con un rol.
     *
     * Partir `"61750650   65976897"`, descartar `000000`, quitar el prefijo 507
     * y decidir qué es un móvil panameño es regla de negocio, así que vive en un
     * value object del dominio de Contactos (§13.4) y aquí sólo se orquesta. El
     * alta va por un contrato y no por el modelo Eloquent de aquel módulo (§3).
     */
    private function generarContactos(
        ProcesarFilaInput $input,
        int $proyectoId,
        ?int $personaId,
        ?int $tipoIdentId,
        string $valorIdentidad,
    ): void {
        $columnas = array_values(array_filter(
            $input->esquema->columnas,
            static fn (ColumnaExcel $c): bool => $c->generaContactos(),
        ));

        if ($columnas === []) {
            return;
        }

        // La persona puede haberla creado el modo que acaba de correr.
        if ($personaId === null && $tipoIdentId !== null && $valorIdentidad !== '') {
            $personaId = $this->personaResolver->lookup($proyectoId, $tipoIdentId, $valorIdentidad);
        }

        if ($personaId === null) {
            return;
        }

        $extractor = new ExtractorDeContactos;
        $contactos = [];

        foreach ($columnas as $columna) {
            $bruto = trim((string) ($input->fila[$columna->clavePayload()] ?? ''));

            if ($bruto === '') {
                continue;
            }

            $contactos = array_merge($contactos, match ($columna->rolContacto) {
                RolContacto::TELEFONO => $extractor->telefonos($bruto, $columna->etiquetaSugerida()),
                RolContacto::CORREO => $extractor->correos($bruto),
                RolContacto::REFERENCIA => $extractor->referencias($bruto),
                RolContacto::NINGUNO => [],
            });
        }

        if ($contactos === []) {
            return;
        }

        try {
            $this->altaContactos->alta($proyectoId, $personaId, $contactos, 'importacion');
        } catch (Throwable $e) {
            Log::warning('importacion: no se pudieron dar de alta los contactos de una fila', [
                'proyecto_id' => $proyectoId,
                'persona_id' => $personaId,
                'error' => $this->descriptor->motivoDeFila($e),
            ]);
        }
    }

    /**
     * @param  array<string, string>  $fila
     * @param  array<string, int>  $tiposIdentificacion
     */
    private function resolverTipoIdentificacion(
        array $fila,
        EsquemaImportacion $esquema,
        array $tiposIdentificacion,
    ): ?int {
        $columnasSistema = $esquema->columnasParaSistema();

        if (isset($columnasSistema['tipo_identificacion_codigo'])) {
            $col = $columnasSistema['tipo_identificacion_codigo'];
            $codigo = strtoupper(trim($fila[$col->campoSistemaMapeado] ?? ''));
            if ($codigo !== '' && isset($tiposIdentificacion[$codigo])) {
                return (int) $tiposIdentificacion[$codigo];
            }
        }

        $primerTipo = reset($tiposIdentificacion);

        return $primerTipo !== false ? (int) $primerTipo : null;
    }

    /**
     * @param  array<string, string>  $fila
     */
    private function buscarCasoExistente(
        TargetImportacion $target,
        int $proyectoId,
        array $fila,
    ): ?int {
        $tabla = self::TABLAS_CTI[$target->value] ?? null;
        $columnaUnique = self::COLUMNAS_UNIQUE[$target->value] ?? null;

        if ($tabla === null || $columnaUnique === null) {
            return null;
        }

        $valorUnique = $fila['id_cpelegido'] ?? '';

        if ($valorUnique === '') {
            return null;
        }

        $id = $this->db->table($tabla)
            ->where('proyecto_id', $proyectoId)
            ->where($columnaUnique, $valorUnique)
            ->value('caso_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  array<string, string>  $fila
     */
    private function extraerValorCampoSistema(
        string $codigoCampo,
        array $fila,
        EsquemaImportacion $esquema,
    ): ?string {
        $columnasSistema = $esquema->columnasParaSistema();
        $columna = $columnasSistema[$codigoCampo] ?? null;

        if ($columna === null) {
            return null;
        }

        return $fila[$columna->campoSistemaMapeado] ?? null;
    }

    private function procesarInsert(
        ProcesarFilaInput $input,
        ?int $personaId,
        bool $personaExistente,
        ?int $casoId,
        bool $casoExistente,
    ): ResultadoFilaConValoresCp {
        if ($casoExistente) {
            return new ResultadoFilaConValoresCp(
                ResultadoFila::duplicada('El caso ya existe en el proyecto', $casoId),
                [],
                fueInsert: false,
            );
        }

        if ($personaExistente) {
            $valoresCp = $this->acumularValoresCp($input, $personaId);

            return new ResultadoFilaConValoresCp(
                ResultadoFila::duplicada('La persona ya existe, pero el caso no se creará en modo INSERT', $personaId),
                $valoresCp,
                fueInsert: false,
            );
        }

        return new ResultadoFilaConValoresCp(
            ResultadoFila::invalida('Modo INSERT requiere que la persona ya exista para asociar el caso'),
            [],
            fueInsert: false,
        );
    }

    /**
     * @param  array<string, string>  $fila
     */
    private function procesarUpdate(
        ProcesarFilaInput $input,
        array $fila,
        EsquemaImportacion $esquema,
        int $proyectoId,
        ?int $casoId,
        bool $casoExistente,
    ): ResultadoFilaConValoresCp {
        if (! $casoExistente || $casoId === null) {
            return new ResultadoFilaConValoresCp(
                ResultadoFila::omitida('El caso no existe en el proyecto'),
                [],
                fueInsert: false,
            );
        }

        return $this->actualizarCaso($input, $fila, $esquema, $proyectoId, $casoId, mergeOnly: false);
    }

    /**
     * Upsert y overwrite hacen lo mismo aquí: actualizar lo que existe y crear
     * lo que no. `OVERWRITE` sigue en el enum como deprecado para que las
     * importaciones antiguas que lo tienen guardado sigan pudiendo leerse.
     *
     * @param  array<string, string>  $fila
     */
    private function procesarUpsert(
        ProcesarFilaInput $input,
        array $fila,
        EsquemaImportacion $esquema,
        int $proyectoId,
        ?int $carteraId,
        ?int $personaId,
        bool $personaExistente,
        ?int $casoId,
        bool $casoExistente,
        ?int $tipoIdentId,
    ): ResultadoFilaConValoresCp {
        if ($casoExistente && $casoId !== null) {
            return $this->actualizarCaso($input, $fila, $esquema, $proyectoId, $casoId, mergeOnly: false);
        }

        return $this->crearCaso($input, $fila, $esquema, $proyectoId, $carteraId, $personaId, $personaExistente, $tipoIdentId);
    }

    /**
     * @param  array<string, string>  $fila
     */
    private function procesarMerge(
        ProcesarFilaInput $input,
        array $fila,
        EsquemaImportacion $esquema,
        int $proyectoId,
        ?int $carteraId,
        ?int $personaId,
        bool $personaExistente,
        ?int $casoId,
        bool $casoExistente,
        ?int $tipoIdentId,
    ): ResultadoFilaConValoresCp {
        if ($casoExistente && $casoId !== null) {
            return $this->actualizarCaso($input, $fila, $esquema, $proyectoId, $casoId, mergeOnly: true);
        }

        return $this->crearCaso($input, $fila, $esquema, $proyectoId, $carteraId, $personaId, $personaExistente, $tipoIdentId);
    }

    private function procesarSkipDuplicados(
        bool $casoExistente,
        bool $personaExistente,
        ?int $casoId,
        ?int $personaId,
    ): ResultadoFilaConValoresCp {
        if ($casoExistente) {
            return new ResultadoFilaConValoresCp(
                ResultadoFila::duplicada('El caso ya existe en el proyecto', $casoId),
                [],
                fueInsert: false,
            );
        }

        if ($personaExistente) {
            return new ResultadoFilaConValoresCp(
                ResultadoFila::duplicada('La persona ya existe en el proyecto', $personaId),
                [],
                fueInsert: false,
            );
        }

        return new ResultadoFilaConValoresCp(
            ResultadoFila::invalida('Modo skip_duplicados: no se crean registros nuevos'),
            [],
            fueInsert: false,
        );
    }

    /**
     * El caso existe: se escribe lo que el archivo trae y, si la fila no pasa
     * la validación, queda inválida SIN que se haya escrito nada ni se hayan
     * acumulado valores de campos personalizados para ella.
     *
     * @param  array<string, string>  $fila
     */
    private function actualizarCaso(
        ProcesarFilaInput $input,
        array $fila,
        EsquemaImportacion $esquema,
        int $proyectoId,
        int $casoId,
        bool $mergeOnly,
    ): ResultadoFilaConValoresCp {
        $motivo = $this->actualizarCasoExistente($esquema->target, $casoId, $fila, $esquema, $proyectoId, $mergeOnly);

        if ($motivo !== null) {
            return new ResultadoFilaConValoresCp(ResultadoFila::invalida($motivo), [], fueInsert: false);
        }

        return new ResultadoFilaConValoresCp(
            ResultadoFila::procesada($casoId),
            $this->acumularValoresCp($input, $casoId),
            fueInsert: false,
        );
    }

    /**
     * El caso no existe: se crea, y antes la persona si tampoco existe.
     *
     * @param  array<string, string>  $fila
     */
    private function crearCaso(
        ProcesarFilaInput $input,
        array $fila,
        EsquemaImportacion $esquema,
        int $proyectoId,
        ?int $carteraId,
        ?int $personaId,
        bool $personaExistente,
        ?int $tipoIdentId,
    ): ResultadoFilaConValoresCp {
        if ($carteraId === null) {
            return new ResultadoFilaConValoresCp(
                ResultadoFila::invalida('Se requiere carteraId para crear un caso'),
                [],
            );
        }

        try {
            if (! $personaExistente || $personaId === null) {
                $personaId = $this->crearOCrearPersona($proyectoId, $tipoIdentId, $fila, $esquema);
            }

            $estadoCasoId = $this->obtenerEstadoCasoDefault($proyectoId);

            $casoId = $this->registrarCaso(
                $esquema->target,
                $proyectoId,
                $carteraId,
                $personaId,
                $estadoCasoId,
                new DateTimeImmutable,
                $fila,
                $esquema,
            );

            return new ResultadoFilaConValoresCp(
                ResultadoFila::procesada($casoId),
                $this->acumularValoresCp($input, $casoId),
                fueInsert: true,
            );
        } catch (Throwable $e) {
            // Lo que se guarda en `mensaje_error` se pinta en pantalla y se
            // descarga en el CSV de rechazadas: el mensaje de la base de datos
            // trae la fila entera y no puede ir ahí.
            return new ResultadoFilaConValoresCp(
                ResultadoFila::invalida($this->descriptor->motivoDeFila($e)),
                [],
                fueInsert: false,
            );
        }
    }

    /**
     * Escribe en la tabla CTI lo que el archivo trae para un caso existente.
     *
     * Devuelve el motivo por el que la fila no puede aplicarse —y entonces no
     * se escribe nada— o null si se escribió.
     *
     * @param  array<string, string>  $fila
     */
    private function actualizarCasoExistente(
        TargetImportacion $target,
        int $casoId,
        array $fila,
        EsquemaImportacion $esquema,
        int $proyectoId,
        bool $mergeOnly = false,
    ): ?string {
        $tabla = self::TABLAS_CTI[$target->value] ?? null;
        if ($tabla === null) {
            return null;
        }

        $columnasSistema = $esquema->columnasParaSistema();
        $camposMutables = $this->camposMutablesPorTarget($target);

        // Una lectura por fila, no una por campo: con diez campos mutables en
        // cobranza eran diez consultas por cada fila en modo «completar vacíos».
        $actual = $mergeOnly
            ? $this->db->table($tabla)->where('caso_id', $casoId)->first()
            : null;

        $update = [];

        foreach ($camposMutables as $campo) {
            $columna = $columnasSistema[$campo] ?? null;
            if ($columna === null) {
                continue;
            }

            $valor = trim($fila[$columna->campoSistemaMapeado] ?? '');
            if ($valor === '') {
                continue;
            }

            if ($mergeOnly && ! $this->estaVacio($campo, $actual === null ? null : ($actual->{$campo} ?? null))) {
                continue;
            }

            $update[$campo] = $valor;
        }

        if ($target === TargetImportacion::CASO_COBRANZA && array_key_exists('dias_mora', $update)) {
            $motivo = $this->anclarDiasMora($update, $fila, $esquema, $proyectoId);
            if ($motivo !== null) {
                return $motivo;
            }
        }

        if ($update !== []) {
            $update['actualizada_en'] = CarbonImmutable::now();
            $this->db->table($tabla)->where('caso_id', $casoId)->update($update);
        }

        return null;
    }

    /**
     * Qué cuenta como «vacío» para el modo completar vacíos.
     *
     * Para `dias_mora` sólo NULL: un 0 es una cuenta al día, un valor que el
     * cliente afirmó, y pisarlo con lo que traiga el siguiente archivo sería
     * justo lo que este modo promete no hacer. El resto de campos conserva la
     * regla de siempre (nulo, vacío o cero).
     */
    private function estaVacio(string $campo, mixed $valorActual): bool
    {
        if ($campo === 'dias_mora') {
            return $valorActual === null;
        }

        return $valorActual === null || (string) $valorActual === '' || (float) $valorActual === 0.0;
    }

    /**
     * Valida los días de mora con el value object de Cobranza y fija la fecha
     * a la que corresponde el valor.
     *
     * `dias_mora_actualizado_en` y `dias_mora_confirmado_en` son la «fecha en
     * que la fuente afirmó el valor», en el calendario del mandante: el archivo
     * del cliente dice que HOY la cuenta lleva N días. El envejecimiento
     * nocturno (`cobranza:avanzar-dias-mora`) suma a partir de esa fecha, así
     * que si no se escribiera aquí, la mora de una cuenta recién importada
     * seguiría contando desde la carga anterior.
     *
     * Se importa el VO de otro módulo, no su modelo Eloquent (§3, §13.6). Y se
     * valida ANTES de escribir: un 99999 en el archivo es una fecha mal leída,
     * y la fila queda inválida con el mensaje del VO en vez de entrar a la base.
     *
     * Un negativo o un texto NO invalidan la fila: son días por vencer o una
     * celda rota, y el alta (`enteroSinSigno`) ya los deja pasar sin escribir
     * nada. Aquí se hace lo mismo —se quita la columna y sigue el resto— para
     * que el mismo archivo no entre en INSERT y se rechace en UPDATE.
     *
     * @param  array<string, mixed>  $update
     * @param  array<string, string>  $fila
     */
    private function anclarDiasMora(array &$update, array $fila, EsquemaImportacion $esquema, int $proyectoId): ?string
    {
        $dias = $this->enteroSinSigno('dias_mora', $fila, $esquema);
        if ($dias === null) {
            unset($update['dias_mora']);

            return null;
        }

        try {
            $diasMora = new DiasMora($dias);
        } catch (DatosCasoCobranzaInvalidos $e) {
            return $e->getMessage();
        }

        $hoy = $this->reloj->hoy($this->mandanteDe($proyectoId));

        $update['dias_mora'] = $diasMora->dias;
        $update['dias_mora_actualizado_en'] = $hoy;
        $update['dias_mora_confirmado_en'] = $hoy;

        return null;
    }

    private function mandanteDe(int $proyectoId): ?int
    {
        if (! array_key_exists($proyectoId, $this->mandantePorProyecto)) {
            $mandanteId = $this->db->table('proyectos')->where('id', $proyectoId)->value('mandante_id');
            $this->mandantePorProyecto[$proyectoId] = $mandanteId === null ? null : (int) $mandanteId;
        }

        return $this->mandantePorProyecto[$proyectoId];
    }

    /**
     * @return list<string>
     */
    private function camposMutablesPorTarget(TargetImportacion $target): array
    {
        return match ($target) {
            TargetImportacion::CASO_COBRANZA => [
                'monto_original', 'saldo_capital', 'saldo_interes', 'saldo_total',
                'cuota_mensual', 'cuotas_totales', 'cuotas_pagadas', 'dias_mora',
                'fecha_desembolso', 'fecha_vencimiento',
            ],
            TargetImportacion::CASO_TICKET_CX => [
                'asunto', 'descripcion', 'fecha_reporte', 'fecha_limite_sla',
            ],
            TargetImportacion::CASO_LEAD_VENTA => [
                'valor_estimado_monto', 'origen_lead', 'fecha_primer_contacto', 'fecha_estimada_cierre',
            ],
            TargetImportacion::CASO_SERVICIO => [
                'direccion_servicio', 'tecnico_asignado', 'fecha_solicitud', 'fecha_programada',
            ],
            default => [],
        };
    }

    private function obtenerEstadoCasoDefault(int $proyectoId): int
    {
        $estadoId = $this->db->table('estados_caso')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->orderBy('orden')
            ->value('id');

        if ($estadoId === null) {
            throw ImportacionNoProcesable::sinEstadosDeCaso($proyectoId);
        }

        return (int) $estadoId;
    }

    /**
     * @param  array<string, string>  $fila
     */
    private function crearOCrearPersona(
        int $proyectoId,
        ?int $tipoIdentId,
        array $fila,
        EsquemaImportacion $esquema,
    ): int {
        $identificacionValor = $this->extraerValorCampoSistema('identificacion', $fila, $esquema) ?? '';
        if ($identificacionValor === '') {
            $columnaIdentidad = $esquema->columnaIdentificador();
            if ($columnaIdentidad !== null) {
                $identificacionValor = $fila[$columnaIdentidad->clavePayload()] ?? '';
            }
        }
        if ($identificacionValor === '') {
            throw FilaNoImportable::sinIdentificacion();
        }

        $nombres = $this->extraerValorCampoSistema('nombres', $fila, $esquema)
            ?? $this->buscarEnCp($fila, $esquema, [
                'nombres', 'nombre', 'nombre_del_cliente', 'nombre_del_titular',
                'nombre_titular', 'titular', 'nombre_completo', 'nombre_cliente',
                'cliente', 'deudor', 'nombre_deudor',
            ])
            ?? '';

        $apellidos = $this->extraerValorCampoSistema('apellidos', $fila, $esquema)
            ?? $this->buscarEnCp($fila, $esquema, ['apellidos', 'apellido'])
            ?? '';

        $razonSocial = $this->extraerValorCampoSistema('razon_social', $fila, $esquema)
            ?? $this->buscarEnCp($fila, $esquema, ['razonsocial', 'razon_social', 'razon'])
            ?? '';

        $tipoPersona = $razonSocial !== '' ? TipoPersona::JURIDICA : TipoPersona::FISICA;

        if ($tipoIdentId === null) {
            $cedId = (int) $this->db->table('tipos_identificacion')->where('codigo', 'CED')->value('id');
            $tipoIdentId = $cedId > 0 ? $cedId : (int) $this->db->table('tipos_identificacion')->orderBy('id')->value('id');
            if ($tipoIdentId === 0 || $tipoIdentId === null) {
                throw ImportacionNoProcesable::sinTiposDeIdentificacion();
            }
        }

        $output = $this->registrarPersona->execute(new RegistrarPersonaInput(
            publicId: (string) Str::ulid(),
            proyectoId: $proyectoId,
            tipoIdentificacionId: $tipoIdentId,
            identificacion: new Identificacion($identificacionValor),
            tipoPersona: $tipoPersona,
            nombres: $nombres !== '' ? $nombres : null,
            apellidos: $apellidos !== '' ? $apellidos : null,
            razonSocial: $razonSocial !== '' ? $razonSocial : null,
            fechaNacimiento: null,
            creadaEn: CarbonImmutable::now(),
        ));

        return $output->id;
    }

    /**
     * Busca un valor en las columnas de campos personalizados por lista de códigos candidatos.
     *
     * @param  list<string>  $candidatos
     */
    private function buscarEnCp(
        array $fila,
        EsquemaImportacion $esquema,
        array $candidatos,
    ): ?string {
        foreach ($esquema->columnasParaCamposPersonalizados() as $col) {
            if (in_array($col->codigoSugerido(), $candidatos, true)) {
                $valor = $fila[$col->codigoSugerido()] ?? '';
                if ($valor !== '') {
                    return $valor;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $fila
     */
    private function registrarCaso(
        TargetImportacion $target,
        int $proyectoId,
        int $carteraId,
        int $personaId,
        int $estadoCasoId,
        DateTimeImmutable $fechaIngreso,
        array $fila,
        EsquemaImportacion $esquema,
    ): int {
        $idUnico = $fila['id_cpelegido'] ?? (string) Str::ulid();

        return match ($target) {
            TargetImportacion::CASO_COBRANZA => $this->registrarCobranza->execute(
                new RegistrarCasoCobranzaInput(
                    proyectoId: $proyectoId,
                    carteraId: $carteraId,
                    personaId: $personaId,
                    estadoCasoId: $estadoCasoId,
                    fechaIngreso: $fechaIngreso,
                    prioridad: 1,
                    numeroPrestamo: $idUnico,
                    montoOriginal: $this->decimal('monto_original', $fila, $esquema),
                    saldoCapital: $this->decimal('saldo_capital', $fila, $esquema),
                    saldoInteres: $this->decimal('saldo_interes', $fila, $esquema),
                    saldoTotal: $this->decimal('saldo_total', $fila, $esquema),
                    cuotaMensual: $this->decimal('cuota_mensual', $fila, $esquema),
                    cuotasTotales: $this->enteroSinSigno('cuotas_totales', $fila, $esquema),
                    cuotasPagadas: $this->enteroSinSigno('cuotas_pagadas', $fila, $esquema),
                    diasMora: $this->enteroSinSigno('dias_mora', $fila, $esquema),
                    fechaDesembolso: $this->fecha('fecha_desembolso', $fila, $esquema),
                    fechaVencimiento: $this->fecha('fecha_vencimiento', $fila, $esquema),
                ),
            )->casoId,
            TargetImportacion::CASO_TICKET_CX => $this->registrarCx->execute(
                new RegistrarCasoTicketCxInput(
                    proyectoId: $proyectoId,
                    carteraId: $carteraId,
                    personaId: $personaId,
                    estadoCasoId: $estadoCasoId,
                    fechaIngreso: $fechaIngreso,
                    prioridad: 1,
                    codigoTicket: $idUnico,
                    asunto: $this->texto('asunto', $fila, $esquema),
                    descripcion: $this->texto('descripcion', $fila, $esquema),
                    fechaReporte: $this->fecha('fecha_reporte', $fila, $esquema),
                    fechaLimiteSla: $this->fecha('fecha_limite_sla', $fila, $esquema),
                ),
            )->casoId,
            TargetImportacion::CASO_LEAD_VENTA => $this->registrarVenta->execute(
                new RegistrarCasoLeadVentaInput(
                    proyectoId: $proyectoId,
                    carteraId: $carteraId,
                    personaId: $personaId,
                    estadoCasoId: $estadoCasoId,
                    fechaIngreso: $fechaIngreso,
                    prioridad: 1,
                    codigoLead: $idUnico,
                    valorEstimadoMonto: $this->decimal('valor_estimado_monto', $fila, $esquema),
                    origenLead: $this->texto('origen_lead', $fila, $esquema),
                    fechaPrimerContacto: $this->fecha('fecha_primer_contacto', $fila, $esquema),
                    fechaEstimadaCierre: $this->fecha('fecha_estimada_cierre', $fila, $esquema),
                ),
            )->casoId,
            TargetImportacion::CASO_SERVICIO => $this->registrarServicio->execute(
                new RegistrarCasoServicioInput(
                    proyectoId: $proyectoId,
                    carteraId: $carteraId,
                    personaId: $personaId,
                    estadoCasoId: $estadoCasoId,
                    fechaIngreso: $fechaIngreso,
                    prioridad: 1,
                    codigoServicio: $idUnico,
                    direccionServicio: $this->texto('direccion_servicio', $fila, $esquema),
                    tecnicoAsignado: $this->texto('tecnico_asignado', $fila, $esquema),
                    fechaSolicitud: $this->fecha('fecha_solicitud', $fila, $esquema),
                    fechaProgramada: $this->fecha('fecha_programada', $fila, $esquema),
                ),
            )->casoId,
            default => throw ImportacionNoProcesable::targetNoSoportado($target->value),
        };
    }

    /**
     * Valor de texto de un campo del sistema, o null si la columna no vino o vino vacía.
     *
     * @param  array<string, string>  $fila
     */
    private function texto(string $campo, array $fila, EsquemaImportacion $esquema): ?string
    {
        $valor = trim((string) $this->extraerValorCampoSistema($campo, $fila, $esquema));

        return $valor !== '' ? $valor : null;
    }

    /**
     * Decimal normalizado como string (el DTO del CTI espera string para no perder precisión).
     * Acepta "1,250.40", "$1.250,40", "1250.40" y "(120.00)" como negativo contable.
     *
     * @param  array<string, string>  $fila
     */
    private function decimal(string $campo, array $fila, EsquemaImportacion $esquema): ?string
    {
        $bruto = $this->texto($campo, $fila, $esquema);
        if ($bruto === null) {
            return null;
        }

        $negativo = str_starts_with($bruto, '(') && str_ends_with($bruto, ')');
        $limpio = (string) preg_replace('/[^0-9,.\-]/', '', $bruto);
        $limpio = $this->unificarSeparadorDecimal($limpio);

        if ($limpio === '' || ! is_numeric($limpio)) {
            return null;
        }

        return $negativo ? '-'.ltrim($limpio, '-') : $limpio;
    }

    /**
     * Deja un único punto como separador decimal, eliminando separadores de miles.
     * "1.250,40" → "1250.40"; "1,250.40" → "1250.40"; "1250,40" → "1250.40".
     */
    private function unificarSeparadorDecimal(string $numero): string
    {
        $ultimaComa = strrpos($numero, ',');
        $ultimoPunto = strrpos($numero, '.');

        if ($ultimaComa !== false && $ultimoPunto !== false) {
            return $ultimaComa > $ultimoPunto
                ? str_replace(',', '.', str_replace('.', '', $numero))
                : str_replace(',', '', $numero);
        }

        if ($ultimaComa !== false) {
            return substr_count($numero, ',') === 1 && strlen($numero) - $ultimaComa <= 3
                ? str_replace(',', '.', $numero)
                : str_replace(',', '', $numero);
        }

        return $numero;
    }

    /**
     * @param  array<string, string>  $fila
     */
    private function entero(string $campo, array $fila, EsquemaImportacion $esquema): ?int
    {
        $decimal = $this->decimal($campo, $fila, $esquema);

        return $decimal !== null ? (int) round((float) $decimal) : null;
    }

    /**
     * Como `entero`, pero descarta negativos: las columnas de mora y cuotas del CTI
     * son `int unsigned`, y un "días en atraso" negativo es días por vencer, no mora.
     *
     * @param  array<string, string>  $fila
     */
    private function enteroSinSigno(string $campo, array $fila, EsquemaImportacion $esquema): ?int
    {
        $valor = $this->entero($campo, $fila, $esquema);

        return $valor !== null && $valor >= 0 ? $valor : null;
    }

    /**
     * @param  array<string, string>  $fila
     */
    private function fecha(string $campo, array $fila, EsquemaImportacion $esquema): ?DateTimeImmutable
    {
        $bruto = $this->texto($campo, $fila, $esquema);
        if ($bruto === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($bruto);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{campo_id: int, entidad_id: int, valor: mixed, tipo: string}>
     */
    private function acumularValoresCp(
        ProcesarFilaInput $input,
        ?int $entidadId,
    ): array {
        if ($entidadId === null) {
            return [];
        }

        $valores = [];
        $columnasCP = $input->esquema->columnasParaCamposPersonalizados();

        foreach ($columnasCP as $columna) {
            $codigo = $columna->codigoSugerido();
            $mapaEntry = $input->mapaCampos[$codigo] ?? null;
            if ($mapaEntry === null) {
                continue;
            }

            $valor = $input->fila[$columna->codigoSugerido()] ?? null;
            if ($valor === null || trim($valor) === '') {
                continue;
            }

            $valores[] = [
                'campo_id' => $mapaEntry['id'],
                'entidad_id' => $entidadId,
                'valor' => $this->mapearValorPorTipo($columna, $valor),
                'tipo' => $mapaEntry['tipo'],
            ];
        }

        return $valores;
    }

    private function mapearValorPorTipo(ColumnaExcel $columna, string $valor): mixed
    {
        return match ($columna->tipoInferido) {
            TipoCampo::NUMERO_ENTERO => (int) $valor,
            TipoCampo::NUMERO_DECIMAL => (float) str_replace(',', '', $valor),
            TipoCampo::BOOLEANO => in_array(strtolower(trim($valor)), ['true', '1', 'si', 'sí', 'yes'], true),
            default => $valor,
        };
    }
}
