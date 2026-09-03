<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Elimina importaciones que nunca se lanzaron (pendiente/preparada) y superan
 * la antigüedad indicada. Las filas y el pivote de campos personalizados caen
 * por FK cascade. Las terminadas (completada/fallida/cancelada) se conservan
 * como historial.
 */
final readonly class PurgarImportacionesObsoletas
{
    public function __construct(private ConnectionInterface $db) {}

    public function execute(int $diasAntiguedad): int
    {
        $limite = CarbonImmutable::now()->subDays(max(1, $diasAntiguedad));

        return $this->db->table('importaciones')
            ->whereIn('estado', [EstadoImportacion::PENDIENTE->value, EstadoImportacion::PREPARADA->value])
            ->where('creada_en', '<', $limite)
            ->delete();
    }
}
