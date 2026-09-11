<?php

declare(strict_types=1);

namespace App\Modules\Casos\Application\Services;

use App\Support\Database\CarterasOperativas;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use stdClass;

/** Read model for a person's operational accounts and their pending commitments. */
final readonly class ConsultaFichaTrabajo
{
    public function __construct(private ConnectionInterface $db) {}

    public function tieneCuentasRegistradas(int $proyectoId, int $personaId): bool
    {
        return $this->db->table('casos')->where('proyecto_id', $proyectoId)
            ->where('persona_id', $personaId)->exists();
    }

    /**
     * @param  list<int>|null  $carteras
     * @return Collection<int, stdClass>
     */
    public function cuentas(int $proyectoId, int $personaId, ?array $carteras): Collection
    {
        return CarterasOperativas::casos($this->db, $proyectoId)
            ->join('carteras as ca', fn ($join) => $join->on('ca.id', '=', 'c.cartera_id')->on('ca.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('estados_caso as ec', fn ($join) => $join->on('ec.id', '=', 'c.estado_caso_id')->on('ec.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('resultados as ru', fn ($join) => $join->on('ru.id', '=', 'c.resultado_ultima_gestion_id')->on('ru.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('casos_cobranza as cc', fn ($join) => $join->on('cc.caso_id', '=', 'c.id')->on('cc.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('casos_ticket_cx as cx', fn ($join) => $join->on('cx.caso_id', '=', 'c.id')->on('cx.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('casos_lead_venta as cv', fn ($join) => $join->on('cv.caso_id', '=', 'c.id')->on('cv.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('casos_servicio as cs', fn ($join) => $join->on('cs.caso_id', '=', 'c.id')->on('cs.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('asignaciones as a', fn ($join) => $join->on('a.caso_id', '=', 'c.id')->on('a.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('users as asesor', 'asesor.id', '=', 'a.usuario_id')
            ->where('c.persona_id', $personaId)
            ->when($carteras !== null, fn (Builder $q) => $q->whereIn('c.cartera_id', $carteras ?? []))
            ->select(['c.id', 'c.public_id', 'c.tipo_caso', 'c.prioridad', 'c.cartera_id',
                'c.fecha_ingreso', 'c.cerrado_en', 'c.fecha_ultima_gestion', 'c.tiene_compromiso_vigente',
                'ec.nombre as estado_caso_nombre', 'ec.codigo as estado_caso_codigo', 'ca.nombre as cartera_nombre',
                'ru.nombre as resultado_ultimo_nombre', 'cc.saldo_total', 'cc.moneda', 'cc.dias_mora',
                'asesor.name as asesor_nombre', 'a.usuario_id as asesor_id'])
            ->selectRaw('COALESCE(cc.numero_prestamo, cx.codigo_ticket, cv.codigo_lead, cs.codigo_servicio, c.public_id) as referencia')
            ->orderByDesc('c.prioridad')->orderByDesc('c.fecha_ingreso')->orderBy('c.id')->get();
    }

    /**
     * The caller passes only account IDs returned by cuentas(), after portfolio authorization.
     *
     * @param  list<int>  $casos
     * @return Collection<int, stdClass>
     */
    public function compromisosPendientes(int $proyectoId, array $casos): Collection
    {
        return $this->db->table('compromisos as co')
            ->leftJoin('compromisos_promesa_pago as pp', fn ($join) => $join->on('pp.compromiso_id', '=', 'co.id')->on('pp.proyecto_id', '=', 'co.proyecto_id'))
            ->leftJoin('compromisos_cierre_venta as cv', fn ($join) => $join->on('cv.compromiso_id', '=', 'co.id')->on('cv.proyecto_id', '=', 'co.proyecto_id'))
            ->leftJoin('compromisos_resolucion_ticket as rt', fn ($join) => $join->on('rt.compromiso_id', '=', 'co.id')->on('rt.proyecto_id', '=', 'co.proyecto_id'))
            ->leftJoin('compromisos_accion_servicio as sa', fn ($join) => $join->on('sa.compromiso_id', '=', 'co.id')->on('sa.proyecto_id', '=', 'co.proyecto_id'))
            ->leftJoin('tipos_pago as tp', fn ($join) => $join->on('tp.id', '=', 'pp.tipo_pago_id')->on('tp.proyecto_id', '=', 'co.proyecto_id'))
            ->leftJoin('users as u', 'u.id', '=', 'co.usuario_id')
            ->where('co.proyecto_id', $proyectoId)->whereIn('co.caso_id', $casos)
            ->where('co.estado', 'pendiente')->whereNull('co.eliminada_en')
            ->select(['co.id', 'co.public_id', 'co.caso_id', 'co.tipo_compromiso', 'co.fecha_vencimiento',
                'tp.nombre as tipo_pago_nombre', 'u.name as usuario_nombre'])
            ->selectRaw('COALESCE(pp.monto, cv.monto_cierre) as monto, COALESCE(pp.moneda, cv.moneda) as moneda, COALESCE(rt.accion_comprometida, sa.descripcion_accion) as detalle')
            ->orderBy('co.fecha_vencimiento')->orderBy('co.id')->get();
    }
}
