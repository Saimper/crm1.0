<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Configuracion\Verificadores;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\Contracts\VerificadorPasoConfiguracion;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use Illuminate\Support\Facades\DB;

final class ResultadosVerificador implements VerificadorPasoConfiguracion
{
    public function paso(): PasoConfiguracion
    {
        return PasoConfiguracion::RESULTADOS;
    }

    public function estaCompletoParaProyecto(int $proyectoId): bool
    {
        return DB::table('resultados')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->exists();
    }
}
