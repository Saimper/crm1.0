<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AsignacionesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = (int) DB::table('users')->where('email', 'admin@crm.local')->value('id');
        if ($adminId === 0) {
            return;
        }

        // Campaña demo
        $campanaId = (int) DB::table('campanas')->where('codigo', 'CAMP-DEMO-ABR-2026')->value('id');
        if ($campanaId === 0) {
            $campanaId = (int) DB::table('campanas')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'codigo' => 'CAMP-DEMO-ABR-2026',
                'nombre' => 'Cartera vencida abril 2026',
                'descripcion' => 'Campaña demo de cobranza temprana sobre cartera en mora.',
                'fecha_inicio' => '2026-04-01',
                'fecha_fin' => '2026-04-30',
                'estado' => 'activa',
                'creada_por_id' => $adminId,
            ]);
        }

        // Asignar los productos existentes al admin
        $productos = DB::table('productos')->whereNull('eliminada_en')->get();

        foreach ($productos as $p) {
            $existe = DB::table('asignaciones')
                ->where('campana_id', $campanaId)
                ->where('producto_id', $p->id)
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('asignaciones')->insert([
                'public_id' => (string) Str::ulid(),
                'campana_id' => $campanaId,
                'producto_id' => $p->id,
                'usuario_id' => $adminId,
                'fecha_asignacion' => '2026-04-17',
                'prioridad' => max(10, 200 - (int) $p->dias_mora),
                'estado' => 'pendiente',
            ]);
        }
    }
}
