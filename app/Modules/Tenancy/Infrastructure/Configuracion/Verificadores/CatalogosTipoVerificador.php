<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Configuracion\Verificadores;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\Contracts\VerificadorPasoConfiguracion;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use Illuminate\Support\Facades\DB;

/**
 * Verifica que cada catálogo tipo-específico declarado para el proyecto
 * tenga al menos una fila. Las tablas a verificar provienen de
 * PasoConfiguracion::subPasosCatalogosPorTipo() (whitelist server-side).
 */
final class CatalogosTipoVerificador implements VerificadorPasoConfiguracion
{
    public function paso(): PasoConfiguracion
    {
        return PasoConfiguracion::CATALOGOS_TIPO;
    }

    public function estaCompletoParaProyecto(int $proyectoId): bool
    {
        $tipoRaw = DB::table('proyectos')
            ->whereNull('eliminada_en')
            ->where('id', $proyectoId)
            ->value('tipo_operacion');

        if (! is_string($tipoRaw)) {
            return false;
        }

        $tipo = TipoOperacion::tryFrom($tipoRaw);
        if ($tipo === null) {
            return false;
        }

        $tablas = PasoConfiguracion::subPasosCatalogosPorTipo($tipo);

        if ($tablas === []) {
            return true;
        }

        foreach ($tablas as $tabla) {
            $tieneFila = DB::table($tabla)
                ->where('proyecto_id', $proyectoId)
                ->exists();

            if (! $tieneFila) {
                return false;
            }
        }

        return true;
    }
}
