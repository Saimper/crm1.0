<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\Contracts;

use App\Modules\Tenancy\Domain\ValueObjects\RegionalSettings;

interface RegionalConfiguration
{
    public function forProject(?int $projectId = null): RegionalSettings;

    public function forMandante(?int $mandanteId): RegionalSettings;

    public function clear(): void;
}
