<?php

declare(strict_types=1);

namespace App\Modules\Casos\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use stdClass;

/** Archived portfolio stages, including immutable snapshots taken before reincorporation. */
final readonly class ConsultaHistorico
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * A current archived account and each completed portfolio stage are separate rows.
     * Filtering uses the source portfolio, never the account's new operational portfolio.
     *
     * @param  list<int>|null  $carteras
     */
    public function cuentas(int $proyectoId, ?array $carteras, string $busqueda = '', string $cartera = '', string $caso = '', string $archivo = ''): Builder
    {
        $actuales = $this->base($proyectoId)
            ->leftJoin('casos_cobranza as cc', fn ($j) => $j->on('cc.caso_id', '=', 'c.id')->on('cc.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('casos_ticket_cx as cx', fn ($j) => $j->on('cx.caso_id', '=', 'c.id')->on('cx.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('casos_lead_venta as cv', fn ($j) => $j->on('cv.caso_id', '=', 'c.id')->on('cv.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('casos_servicio as cs', fn ($j) => $j->on('cs.caso_id', '=', 'c.id')->on('cs.proyecto_id', '=', 'c.proyecto_id'))
            ->join('carteras as ca', fn ($j) => $j->on('ca.id', '=', 'c.cartera_id')->on('ca.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('estados_caso as ec', fn ($j) => $j->on('ec.id', '=', 'c.estado_caso_id')->on('ec.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('asignaciones as a', fn ($j) => $j->on('a.caso_id', '=', 'c.id')->on('a.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('users as asesor', 'asesor.id', '=', 'a.usuario_id')
            ->where(fn (Builder $q) => $q->where('ca.activo', false)->orWhereNotNull('ca.eliminada_en')->orWhereNotNull('c.eliminada_en')->orWhereNotNull('p.eliminada_en'))
            ->select($this->columnasPersona())
            ->selectRaw("CONCAT('c-', LPAD(c.id, 20, '0')) as cursor_id, NULL as archivo, ca.id as cartera_id, ca.public_id as cartera_public_id, ca.nombre as cartera_nombre")
            ->selectRaw('COALESCE(cc.numero_prestamo, cx.codigo_ticket, cv.codigo_lead, cs.codigo_servicio, c.public_id) as referencia, cc.moneda, cc.saldo_total, cc.dias_mora, ec.nombre as estado_nombre')
            ->selectRaw("c.fecha_ingreso, COALESCE(c.eliminada_en, ca.eliminada_en, p.eliminada_en, ca.actualizada_en) as fecha_archivo, asesor.name as asesor_nombre, CASE WHEN ca.eliminada_en IS NOT NULL THEN 'Cartera eliminada' WHEN ca.activo = 0 THEN 'Cartera desactivada' ELSE 'Cuenta archivada' END as motivo")
            ->selectRaw($this->limiteAnterior('last_gestion_id').' as gestion_desde, NULL as gestion_hasta, '.$this->limiteAnterior('last_compromiso_id').' as compromiso_desde, NULL as compromiso_hasta');

        $traslados = $this->base($proyectoId)
            ->join('caso_cartera_movimientos as m', fn ($j) => $j->on('m.caso_id', '=', 'c.id')->on('m.proyecto_id', '=', 'c.proyecto_id'))
            ->join('carteras as ca', fn ($j) => $j->on('ca.id', '=', 'm.cartera_origen_id')->on('ca.proyecto_id', '=', 'm.proyecto_id'))
            ->select($this->columnasPersona())
            ->selectRaw("CONCAT('m-', LPAD(m.id, 20, '0')) as cursor_id, m.public_id as archivo, ca.id as cartera_id, ca.public_id as cartera_public_id")
            ->selectRaw('COALESCE('.$this->snapshot('cartera_nombre').', ca.nombre) as cartera_nombre, COALESCE('.$this->snapshot('referencia').', c.public_id) as referencia')
            ->selectRaw($this->snapshot('moneda').' as moneda, '.$this->snapshot('saldo_total').' as saldo_total, '.$this->snapshot('dias_mora').' as dias_mora, '.$this->snapshot('estado_caso_nombre').' as estado_nombre')
            ->selectRaw($this->snapshot('fecha_ingreso').' as fecha_ingreso, m.trasladada_en as fecha_archivo, '.$this->snapshot('asesor_nombre')." as asesor_nombre, 'Reincorporada en otra cartera' as motivo")
            ->selectRaw($this->limiteAnterior('last_gestion_id', true).' as gestion_desde, COALESCE('.$this->snapshot('last_gestion_id').', 0) as gestion_hasta, '.$this->limiteAnterior('last_compromiso_id', true).' as compromiso_desde, COALESCE('.$this->snapshot('last_compromiso_id').', 0) as compromiso_hasta');

        return $this->db->table('casos')->fromSub($actuales->unionAll($traslados), 'h')
            ->when($carteras !== null, fn (Builder $q) => $q->whereIn('h.cartera_id', $carteras ?? []))
            ->when($cartera !== '', fn (Builder $q) => $q->where('h.cartera_public_id', $cartera))
            ->when($caso !== '', fn (Builder $q) => $q->where('h.caso_public_id', $caso))
            ->when($archivo !== '', fn (Builder $q) => $q->where('h.archivo', $archivo))
            ->when(trim($busqueda) !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($busqueda): void {
                $like = '%'.trim($busqueda).'%';
                $w->where('h.identificacion', 'like', $like)->orWhere('h.nombres', 'like', $like)
                    ->orWhere('h.apellidos', 'like', $like)->orWhere('h.razon_social', 'like', $like)
                    ->orWhere('h.referencia', 'like', $like);
            }));
    }

    /** @param list<int>|null $carteras */
    public function ficha(int $proyectoId, ?array $carteras, string $caso, string $archivo): ?stdClass
    {
        $q = $this->cuentas($proyectoId, $carteras, caso: $caso, archivo: $archivo);
        if ($archivo === '') {
            $q->whereNull('h.archivo');
        }

        return $q->first();
    }

    public function gestiones(Builder $cuentas, int $proyectoId): Builder
    {
        return $this->db->table('gestiones as g')
            ->joinSub($cuentas, 'h', fn ($j) => $j->on('h.caso_id', '=', 'g.caso_id')->on('h.proyecto_id', '=', 'g.proyecto_id'))
            ->leftJoin('resultados as r', fn ($j) => $j->on('r.id', '=', 'g.resultado_id')->on('r.proyecto_id', '=', 'g.proyecto_id'))
            ->leftJoin('tipos_gestion as tg', fn ($j) => $j->on('tg.id', '=', 'g.tipo_gestion_id')->on('tg.proyecto_id', '=', 'g.proyecto_id'))
            ->leftJoin('canales as cn', 'cn.id', '=', 'g.canal_id')
            ->leftJoin('users as u', 'u.id', '=', 'g.usuario_id')
            ->where('g.proyecto_id', $proyectoId)->whereNull('g.eliminada_en')
            ->whereColumn('g.id', '>', 'h.gestion_desde')
            ->where(fn (Builder $q) => $q->whereNull('h.gestion_hasta')->orWhereColumn('g.id', '<=', 'h.gestion_hasta'))
            ->select(['h.*', 'g.id as registro_id', 'g.public_id as gestion_public_id', 'g.creada_en', 'g.notas',
                'g.duracion_segundos', 'r.nombre as resultado_nombre', 'tg.nombre as tipo_nombre', 'cn.nombre as canal_nombre', 'u.name as autor_nombre'])
            ->selectRaw("CONCAT(h.cursor_id, '-', LPAD(g.id, 20, '0')) as export_id");
    }

    public function compromisos(Builder $cuentas, int $proyectoId): Builder
    {
        return $this->db->table('compromisos as co')
            ->joinSub($cuentas, 'h', fn ($j) => $j->on('h.caso_id', '=', 'co.caso_id')->on('h.proyecto_id', '=', 'co.proyecto_id'))
            ->leftJoin('compromisos_promesa_pago as pp', fn ($j) => $j->on('pp.compromiso_id', '=', 'co.id')->on('pp.proyecto_id', '=', 'co.proyecto_id'))
            ->leftJoin('compromisos_cierre_venta as cv', fn ($j) => $j->on('cv.compromiso_id', '=', 'co.id')->on('cv.proyecto_id', '=', 'co.proyecto_id'))
            ->leftJoin('compromisos_resolucion_ticket as rt', fn ($j) => $j->on('rt.compromiso_id', '=', 'co.id')->on('rt.proyecto_id', '=', 'co.proyecto_id'))
            ->leftJoin('compromisos_accion_servicio as sa', fn ($j) => $j->on('sa.compromiso_id', '=', 'co.id')->on('sa.proyecto_id', '=', 'co.proyecto_id'))
            ->leftJoin('users as u', 'u.id', '=', 'co.usuario_id')
            ->where('co.proyecto_id', $proyectoId)->whereNull('co.eliminada_en')
            ->whereColumn('co.id', '>', 'h.compromiso_desde')
            ->where(fn (Builder $q) => $q->whereNull('h.compromiso_hasta')->orWhereColumn('co.id', '<=', 'h.compromiso_hasta'))
            ->select(['h.*', 'co.id as registro_id', 'co.public_id as compromiso_public_id', 'co.tipo_compromiso', 'co.estado',
                'co.fecha_vencimiento', 'co.fecha_resolucion', 'co.creada_en', 'u.name as autor_nombre'])
            ->selectRaw('COALESCE(pp.monto, cv.monto_cierre) as monto_compromiso, COALESCE(pp.moneda, cv.moneda) as moneda_compromiso, COALESCE(rt.accion_comprometida, sa.descripcion_accion) as detalle')
            ->selectRaw("CONCAT(h.cursor_id, '-', LPAD(co.id, 20, '0')) as export_id");
    }

    private function base(int $proyectoId): Builder
    {
        return $this->db->table('casos as c')
            ->join('personas as p', fn ($j) => $j->on('p.id', '=', 'c.persona_id')->on('p.proyecto_id', '=', 'c.proyecto_id'))
            ->where('c.proyecto_id', $proyectoId);
    }

    /** @return list<string> */
    private function columnasPersona(): array
    {
        return ['c.proyecto_id', 'c.id as caso_id', 'c.public_id as caso_public_id', 'c.tipo_caso',
            'p.identificacion', 'p.nombres', 'p.apellidos', 'p.razon_social', 'p.tipo_persona'];
    }

    private function snapshot(string $key): string
    {
        return "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(m.instantanea, '$.".$key."')), 'null')";
    }

    private function limiteAnterior(string $key, bool $traslado = false): string
    {
        return "COALESCE((SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(anterior.instantanea, '$.".$key."')) AS UNSIGNED) FROM caso_cartera_movimientos anterior WHERE anterior.proyecto_id = c.proyecto_id AND anterior.caso_id = c.id"
            .($traslado ? ' AND anterior.id < m.id' : '').' ORDER BY anterior.id DESC LIMIT 1), 0)';
    }
}
