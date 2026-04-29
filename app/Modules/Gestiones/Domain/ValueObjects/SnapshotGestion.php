<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Domain\ValueObjects;

use App\Modules\Productos\Domain\ValueObjects\DiasMora;
use InvalidArgumentException;

final readonly class SnapshotGestion
{
    public function __construct(
        public string $saldo,
        public DiasMora $diasMora,
    ) {
        if (preg_match('/^\d{1,13}(\.\d{1,2})?$/', $saldo) !== 1) {
            throw new InvalidArgumentException("Saldo con formato inválido: {$saldo}");
        }
    }
}
