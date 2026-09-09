<?php

declare(strict_types=1);

namespace App\Modules\Casos\Application\Services;

use App\Modules\Auditoria\Domain\Contracts\RegistroDeExportaciones;
use App\Modules\Casos\Application\DTOs\FiltrosListadoCasos;
use App\Modules\Casos\Domain\Columnas\CatalogoColumnasCaso;
use App\Modules\Casos\Domain\Columnas\ColumnaCaso;
use App\Modules\Tenancy\Application\Services\RelojDelMandante;
use App\Support\Csv\RespuestaCsv;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La hoja de casos que pide el cliente, no las siete columnas de la pantalla.
 *
 * Bloque fijo (caso, persona, estado, última gestión) + TODAS las columnas de
 * la tabla CTI del tipo de operación + en cobranza el tramo y la fecha en que
 * el cliente afirmó la mora + una columna por cada campo personalizado de
 * caso definido en las carteras del proyecto. Un supervisor que exporta la
 * cartera quiere saldo, mora y tramo aunque en el listado tenga visibles sólo
 * persona y estado.
 *
 * Los valores de los campos personalizados se cargan por LOTE (una consulta
 * por cada 500 casos) y no por fila: con 8.000 cuentas y 30 campos, por fila
 * serían 8.000 consultas. Las opciones de los campos de selección se cargan
 * una sola vez antes del stream, porque son las mismas para todas las filas.
 */
