<?php

declare(strict_types=1);

namespace App\Modules\Personas\Application\Services;

use App\Modules\Personas\Application\DTOs\FiltrosListadoPersonas;
use App\Support\Database\CarterasOperativas;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;

/**
 * La consulta del listado de personas, compartida por la pantalla y el CSV.
 *
 * Una sola definición del recorte y de los filtros: si el listado enseña 120
 * personas con «q=perez», la descarga con «q=perez» trae esas 120. La cláusula
 * de proyecto va en la base y no en los filtros, para que ningún filtro pueda
 * ampliarla: sólo estrechan.
 */
final readonly class ConsultaListadoPersonas
{
    public function __construct(private ConnectionInterface $db) {}

    /** Live project identities with at least one operational account. */
    public function consultaBase(int $proyectoId): Builder
    {
        return $this->db->table('personas as p')
            ->leftJoin('tipos_identificacion as ti', 'ti.id', '=', 'p.tipo_identificacion_id')
            ->where('p.proyecto_id', $proyectoId)
            ->whereNull('p.eliminada_en')
            ->whereExists(fn (Builder $cases) => CarterasOperativas::filtrar($cases
                ->selectRaw('1')->from('casos as c')
                ->whereColumn('c.proyecto_id', 'p.proyecto_id')
                ->whereColumn('c.persona_id', 'p.id')
                ->whereNull('c.eliminada_en')));
    }

    /**
     * El límite por cartera del rol del usuario (F22), ANTES de los filtros.
     *
     * Una persona no pertenece a una cartera; sus casos sí. Así que un rol
     * acotado ve a quien tenga AL MENOS UN caso en sus carteras, y deja de ver
     * al resto, incluidas las personas sin ningún caso: de ésas no puede mirar
     * un solo dato operativo, y listarlas sería enseñar el padrón del cliente
     * por la puerta de atrás.
     *
     * `null` es «sin límite». Una lista vacía no deja ver a nadie, que es lo
     * correcto para un rol restringido a carteras que ya no existen.
     *
     * The same operational-portfolio predicate applies to rows and counts.
     * Historical-only people are available through the authorized history screen.
     *
     * @param  list<int>|null  $carterasPermitidas  Lo que devuelve `User::carterasPermitidas()`.
     */
    public function recortarACarteras(Builder $q, ?array $carterasPermitidas): Builder
    {
        if ($carterasPermitidas !== null) {
            $q->whereExists(fn (Builder $sub) => $sub
                ->select($this->db->raw('1'))
                ->from('casos as cr')
                ->whereColumn('cr.persona_id', 'p.id')
                ->whereColumn('cr.proyecto_id', 'p.proyecto_id')
                ->whereNull('cr.eliminada_en')
                ->whereIn('cr.cartera_id', $carterasPermitidas)
                ->where(fn (Builder $scope) => CarterasOperativas::filtrar($scope, 'cr')));
        }

        return $q;
    }

    /** @param list<int>|null $carterasPermitidas */
    public function aplicarFiltros(Builder $q, FiltrosListadoPersonas $filtros, ?array $carterasPermitidas = null): Builder
    {
        if ($filtros->busqueda !== '') {
            $like = '%'.$filtros->busqueda.'%';
            $q->where(function (Builder $w) use ($like, $carterasPermitidas): void {
                $w->where('p.identificacion', 'like', $like)
                    ->orWhere('p.nombres', 'like', $like)
                    ->orWhere('p.apellidos', 'like', $like)
                    ->orWhere('p.razon_social', 'like', $like)
                    ->orWhereExists(fn (Builder $account) => CarterasOperativas::filtrar($account
                        ->selectRaw('1')->from('casos as c')
                        ->leftJoin('casos_cobranza as cc', fn ($j) => $j->on('cc.caso_id', '=', 'c.id')->on('cc.proyecto_id', '=', 'c.proyecto_id'))
                        ->leftJoin('casos_ticket_cx as cx', fn ($j) => $j->on('cx.caso_id', '=', 'c.id')->on('cx.proyecto_id', '=', 'c.proyecto_id'))
                        ->leftJoin('casos_lead_venta as cv', fn ($j) => $j->on('cv.caso_id', '=', 'c.id')->on('cv.proyecto_id', '=', 'c.proyecto_id'))
                        ->leftJoin('casos_servicio as cs', fn ($j) => $j->on('cs.caso_id', '=', 'c.id')->on('cs.proyecto_id', '=', 'c.proyecto_id'))
                        ->whereColumn('c.persona_id', 'p.id')->whereColumn('c.proyecto_id', 'p.proyecto_id')
                        ->whereNull('c.eliminada_en')
                        ->when($carterasPermitidas !== null, fn (Builder $allowed) => $allowed->whereIn('c.cartera_id', $carterasPermitidas ?? []))
                        ->where(fn (Builder $reference) => $reference->where('cc.numero_prestamo', 'like', $like)
                            ->orWhere('cx.codigo_ticket', 'like', $like)->orWhere('cv.codigo_lead', 'like', $like)->orWhere('cs.codigo_servicio', 'like', $like))));
            });
        }

        if ($filtros->tipoPersona !== '') {
            $q->where('p.tipo_persona', $filtros->tipoPersona);
        }

        return $q;
    }

    /**
     * Cuántos casos vivos tiene cada persona, como subconsulta correlacionada.
     *
     * No es un JOIN con GROUP BY a propósito: la descarga pagina por `p.id`
     * (`chunkById`) y necesita la clave limpia en cada fila; con la agrupación
     * el `where p.id > último` y el `limit` se pelean con el `group by`.
     *
     * Cuenta sólo los casos de las carteras del rol: si contara todos, la fila
     * que el usuario sí puede ver le diría cuántos casos más tiene esa persona
     * en las carteras que no puede abrir, que es media fuga con otro nombre.
     *
     * @param  list<int>|null  $carterasPermitidas
     * @return Expression<string>
     */
    public function totalCasos(?array $carterasPermitidas = null): Expression
    {
        // Los ids son enteros que salen de la base, no del cliente: se
        // interpolan como literales porque una subconsulta cruda no admite
        // bindings sin desordenar los del resto de la consulta.
        $recorte = $carterasPermitidas === null
            ? ''
            : ' and c.cartera_id in ('.($carterasPermitidas === [] ? 'null' : implode(',', array_map('intval', $carterasPermitidas))).')';

        return $this->db->raw(
            '(select count(*) from casos c inner join carteras ca on ca.id = c.cartera_id and ca.proyecto_id = c.proyecto_id where c.proyecto_id = p.proyecto_id and c.persona_id = p.id and c.eliminada_en is null and ca.activo = 1 and ca.eliminada_en is null'.$recorte.') as total_casos'
        );
    }

    /**
     * The page batches all visible accounts, so siblings are identifiable without opening each account.
     *
     * @param  list<int>  $personas
     * @param  list<int>|null  $carteras
     * @return Collection<int, \stdClass>
     */
    public function cuentasDePersonas(int $proyectoId, array $personas, ?array $carteras): Collection
    {
        return CarterasOperativas::casos($this->db, $proyectoId)
            ->join('carteras as ca', fn ($join) => $join->on('ca.id', '=', 'c.cartera_id')->on('ca.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('casos_cobranza as cc', fn ($join) => $join->on('cc.caso_id', '=', 'c.id')->on('cc.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('casos_ticket_cx as cx', fn ($join) => $join->on('cx.caso_id', '=', 'c.id')->on('cx.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('casos_lead_venta as cv', fn ($join) => $join->on('cv.caso_id', '=', 'c.id')->on('cv.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('casos_servicio as cs', fn ($join) => $join->on('cs.caso_id', '=', 'c.id')->on('cs.proyecto_id', '=', 'c.proyecto_id'))
            ->leftJoin('estados_caso as ec', fn ($join) => $join->on('ec.id', '=', 'c.estado_caso_id')->on('ec.proyecto_id', '=', 'c.proyecto_id'))
            ->whereIn('c.persona_id', $personas)
            ->when($carteras !== null, fn (Builder $q) => $q->whereIn('c.cartera_id', $carteras ?? []))
            ->select(['c.id', 'c.public_id', 'c.persona_id', 'c.tipo_caso', 'ca.nombre as cartera_nombre',
                'cc.saldo_total', 'cc.moneda', 'cc.dias_mora', 'ec.nombre as estado_nombre'])
            ->selectRaw('COALESCE(cc.numero_prestamo, cx.codigo_ticket, cv.codigo_lead, cs.codigo_servicio, c.public_id) as referencia')
            ->orderBy('c.persona_id')->orderBy('c.id')->get();
    }
}
