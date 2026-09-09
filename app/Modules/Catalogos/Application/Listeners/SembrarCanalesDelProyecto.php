<?php

declare(strict_types=1);

namespace App\Modules\Catalogos\Application\Listeners;

use App\Modules\Tenancy\Domain\Events\ProyectoCreado;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Al crear un proyecto, darle todos los canales globales activos.
 *
 * Sin esto, un proyecto nuevo nace sin ningún canal y no se puede registrar una
 * gestión: la cascada empieza ahí. El administrador desactiva luego los que no
 * use y renombra los que quiera. Mismo patrón que los estados de caso por
 * defecto, que ya existía.
 */
final class SembrarCanalesDelProyecto
{
    public function handle(ProyectoCreado $evento): void
    {
        $yaTiene = DB::table('canal_proyecto')
            ->where('proyecto_id', $evento->proyectoId)
            ->exists();

        if ($yaTiene) {
            return;
        }

        $ahora = CarbonImmutable::now();

        $filas = DB::table('canales')
            ->where('activo', true)
            ->orderBy('orden')
            ->get(['id', 'orden'])
            ->map(fn (object $canal): array => [
                'proyecto_id' => $evento->proyectoId,
                'canal_id' => $canal->id,
                'etiqueta' => null,
                'activo' => true,
                'orden' => (int) $canal->orden,
                'requiere_duracion' => false,
                'permite_adjunto' => false,
                'creada_en' => $ahora,
                'actualizada_en' => $ahora,
            ])
            ->all();

        if ($filas !== []) {
            DB::table('canal_proyecto')->insert($filas);
        }
    }
}
