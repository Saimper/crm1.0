<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Persistence\Repositories;

use App\Modules\Integracion\Domain\Contracts\RepositorioTokensConsumidos;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

final class RepositorioTokensConsumidosEloquent implements RepositorioTokensConsumidos
{
    private const TABLA = 'sso_tokens_consumidos';

    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    public function fueConsumido(string $jti): bool
    {
        return $this->db->table(self::TABLA)->where('jti', $jti)->exists();
    }

    public function registrarConsumo(string $jti, int $proyectoId, DateTimeImmutable $expiraEn): void
    {
        $this->db->table(self::TABLA)->insert([
            'jti' => $jti,
            'proyecto_id' => $proyectoId,
            'consumido_en' => Carbon::now(),
            'expira_en' => Carbon::createFromTimestamp($expiraEn->getTimestamp()),
        ]);
    }

    public function purgarExpirados(DateTimeImmutable $hasta): int
    {
        return $this->db->table(self::TABLA)
            ->where('expira_en', '<', Carbon::createFromTimestamp($hasta->getTimestamp()))
            ->delete();
    }
}
