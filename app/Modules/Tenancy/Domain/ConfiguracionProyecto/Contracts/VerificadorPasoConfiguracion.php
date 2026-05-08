<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\ConfiguracionProyecto\Contracts;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;

interface VerificadorPasoConfiguracion
{
    public function paso(): PasoConfiguracion;

    public function estaCompletoParaProyecto(int $proyectoId): bool;
}
