<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\Console\Commands;

use App\Modules\Integracion\Domain\Contracts\RepositorioTokensConsumidos;
use DateTimeImmutable;
use Illuminate\Console\Command;

final class PurgarSsoTokensConsumidosCommand extends Command
{
    protected $signature = 'integracion:purgar-sso-consumidos';

    protected $description = 'Elimina filas de sso_tokens_consumidos cuyo expira_en ya pasó. Idempotente.';

    public function handle(RepositorioTokensConsumidos $repositorio): int
    {
        $borrados = $repositorio->purgarExpirados(new DateTimeImmutable('now'));

        $this->info("Tokens SSO consumidos purgados: {$borrados}.");

        return self::SUCCESS;
    }
}
