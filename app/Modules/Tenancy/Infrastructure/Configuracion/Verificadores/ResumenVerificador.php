<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Configuracion\Verificadores;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\Contracts\VerificadorPasoConfiguracion;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;

/**
 * El paso final no requiere acción — es una pantalla de confirmación.
 * Siempre devuelve true para que el porcentaje refleje el progreso real
 * de los 7 pasos obligatorios anteriores cuando estén completos.
 */
final class ResumenVerificador implements VerificadorPasoConfiguracion
{
    public function paso(): PasoConfiguracion
    {
        return PasoConfiguracion::RESUMEN;
    }

    public function estaCompletoParaProyecto(int $proyectoId): bool
    {
        return true;
    }
}
