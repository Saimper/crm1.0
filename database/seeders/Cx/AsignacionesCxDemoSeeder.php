<?php

declare(strict_types=1);

namespace Database\Seeders\Cx;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Asigna los tickets CX al gestor demo dentro de la campaña CX.
 */
final class AsignacionesCxDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $campanaId = (int) DB::table('campanas')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'SOPORTE_DEMO_ABR_2026')->value('id');
        $gestorId = (int) DB::table('users')->where('email', 'gestor.demo@crm.local')->value('id');

        if ($campanaId === 0 || $gestorId === 0) {
            return;
        }

        $casos = DB::table('casos')->where('proyecto_id', $proyectoId)->pluck('id');
        foreach ($casos as $casoId) {
            $existe = DB::table('asignaciones')
                ->where('campana_id', $campanaId)
                ->where('caso_id', $casoId)
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('asignaciones')->insert([
                'public_id'        => (string) Str::ulid(),
                'proyecto_id'      => $proyectoId,
                'campana_id'       => $campanaId,
                'caso_id'          => $casoId,
                'usuario_id'       => $gestorId,
                'fecha_asignacion' => '2026-04-18',
                'prioridad'        => 100,
                'estado'           => 'pendiente',
            ]);
        }
    }
}
