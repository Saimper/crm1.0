<?php

declare(strict_types=1);

namespace App\Modules\Catalogos\Application\Listeners;

use App\Modules\Tenancy\Domain\Events\ProyectoCreado;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F35-D: al crear un proyecto, sembrar un estado de caso `ABIERTO` por default
 * para que el flujo Crear Caso funcione sin requerir setup manual previo.
 *
 * El admin puede agregar/editar/desactivar estados después en
 * /admin/estados-caso. El estado `ABIERTO` no es especial: es solo el primer
 * activo por orden — si el admin lo desactiva y crea otros, el sistema usará
 * el primero activo encontrado.
 */
final class CrearEstadosCasoPorDefecto
{
    public function handle(ProyectoCreado $evento): void
    {
        $existe = DB::table('estados_caso')
            ->where('proyecto_id', $evento->proyectoId)
            ->exists();

        if ($existe) {
            return;
        }

        $ahora = CarbonImmutable::now();

        $defaults = [
            ['codigo' => 'ABIERTO', 'nombre' => 'Abierto', 'es_terminal' => false],
            ['codigo' => 'ASIGNADO', 'nombre' => 'Asignado', 'es_terminal' => false],
            ['codigo' => 'EN_PROGRESO', 'nombre' => 'En progreso', 'es_terminal' => false],
            ['codigo' => 'FINALIZADO', 'nombre' => 'Finalizado', 'es_terminal' => true],
        ];

        $filas = [];
        foreach ($defaults as $i => $estado) {
            $filas[] = [
                'proyecto_id' => $evento->proyectoId,
                'codigo' => $estado['codigo'],
                'nombre' => $estado['nombre'],
                'activo' => true,
                'es_terminal' => $estado['es_terminal'],
                'orden' => ($i + 1) * 10,
                'creada_en' => $ahora,
                'actualizada_en' => $ahora,
            ];
        }

        DB::table('estados_caso')->insert($filas);
    }
}
