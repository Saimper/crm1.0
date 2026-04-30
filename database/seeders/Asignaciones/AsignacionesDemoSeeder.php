<?php

declare(strict_types=1);

namespace Database\Seeders\Asignaciones;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Asigna los casos sembrados del proyecto cobranza demo a la campaña demo y al gestor demo.
 * Depende de CobranzaSeeder (casos creados) y CampanasSeeder (campaña creada).
 */
final class AsignacionesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $campanaId = (int) DB::table('campanas')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'COB_DEMO_ABR_2026')->value('id');
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
                'public_id' => (string) Str::ulid(),
                'proyecto_id' => $proyectoId,
                'campana_id' => $campanaId,
                'caso_id' => $casoId,
                'usuario_id' => $gestorId,
                'fecha_asignacion' => '2026-04-17',
                'prioridad' => 100,
                'estado' => 'pendiente',
            ]);
        }
    }
}
