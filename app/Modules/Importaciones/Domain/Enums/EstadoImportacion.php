<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Enums;

enum EstadoImportacion: string
{
    case PENDIENTE = 'pendiente';
    case PREPARADA = 'preparada';
    case PROCESANDO = 'procesando';
    case COMPLETADA = 'completada';
    case FALLIDA = 'fallida';
    case CANCELADA = 'cancelada';

    public function esTerminal(): bool
    {
        return match ($this) {
            self::COMPLETADA, self::FALLIDA, self::CANCELADA => true,
            default => false,
        };
    }

    public function puedeEncolarse(): bool
    {
        return $this === self::PREPARADA;
    }
}
