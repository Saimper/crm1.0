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
     * @return Expression<string>
     */
    public function totalCasos(): Expression
    {
        return $this->db->raw(
            '(select count(*) from casos c where c.persona_id = p.id and c.eliminada_en is null) as total_casos'
        );
    }
}
