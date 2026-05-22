<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use App\Modules\Cx\Application\DTOs\RegistrarCasoTicketCxInput;
use App\Modules\Cx\Application\UseCases\RegistrarCasoTicketCx;
use App\Modules\Importaciones\Application\Services\ResolverPersonaImportacion;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ResultadoFila;
use App\Modules\Personas\Application\DTOs\RegistrarPersonaInput;
use App\Modules\Personas\Application\UseCases\RegistrarPersona;
use App\Modules\Personas\Domain\ValueObjects\Identificacion;
use App\Modules\Personas\Domain\ValueObjects\TipoPersona;
use App\Modules\Servicio\Application\DTOs\RegistrarCasoServicioInput;
use App\Modules\Servicio\Application\UseCases\RegistrarCasoServicio;
use App\Modules\Venta\Application\DTOs\RegistrarCasoLeadVentaInput;
use App\Modules\Venta\Application\UseCases\RegistrarCasoLeadVenta;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Throwable;

/**
 * Procesa una sola fila de importación dinámica según el esquema configurado.
 *
 * NO persiste valores de campos personalizados directamente; los retorna
 * para que el job los acumule y llame a guardarValoresEnLote() en batch.
 */
final readonly class ProcesarFilaDinamica
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

    public function __construct(
        private ResolverPersonaImportacion $personaResolver,
        private RegistrarPersona $registrarPersona,
        private RegistrarCasoCobranza $registrarCobranza,
        private RegistrarCasoTicketCx $registrarCx,
        private RegistrarCasoLeadVenta $registrarVenta,
        private RegistrarCasoServicio $registrarServicio,
        private ConnectionInterface $db,
    ) {}

    public function execute(ProcesarFilaInput $input): ResultadoFilaConValoresCp
    {
        $esquema = $input->esquema;
        $fila = $input->fila;

        $columnaIdentidad = $esquema->columnaIdentificador();
        $identKey = $columnaIdentidad !== null
            ? ($columnaIdentidad->accion === AccionColumna::MAPEAR_SISTEMA
                ? $columnaIdentidad->campoSistemaMapeado
                : $columnaIdentidad->codigoSugerido())
            : null;
        $valorIdentidad = $identKey !== null ? ($fila[$identKey] ?? '') : '';

        $proyectoId = $esquema->proyectoId;
        $carteraId = $esquema->carteraId;

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
                $casoId = $this->buscarCasoExistente($esquema->target, $proyectoId, $fila, $esquema);
                $casoExistente = $casoId !== null;
            }
        }

        return match ($esquema->modo) {
            ModoImportacion::INSERT => $this->procesarInsert(
                $input, $fila, $esquema, $proyectoId, $carteraId,
                $personaId, $personaExistente, $casoId, $casoExistente,
                $tipoIdentId, $tiposIdentificacion,
            ),
            ModoImportacion::UPDATE => $this->procesarUpdate(
                $input, $fila, $esquema, $proyectoId, $carteraId,
                $personaId, $personaExistente, $casoId, $casoExistente,
                $tipoIdentId, $tiposIdentificacion,
            ),
            ModoImportacion::UPSERT => $this->procesarUpsert(
                $input, $fila, $esquema, $proyectoId, $carteraId,
                $personaId, $personaExistente, $casoId, $casoExistente,
                $tipoIdentId, $tiposIdentificacion,
            ),
            ModoImportacion::MERGE => $this->procesarMerge(
                $input, $fila, $esquema, $proyectoId, $carteraId,
                $personaId, $personaExistente, $casoId, $casoExistente,
                $tipoIdentId, $tiposIdentificacion,
            ),
            ModoImportacion::SKIP_DUPLICADOS => $this->procesarSkipDuplicados(
                $casoExistente, $personaExistente, $casoId, $personaId,
            ),
            ModoImportacion::OVERWRITE => $this->procesarOverwrite(
                $input, $fila, $esquema, $proyectoId, $carteraId,
                $personaId, $personaExistente, $casoId, $casoExistente,
                $tipoIdentId, $tiposIdentificacion,
            ),
        };
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
        EsquemaImportacion $esquema,
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

    /**
     * @param  array<string, string>  $fila
     * @param  array<string, int>  $tiposIdentificacion
     */
    private function procesarInsert(
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
        array $tiposIdentificacion,
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
     * @param  array<string, int>  $tiposIdentificacion
     */
    private function procesarUpdate(
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
        array $tiposIdentificacion,
    ): ResultadoFilaConValoresCp {
        if (! $casoExistente) {
            return new ResultadoFilaConValoresCp(
                ResultadoFila::omitida('El caso no existe en el proyecto'),
                [],
                fueInsert: false,
            );
        }

        $valoresCp = $this->acumularValoresCp($input, $casoId);

        $this->actualizarCasoExistente($esquema->target, $casoId, $fila, $esquema);

        return new ResultadoFilaConValoresCp(
            ResultadoFila::procesada($casoId),
            $valoresCp,
            fueInsert: false,
        );
    }

    /**
     * @param  array<string, string>  $fila
     * @param  array<string, int>  $tiposIdentificacion
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
        array $tiposIdentificacion,
    ): ResultadoFilaConValoresCp {
        if ($casoExistente) {
            $valoresCp = $this->acumularValoresCp($input, $casoId);
            $this->actualizarCasoExistente($esquema->target, $casoId, $fila, $esquema);

            return new ResultadoFilaConValoresCp(
                ResultadoFila::procesada($casoId),
                $valoresCp,
                fueInsert: false,
            );
        }

        if ($carteraId === null) {
            return new ResultadoFilaConValoresCp(
                ResultadoFila::invalida('Se requiere carteraId para crear un caso'),
                [],
            );
        }

        try {
            if (! $personaExistente) {
                $personaId = $this->crearOCrearPersona(
                    $proyectoId,
                    $tipoIdentId,
                    $fila,
                    $esquema,
                );
            }

            $estadoCasoId = $this->obtenerEstadoCasoDefault($proyectoId);
            $fechaIngreso = new DateTimeImmutable;

            $casoId = $this->registrarCaso(
                $esquema->target,
                $proyectoId,
                $carteraId,
                (int) $personaId,
                (int) $estadoCasoId,
                $fechaIngreso,
                $fila,
                $esquema,
            );

            $valoresCp = $this->acumularValoresCp($input, $casoId);

            return new ResultadoFilaConValoresCp(
                ResultadoFila::procesada($casoId),
                $valoresCp,
                fueInsert: true,
            );
        } catch (Throwable $e) {
            return new ResultadoFilaConValoresCp(
                ResultadoFila::invalida('Error al crear caso: '.mb_substr($e->getMessage(), 0, 200)),
                [],
                fueInsert: false,
            );
        }
    }

    /**
     * @param  array<string, string>  $fila
     * @param  array<string, int>  $tiposIdentificacion
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
        array $tiposIdentificacion,
    ): ResultadoFilaConValoresCp {
        if ($casoExistente) {
            $valoresCp = $this->acumularValoresCp($input, $casoId);
            $this->actualizarCasoExistente($esquema->target, $casoId, $fila, $esquema, mergeOnly: true);

            return new ResultadoFilaConValoresCp(
                ResultadoFila::procesada($casoId),
                $valoresCp,
                fueInsert: false,
            );
        }

        if ($carteraId === null) {
            return new ResultadoFilaConValoresCp(
                ResultadoFila::invalida('Se requiere carteraId para crear un caso'),
                [],
            );
        }

        try {
            if (! $personaExistente) {
                $personaId = $this->crearOCrearPersona(
                    $proyectoId,
                    $tipoIdentId,
                    $fila,
                    $esquema,
                );
            }

            $estadoCasoId = $this->obtenerEstadoCasoDefault($proyectoId);
            $fechaIngreso = new DateTimeImmutable;

            $casoId = $this->registrarCaso(
                $esquema->target,
                $proyectoId,
                $carteraId,
                (int) $personaId,
                (int) $estadoCasoId,
                $fechaIngreso,
                $fila,
                $esquema,
            );

            $valoresCp = $this->acumularValoresCp($input, $casoId);

            return new ResultadoFilaConValoresCp(
                ResultadoFila::procesada($casoId),
                $valoresCp,
                fueInsert: true,
            );
        } catch (Throwable $e) {
            return new ResultadoFilaConValoresCp(
                ResultadoFila::invalida('Error al crear caso: '.mb_substr($e->getMessage(), 0, 200)),
                [],
                fueInsert: false,
            );
        }
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
     * @param  array<string, string>  $fila
     * @param  array<string, int>  $tiposIdentificacion
     */
    private function procesarOverwrite(
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
        array $tiposIdentificacion,
    ): ResultadoFilaConValoresCp {
        if ($casoExistente) {
            $valoresCp = $this->acumularValoresCp($input, $casoId);
            $this->actualizarCasoExistente($esquema->target, $casoId, $fila, $esquema);

            return new ResultadoFilaConValoresCp(
                ResultadoFila::procesada($casoId),
                $valoresCp,
                fueInsert: false,
            );
        }

        if ($carteraId === null) {
            return new ResultadoFilaConValoresCp(
                ResultadoFila::invalida('Se requiere carteraId para crear un caso'),
                [],
            );
        }

        try {
            if (! $personaExistente) {
                $personaId = $this->crearOCrearPersona(
                    $proyectoId,
                    $tipoIdentId,
                    $fila,
                    $esquema,
                );
            }

            $estadoCasoId = $this->obtenerEstadoCasoDefault($proyectoId);
            $fechaIngreso = new DateTimeImmutable;

            $casoId = $this->registrarCaso(
                $esquema->target,
                $proyectoId,
                $carteraId,
                (int) $personaId,
                (int) $estadoCasoId,
                $fechaIngreso,
                $fila,
                $esquema,
            );

            $valoresCp = $this->acumularValoresCp($input, $casoId);

            return new ResultadoFilaConValoresCp(
                ResultadoFila::procesada($casoId),
                $valoresCp,
                fueInsert: true,
            );
        } catch (Throwable $e) {
            return new ResultadoFilaConValoresCp(
                ResultadoFila::invalida('Error al crear caso: '.mb_substr($e->getMessage(), 0, 200)),
                [],
                fueInsert: false,
            );
        }
    }

    /**
     * @param  array<string, string>  $fila
     */
    private function actualizarCasoExistente(
        TargetImportacion $target,
        int $casoId,
        array $fila,
        EsquemaImportacion $esquema,
        bool $mergeOnly = false,
    ): void {
        $tabla = self::TABLAS_CTI[$target->value] ?? null;
        if ($tabla === null) {
            return;
        }

        $columnasSistema = $esquema->columnasParaSistema();
        $update = [];

        $camposMutables = $this->camposMutablesPorTarget($target);

        foreach ($camposMutables as $campo) {
            $columna = $columnasSistema[$campo] ?? null;
            if ($columna === null) {
                continue;
            }

            $valor = trim($fila[$columna->campoSistemaMapeado] ?? '');
            if ($valor === '') {
                continue;
            }

            if ($mergeOnly) {
                $valorActual = $this->db->table($tabla)
                    ->where('caso_id', $casoId)
                    ->value($campo);

                if ($valorActual !== null && (string) $valorActual !== '' && (float) $valorActual !== 0.0) {
                    continue;
                }
            }

            $update[$campo] = $valor;
        }

        if ($update !== []) {
            $update['actualizada_en'] = CarbonImmutable::now();
            $this->db->table($tabla)->where('caso_id', $casoId)->update($update);
        }
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
            throw new \RuntimeException("No hay estados de caso activos configurados para el proyecto {$proyectoId}.");
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
                $identKey = $columnaIdentidad->accion === AccionColumna::MAPEAR_SISTEMA
                    ? $columnaIdentidad->campoSistemaMapeado
                    : $columnaIdentidad->codigoSugerido();
                $identificacionValor = $fila[$identKey] ?? '';
            }
        }
        if ($identificacionValor === '') {
            throw new \RuntimeException('No se puede crear persona sin identificación.');
        }

        $nombres = $this->extraerValorCampoSistema('nombres', $fila, $esquema)
            ?? $this->buscarEnCp($fila, $esquema, ['nombres', 'nombre', 'nombre_del_cliente'])
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
                throw new \RuntimeException('No hay tipos de identificación disponibles en el sistema.');
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
        $columnasSistema = $esquema->columnasParaSistema();

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
                ),
            )->casoId,
            default => throw new \RuntimeException("Target no soportado: {$target->value}"),
        };
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
