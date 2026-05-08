<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Configuracion\Verificadores;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\Contracts\VerificadorPasoConfiguracion;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use Illuminate\Support\Facades\DB;

final class DatosProyectoVerificador implements VerificadorPasoConfiguracion
{
    public function paso(): PasoConfiguracion
    {
        return PasoConfiguracion::DATOS_PROYECTO;
    }

    public function estaCompletoParaProyecto(int $proyectoId): bool
    {
        $row = DB::table('proyectos')
            ->whereNull('eliminada_en')
            ->where('id', $proyectoId)
            ->select(['nombre', 'tipo_operacion'])
            ->first();

        if ($row === null) {
            return false;
        }

        return trim((string) $row->nombre) !== '' && trim((string) $row->tipo_operacion) !== '';
    }
}
