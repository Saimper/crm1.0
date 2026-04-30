<?php

declare(strict_types=1);

namespace Database\Seeders\Cx;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CampanaCxDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $adminId = (int) DB::table('users')->where('email', 'admin@crm.local')->value('id');

        $codigo = 'SOPORTE_DEMO_ABR_2026';
        $existe = DB::table('campanas')
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', $codigo)
            ->exists();
        if ($existe) {
            return;
        }

        DB::table('campanas')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'codigo' => $codigo,
            'nombre' => 'Soporte demo abril 2026',
            'descripcion' => 'Campaña demo de atención al cliente para validar el tipo CX (Fase 3).',
            'estado' => 'activa',
            'fecha_inicio' => '2026-04-15',
            'fecha_fin' => '2026-05-15',
            'creada_por_id' => $adminId > 0 ? $adminId : null,
        ]);
    }
}
