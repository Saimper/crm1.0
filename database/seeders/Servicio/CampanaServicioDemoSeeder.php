<?php

declare(strict_types=1);

namespace Database\Seeders\Servicio;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CampanaServicioDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SERVICIO_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $adminId = (int) DB::table('users')->where('email', 'admin@crm.local')->value('id');
        $codigo = 'SERVICIO_DEMO_ABR_2026';
        if (DB::table('campanas')->where('proyecto_id', $proyectoId)->where('codigo', $codigo)->exists()) {
            return;
        }

        DB::table('campanas')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'codigo' => $codigo,
            'nombre' => 'Servicio técnico abril 2026',
            'descripcion' => 'Campaña demo de servicio técnico para validar el tipo Servicio (Fase 5).',
            'estado' => 'activa',
            'fecha_inicio' => '2026-04-20',
            'fecha_fin' => '2026-05-31',
            'creada_por_id' => $adminId > 0 ? $adminId : null,
        ]);
    }
}
