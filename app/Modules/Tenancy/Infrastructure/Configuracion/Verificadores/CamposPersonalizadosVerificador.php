<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Configuracion\Verificadores;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\Contracts\VerificadorPasoConfiguracion;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use Illuminate\Support\Facades\DB;

/**
 * Paso opcional (esOpcional()=true). El cálculo de avance lo ignora,
 * pero el contrato del Calculador exige cobertura para los 9 pasos.
 * Marca completo si el proyecto tiene al menos un campo personalizado definido.
 */
final class CamposPersonalizadosVerificador implements VerificadorPasoConfiguracion
{
    public function paso(): PasoConfiguracion
    {
        return PasoConfiguracion::CAMPOS_PERSONALIZADOS;
    }

    public function estaCompletoParaProyecto(int $proyectoId): bool
    {
        return DB::table('campos_personalizados')
            ->where('proyecto_id', $proyectoId)
            ->exists();
    }
}