final readonly class ExportadorCsvCasos
{
    private const FIJAS = [
        'caso_public_id', 'tipo_caso', 'estado', 'cartera',
        'tipo_identificacion', 'identificacion', 'nombres', 'apellidos', 'razon_social',
        'prioridad', 'fecha_ingreso', 'fecha_ultima_gestion', 'resultado_ultimo',
        'usuario_ultima_gestion', 'tiene_compromiso_vigente', 'cerrado_en',
    ];

    /** Lo que la cartera de cobranza necesita y el catálogo de pantalla no enseña. */
    private const COMPLEMENTARIAS_COBRANZA = ['tramo_mora', 'dias_mora_confirmado_en'];

    public function __construct(
        private ConnectionInterface $db,
        private ConsultaListadoCasos $consulta,
        private RelojDelMandante $reloj,
        private RegistroDeExportaciones $registro,
    ) {}

    /**
     * @param  stdClass  $proyecto  Fila de `proyectos` con `id`, `codigo`, `mandante_id` y `tipo_operacion`.
     */
    /**
     * @param  list<int>|null  $carterasPermitidas  El límite por cartera del rol (F22); null sin límite.
     */
    public function responder(stdClass $proyecto, FiltrosListadoCasos $filtros, ?array $carterasPermitidas = null): StreamedResponse
    {
        $proyectoId = (int) $proyecto->id;
        $tipoOperacion = (string) $proyecto->tipo_operacion;
        $esCobranza = $tipoOperacion === 'cobranza';

        // Una vez, fuera del stream: no cambia entre filas.
        $zona = $this->reloj->zonaDe((int) $proyecto->mandante_id);

        $especificas = CatalogoColumnasCaso::especificasDe($tipoOperacion);
        $campos = $this->camposDeCasoDelProyecto($proyectoId);
        $columnasDeCampos = $this->columnasDeCampos($campos);
        $camposPorCartera = $this->camposPorCartera($campos);
        $opciones = $this->etiquetasDeOpciones($campos);
        $idsDeCampos = array_map(static fn (stdClass $c): int => (int) $c->id, $campos);

        $q = $this->consulta
            ->aplicarFiltros(
                $this->consulta->recortarACarteras($this->consulta->consultaBase($proyectoId, $tipoOperacion), $carterasPermitidas),
                $filtros,
            )
            ->leftJoin('tipos_identificacion as ti', 'ti.id', '=', 'p.tipo_identificacion_id')
            ->leftJoin('resultados as r', 'r.id', '=', 'c.resultado_ultima_gestion_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.usuario_ultima_gestion_id');

        $seleccion = [
            'c.id', 'c.cartera_id', 'c.public_id as caso_public_id', 'c.tipo_caso',
            'ec.nombre as estado', 'ca.nombre as cartera',
            'ti.codigo as tipo_identificacion', 'p.identificacion', 'p.nombres', 'p.apellidos', 'p.razon_social',
            'c.prioridad', 'c.fecha_ingreso', 'c.fecha_ultima_gestion',
            'r.nombre as resultado_ultimo', 'u.name as usuario_ultima_gestion',
            'c.tiene_compromiso_vigente', 'c.cerrado_en',
        ];

        foreach ($especificas as $columna) {
            $seleccion[] = $this->db->raw($columna->expresion.' as '.$columna->alias());
        }

        if ($esCobranza) {
            $q->leftJoin('tramos_mora as tm', 'tm.id', '=', 'cti.tramo_mora_id');
            $seleccion[] = 'tm.nombre as tramo_mora';
            $seleccion[] = 'cti.dias_mora_confirmado_en';
        }

        $q->select($seleccion);

        $cabeceras = [
            ...self::FIJAS,
            ...array_map(static fn (ColumnaCaso $c): string => $c->clave, $especificas),
            ...($esCobranza ? self::COMPLEMENTARIAS_COBRANZA : []),
            ...array_values($columnasDeCampos),
        ];

        return RespuestaCsv::desdeConsulta(
            nombreFichero: 'casos_'.$proyecto->codigo.'_'.Carbon::now()->format('Ymd_His').'.csv',
            cabeceras: $cabeceras,
            consulta: $q,
            columnaId: 'c.id',
            aliasId: 'id',
            fila: function (stdClass $c, mixed $valores) use ($especificas, $esCobranza, $columnasDeCampos, $camposPorCartera, $opciones, $zona): array {
                $fila = [
                    $c->caso_public_id,
                    $c->tipo_caso,
                    $c->estado,
                    $c->cartera,
                    $c->tipo_identificacion,
                    $c->identificacion,
                    $c->nombres,
                    $c->apellidos,
                    $c->razon_social,
                    (int) $c->prioridad,
                    $c->fecha_ingreso,
                    $this->instante($c->fecha_ultima_gestion, $zona),
                    $c->resultado_ultimo,
                    $c->usuario_ultima_gestion,
                    (bool) $c->tiene_compromiso_vigente,
                    $this->instante($c->cerrado_en, $zona),
                ];

                foreach ($especificas as $columna) {
                    $valor = $c->{$columna->alias()} ?? null;
                    $fila[] = $columna->instante ? $this->instante($valor, $zona) : $valor;
                }

                if ($esCobranza) {
                    $fila[] = $c->tramo_mora;
                    $fila[] = $c->dias_mora_confirmado_en;
                }

                /** @var array<int, array<int, stdClass>> $valores */
                $porCampo = $valores[(int) $c->id] ?? [];
                foreach (array_keys($columnasDeCampos) as $codigo) {
                    $campo = $camposPorCartera[(int) $c->cartera_id][$codigo] ?? null;
                    $fila[] = $campo === null ? null : $this->celdaDeCampo($campo, $porCampo[(int) $campo->id] ?? null, $opciones, $zona);
                }

                return $fila;
            },
            prepararLote: fn (Collection $lote): array => $this->valoresDelLote($lote, $idsDeCampos),
            alTerminar: fn (int $total, bool $completa = true) => $this->registro->registrar(
                'casos',
                $filtros->comoParametros(),
                $total,
                $proyectoId,
                completa: $completa,
            ),
        );
    }

    /**
     * Los campos personalizados de caso del proyecto: los que cuelgan de una
     * cartera SUYA. `ambito_id` no tiene FK física (§7), así que la pertenencia
     * se comprueba uniendo con `carteras` por proyecto.
     *
     * @return list<stdClass>
     */
    private function camposDeCasoDelProyecto(int $proyectoId): array
    {
        return $this->db->table('campos_personalizados as cp')
            ->join('carteras as ca', function ($join) use ($proyectoId): void {
                $join->on('ca.id', '=', 'cp.ambito_id')->where('ca.proyecto_id', $proyectoId);
            })
            ->where('cp.proyecto_id', $proyectoId)
            ->where('cp.ambito', 'caso')
            ->where('cp.activo', true)
            ->orderBy('ca.nombre')
            ->orderBy('cp.orden')
            ->orderBy('cp.id')
            ->get(['cp.id', 'cp.ambito_id as cartera_id', 'cp.codigo', 'cp.etiqueta', 'cp.tipo'])
            ->all();
    }

    /**
     * Una columna por código distinto, con la etiqueta del primero que lo
     * declara: dos carteras que definen `SALDO_REAL` comparten columna, que es
     * lo que espera quien abre la hoja.
     *
     * @param  list<stdClass>  $campos
     * @return array<string, string> código => etiqueta, en orden de aparición.
     */
    private function columnasDeCampos(array $campos): array
    {
        $columnas = [];
        foreach ($campos as $campo) {
            $columnas[(string) $campo->codigo] ??= (string) $campo->etiqueta;
        }

        return $columnas;
    }

    /**
     * @param  list<stdClass>  $campos
     * @return array<int, array<string, stdClass>> cartera => código => campo.
     */
    private function camposPorCartera(array $campos): array
    {
        $porCartera = [];
        foreach ($campos as $campo) {
            $porCartera[(int) $campo->cartera_id][(string) $campo->codigo] = $campo;
        }

        return $porCartera;
    }

    /**
     * @param  list<stdClass>  $campos
     * @return array<int, string> id de opción => etiqueta.
     */
    private function etiquetasDeOpciones(array $campos): array
    {
        $deSeleccion = [];
        foreach ($campos as $campo) {
            if (in_array((string) $campo->tipo, ['seleccion_unica', 'seleccion_multiple'], true)) {
                $deSeleccion[] = (int) $campo->id;
            }
        }

        if ($deSeleccion === []) {
            return [];
        }

        $etiquetas = [];
        $filas = $this->db->table('opciones_campo_personalizado')
            ->whereIn('campo_personalizado_id', $deSeleccion)
            ->get(['id', 'etiqueta']);
        foreach ($filas as $opcion) {
            $etiquetas[(int) $opcion->id] = (string) $opcion->etiqueta;
        }

        return $etiquetas;
    }

    /**
     * Los valores de los campos para los casos del lote, en una consulta.
     *
     * @param  Collection<int, stdClass>  $lote
     * @param  list<int>  $idsDeCampos
     * @return array<int, array<int, stdClass>> caso => campo => fila de valor.
     */
    private function valoresDelLote(Collection $lote, array $idsDeCampos): array
    {
        if ($idsDeCampos === []) {
            return [];
        }

        $casos = $lote->map(static fn (stdClass $c): int => (int) $c->id)->all();

        $valores = [];
        $filas = $this->db->table('valores_campo_personalizado')
            ->whereIn('campo_personalizado_id', $idsDeCampos)
            ->whereIn('entidad_id', $casos)
            ->get([
                'campo_personalizado_id', 'entidad_id',
                'valor_texto_corto', 'valor_texto_largo',
                'valor_numero_entero', 'valor_numero_decimal',
                'valor_fecha', 'valor_fecha_hora', 'valor_booleano',
                'valor_opcion_id', 'valor_opciones_ids',
                'valor_moneda_monto', 'valor_moneda_codigo',
            ]);
        foreach ($filas as $fila) {
            $valores[(int) $fila->entidad_id][(int) $fila->campo_personalizado_id] = $fila;
        }

        return $valores;
    }

    /**
     * El valor de un campo como celda, según su tipo (§7, diez tipos cerrados).
     *
     * @param  array<int, string>  $opciones
     */
    private function celdaDeCampo(stdClass $campo, ?stdClass $v, array $opciones, string $zona): string|bool|null
    {
        if ($v === null) {
            return null;
        }

        return match ((string) $campo->tipo) {
            'texto_corto' => $v->valor_texto_corto,
            'texto_largo' => $v->valor_texto_largo,
            'numero_entero' => $v->valor_numero_entero === null ? null : (string) (int) $v->valor_numero_entero,
            'numero_decimal' => $this->decimal($v->valor_numero_decimal),
            'fecha' => $v->valor_fecha,
            // Tal cual está guardado, sin pasar por la zona: lo captura un
            // `datetime-local` y se guarda como lo tecleó el gestor, que ya es
            // hora local. Convertirlo como si fuera UTC lo desplazaría cinco
            // horas en Panamá, y la pantalla lo enseña sin convertir.
            'fecha_hora' => $v->valor_fecha_hora === null ? null : (string) $v->valor_fecha_hora,
            'booleano' => $v->valor_booleano === null ? null : (bool) $v->valor_booleano,
            'moneda' => $v->valor_moneda_monto === null
                ? null
                : trim((string) $v->valor_moneda_monto.' '.(string) ($v->valor_moneda_codigo ?? '')),
            'seleccion_unica' => $v->valor_opcion_id === null ? null : ($opciones[(int) $v->valor_opcion_id] ?? null),
            'seleccion_multiple' => $this->etiquetasMultiples($v->valor_opciones_ids, $opciones),
            default => null,
        };
    }

    /** @param  array<int, string>  $opciones */
    private function etiquetasMultiples(mixed $ids, array $opciones): ?string
    {
        $lista = is_array($ids) ? $ids : json_decode((string) $ids, true);
        if (! is_array($lista)) {
            return null;
        }

        $etiquetas = [];
        foreach ($lista as $id) {
            if (is_scalar($id) && isset($opciones[(int) $id])) {
                $etiquetas[] = $opciones[(int) $id];
            }
        }

        return $etiquetas === [] ? null : implode(' | ', $etiquetas);
    }

    /** `decimal(18,4)` sin los ceros de relleno: «12.5», no «12.5000». */
    private function decimal(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = (string) $valor;

        return str_contains($texto, '.') ? rtrim(rtrim($texto, '0'), '.') : $texto;
    }

    /** Un instante UTC de la base, escrito en la hora del cliente. */
    private function instante(mixed $valor, string $zona): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return Carbon::parse((string) $valor, 'UTC')->setTimezone($zona)->format('Y-m-d H:i:s');
    }
}
