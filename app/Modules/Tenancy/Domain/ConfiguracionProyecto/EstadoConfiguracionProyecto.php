<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\ConfiguracionProyecto;

enum EstadoConfiguracionProyecto: string
{
    case BORRADOR = 'borrador';
    case EN_PROGRESO = 'en_progreso';
    case COMPLETADA = 'completada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BORRADOR => 'Borrador',
            self::EN_PROGRESO => 'En progreso',
            self::COMPLETADA => 'Completada',
        };
    }
}
