<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Services;

use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\JoinClause;

final readonly class ConsultaCoincidenciasImportacion
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array{nuevas: int, misma_cartera: int, otras_activas: int, archivadas: int, carteras: list<array{cartera_id: int, codigo: string, nombre: string, archivada: bool, filas: int}>} */
    public function execute(int $proyectoId, int $importacionId): array
    {
        $resumen = ['nuevas' => 0, 'misma_cartera' => 0, 'otras_activas' => 0, 'archivadas' => 0, 'carteras' => []];
        $importacion = $this->db->table('importaciones')->where('proyecto_id', $proyectoId)->where('id', $importacionId)->first(['esquema', 'tipo_entidad']);
        if ($importacion === null || ! is_string($importacion->esquema)) {
            return $resumen;
        }
        $esquema = EsquemaImportacion::deserializar($importacion->esquema);
        $tipoOperacion = (string) $this->db->table('proyectos')->where('id', $proyectoId)->value('tipo_operacion');
        $esquema->validarContexto($proyectoId, (string) $importacion->tipo_entidad, $tipoOperacion);
        [$tabla, $identificador] = match ($esquema->target->value) {
            'caso_cobranza' => ['casos_cobranza', 'numero_prestamo'],
            'caso_ticket_cx' => ['casos_ticket_cx', 'codigo_ticket'],
            'caso_lead_venta' => ['casos_lead_venta', 'codigo_lead'],
            'caso_servicio' => ['casos_servicio', 'codigo_servicio'],
            default => [null, null],
        };
        if ($tabla === null) {
            return $resumen;
        }
        $grupos = $this->db->table('importacion_filas as f')
            ->leftJoin($tabla.' as t', function (JoinClause $j) use ($identificador): void {
                $j->on('t.proyecto_id', '=', 'f.proyecto_id')->whereRaw('t.'.$identificador.' = TRIM(JSON_UNQUOTE(JSON_EXTRACT(f.payload, ?)))', ['$.id_cpelegido']);
            })
            ->leftJoin('casos as c', fn (JoinClause $j) => $j->on('c.id', '=', 't.caso_id')->on('c.proyecto_id', '=', 'f.proyecto_id'))
            ->leftJoin('carteras as ca', fn (JoinClause $j) => $j->on('ca.id', '=', 'c.cartera_id')->on('ca.proyecto_id', '=', 'f.proyecto_id'))
            ->where('f.proyecto_id', $proyectoId)->where('f.importacion_id', $importacionId)
            ->select(['ca.id', 'ca.codigo', 'ca.nombre', 'ca.activo', 'ca.eliminada_en'])
            ->selectRaw('COUNT(*) as filas')->groupBy('ca.id', 'ca.codigo', 'ca.nombre', 'ca.activo', 'ca.eliminada_en')->get();
        foreach ($grupos as $grupo) {
            if ($grupo->id === null) {
                $resumen['nuevas'] += (int) $grupo->filas;

                continue;
            }
            $archivada = ! (bool) $grupo->activo || $grupo->eliminada_en !== null;
            $clave = $archivada ? 'archivadas' : ((int) $grupo->id === $esquema->carteraId ? 'misma_cartera' : 'otras_activas');
            $resumen[$clave] += (int) $grupo->filas;
            $resumen['carteras'][] = ['cartera_id' => (int) $grupo->id, 'codigo' => (string) $grupo->codigo,
                'nombre' => (string) $grupo->nombre, 'archivada' => $archivada, 'filas' => (int) $grupo->filas];
        }

        return $resumen;
    }
}
