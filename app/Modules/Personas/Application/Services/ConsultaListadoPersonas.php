<?php

declare(strict_types=1);

namespace App\Modules\Personas\Application\Services;

use App\Modules\Personas\Application\DTOs\FiltrosListadoPersonas;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;

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

    /** `personas as p`, ya recortada al proyecto y sin bajas lógicas. */
    public function consultaBase(int $proyectoId): Builder
    {
        return $this->db->table('personas as p')
            ->leftJoin('tipos_identificacion as ti', 'ti.id', '=', 'p.tipo_identificacion_id')
            ->where('p.proyecto_id', $proyectoId)
            ->whereNull('p.eliminada_en');
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
     * `EXISTS` y no `IN (SELECT persona_id FROM casos ...)`: medido sobre el
     * proyecto 8 de la base de desarrollo (8.428 personas, 8.676 casos), el
     * EXISTS conduce por `personas` con semi-join FirstMatch y tarda 46 ms,
     * mientras que el IN conduce por `casos`, monta tabla temporal y tarda 90.
     * El filesort que aparece en las dos es del `ORDER BY creada_en` del
     * listado, no del recorte.
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
                ->whereNull('cr.eliminada_en')
                ->whereIn('cr.cartera_id', $carterasPermitidas));
        }

        return $q;
    }

    public function aplicarFiltros(Builder $q, FiltrosListadoPersonas $filtros): Builder
    {
        if ($filtros->busqueda !== '') {
            $like = '%'.$filtros->busqueda.'%';
            $q->where(function (Builder $w) use ($like): void {
                $w->where('p.identificacion', 'like', $like)
                    ->orWhere('p.nombres', 'like', $like)
                    ->orWhere('p.apellidos', 'like', $like)
                    ->orWhere('p.razon_social', 'like', $like);
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
            '(select count(*) from casos c where c.persona_id = p.id and c.eliminada_en is null'.$recorte.') as total_casos'
        );
    }
}
