<?php

declare(strict_types=1);

namespace App\Modules\Casos\Application\Services;

use App\Modules\Casos\Application\DTOs\FiltrosListadoCasos;
use App\Modules\Casos\Domain\Columnas\CatalogoColumnasCaso;
use App\Support\Database\CarterasOperativas;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * La consulta del listado de casos, compartida por la pantalla y el CSV.
 *
 * El recorte por proyecto va en la base y los filtros sólo estrechan: un
 * `?cartera=` con el id de una cartera de otro proyecto devuelve vacío, nunca
 * la cartera ajena. Alias fijos —`c` casos, `p` personas, `ca` carteras, `ec`
 * estados, `cti` la tabla del tipo— porque las expresiones del catálogo de
 * columnas los dan por hechos.
 */
final readonly class ConsultaListadoCasos
{
    public function __construct(private ConnectionInterface $db) {}

    public function consultaBase(int $proyectoId, string $tipoOperacion): Builder
    {
        $q = $this->db->table('casos as c')
            ->join('personas as p', 'p.id', '=', 'c.persona_id')
            ->leftJoin('carteras as ca', 'ca.id', '=', 'c.cartera_id')
            ->leftJoin('estados_caso as ec', 'ec.id', '=', 'c.estado_caso_id')
            ->where('c.proyecto_id', $proyectoId)
            ->whereNull('c.eliminada_en')
            ->where(fn ($q) => CarterasOperativas::filtrar($q));

        $tablaCti = CatalogoColumnasCaso::tablaCti($tipoOperacion);
        if ($tablaCti !== null) {
            $q->leftJoin($tablaCti.' as cti', 'cti.caso_id', '=', 'c.id');
        }

        return $q;
    }

    /**
     * El límite por cartera del rol del usuario (F22), ANTES de cualquier
     * filtro. `null` es «sin límite»; una lista vacía no deja ver nada, que es
     * lo correcto para un rol restringido cuyas carteras ya no existen.
     *
     * @param  list<int>|null  $carterasPermitidas  Lo que devuelve `User::carterasPermitidas()`.
     */
    public function recortarACarteras(Builder $q, ?array $carterasPermitidas): Builder
    {
        if ($carterasPermitidas !== null) {
            $q->whereIn('c.cartera_id', $carterasPermitidas);
        }

        return $q;
    }

    public function aplicarFiltros(Builder $q, FiltrosListadoCasos $filtros): Builder
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

        if ($filtros->carteraId !== null) {
            $q->where('c.cartera_id', $filtros->carteraId);
        }

        if ($filtros->estadoCasoId !== null) {
            $q->where('c.estado_caso_id', $filtros->estadoCasoId);
        }

        return $q;
    }
}
