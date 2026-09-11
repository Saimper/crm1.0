<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/** Portfolio availability for operational reads; historical records remain intact. */
final class CarterasOperativas
{
    /** @param list<int>|null $carterasPermitidas */
    public static function filtrar(Builder $query, string $casoAlias = 'c', ?array $carterasPermitidas = null): Builder
    {
        return $query->whereExists(fn (Builder $q) => $q->selectRaw('1')
            ->from('carteras as cartera_operativa')
            ->whereColumn('cartera_operativa.id', $casoAlias.'.cartera_id')
            ->whereColumn('cartera_operativa.proyecto_id', $casoAlias.'.proyecto_id')
            ->where('cartera_operativa.activo', true)
            ->whereNull('cartera_operativa.eliminada_en'))
            ->when($carterasPermitidas !== null, fn (Builder $q) => $q->whereIn($casoAlias.'.cartera_id', $carterasPermitidas));
    }

    public static function exigirCartera(ConnectionInterface $db, int $proyectoId, int $carteraId): void
    {
        if (! self::carteraDisponible($db, $proyectoId, $carteraId)) {
            throw new \DomainException('La cartera no está activa en este proyecto.');
        }
    }

    public static function carteraDisponible(ConnectionInterface $db, int $proyectoId, int $carteraId): bool
    {
        return $db->table('carteras')->where('proyecto_id', $proyectoId)->where('id', $carteraId)
            ->where('activo', true)->whereNull('eliminada_en')->exists();
    }

    /** Reject operational writes after any level of the account hierarchy is archived. */
    public static function exigirCaso(ConnectionInterface $db, int $proyectoId, int $casoId): object
    {
        $case = self::casos($db, $proyectoId)
            ->join('proyectos as operational_project', 'operational_project.id', '=', 'c.proyecto_id')
            ->join('mandantes as operational_client', 'operational_client.id', '=', 'operational_project.mandante_id')
            ->join('personas as operational_person', 'operational_person.id', '=', 'c.persona_id')
            ->where('c.id', $casoId)
            ->where('operational_project.activo', true)->whereNull('operational_project.eliminada_en')
            ->where('operational_client.activo', true)->whereNull('operational_client.eliminada_en')
            ->where('operational_person.proyecto_id', $proyectoId)->whereNull('operational_person.eliminada_en')
            ->select('c.*')->first();
        if ($case === null) {
            throw new \DomainException('La cuenta está archivada o su cartera no está activa. Consulta el Histórico.');
        }

        return $case;
    }

    /** @param list<int>|null $carterasPermitidas */
    public static function casos(ConnectionInterface $db, int $proyectoId, ?array $carterasPermitidas = null): Builder
    {
        return self::filtrar($db->table('casos as c'), 'c', $carterasPermitidas)
            ->where('c.proyecto_id', $proyectoId)->whereNull('c.eliminada_en');
    }

    /**
     * Filter operational rows through their account without changing result cardinality.
     *
     * @param  list<int>|null  $carterasPermitidas
     */
    public static function filtrarVinculados(Builder $query, string $alias, string $foreignKey = 'caso_id', ?array $carterasPermitidas = null): Builder
    {
        return $query->whereExists(fn (Builder $case) => self::filtrar(
            $case->selectRaw('1')->from('casos as caso_vinculado')
                ->whereColumn('caso_vinculado.id', $alias.'.'.$foreignKey)
                ->whereColumn('caso_vinculado.proyecto_id', $alias.'.proyecto_id')
                ->whereNull('caso_vinculado.eliminada_en'),
            'caso_vinculado',
            $carterasPermitidas,
        ));
    }
}
