<?php

declare(strict_types=1);

namespace Database\Seeders\Venta;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CampanaVentaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'VENTA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $adminId = (int) DB::table('users')->where('email', 'admin@crm.local')->value('id');

        $codigo = 'VENTA_DEMO_ABR_2026';
        if (DB::table('campanas')->where('proyecto_id', $proyectoId)->where('codigo', $codigo)->exists()) {
            return;
        }

        DB::table('campanas')->insert([
            'public_id'     => (string) Str::ulid(),
            'proyecto_id'   => $proyectoId,
            'codigo'        => $codigo,
            'nombre'        => 'Venta outbound abril 2026',
            'descripcion'   => 'Campaña demo de venta outbound para validar el tipo Venta (Fase 4).',
            'estado'        => 'activa',
            'fecha_inicio'  => '2026-04-18',
            'fecha_fin'     => '2026-05-31',
            'creada_por_id' => $adminId > 0 ? $adminId : null,
        ]);
    }
}
