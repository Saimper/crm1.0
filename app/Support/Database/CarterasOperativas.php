<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/** Portfolio availability for operational reads; historical records remain intact. */
final class CarterasOperativas
{
    public static function filtrar(Builder $query, string $casoAlias = 'c'): Builder
    {
        return $query->whereExists(fn (Builder $q) => $q->selectRaw('1')
            ->from('carteras as cartera_operativa')
            ->whereColumn('cartera_operativa.id', $casoAlias.'.cartera_id')
            ->whereColumn('cartera_operativa.proyecto_id', $casoAlias.'.proyecto_id')
            ->where('cartera_operativa.activo', true)
            ->whereNull('cartera_operativa.eliminada_en'));
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

    public static function casos(ConnectionInterface $db, int $proyectoId): Builder
    {
        return self::filtrar($db->table('casos as c'))
            ->where('c.proyecto_id', $proyectoId)->whereNull('c.eliminada_en');
    }
}
