<?php

declare(strict_types=1);

namespace Database\Seeders\Cx;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Personas demo del proyecto CX. Una de ellas usa deliberadamente la misma identificación
 * que una persona del proyecto de cobranza para validar aislamiento (§2.1 CLAUDE.md v2).
 */
final class PersonasCxDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $tipoRuc = (int) DB::table('tipos_identificacion')->where('codigo', 'RUC')->value('id');

        $filas = [
            ['tipo_persona' => 'fisica',   'tipo_identificacion_id' => $tipoCed, 'identificacion' => '0102030405',    'nombres' => 'Juan',    'apellidos' => 'Pérez'],
            ['tipo_persona' => 'fisica',   'tipo_identificacion_id' => $tipoCed, 'identificacion' => '0405060708',    'nombres' => 'Laura',   'apellidos' => 'Mendoza'],
            ['tipo_persona' => 'fisica',   'tipo_identificacion_id' => $tipoCed, 'identificacion' => '0506070809',    'nombres' => 'Pedro',   'apellidos' => 'Sánchez'],
            ['tipo_persona' => 'juridica', 'tipo_identificacion_id' => $tipoRuc, 'identificacion' => '0990123456789', 'razon_social' => 'Soluciones Cloud C.A.'],
        ];

        foreach ($filas as $f) {
            $yaExiste = DB::table('personas')
                ->where('proyecto_id', $proyectoId)
                ->where('tipo_identificacion_id', $f['tipo_identificacion_id'])
                ->where('identificacion', $f['identificacion'])
                ->exists();
            if ($yaExiste) {
                continue;
            }

            DB::table('personas')->insert([
                'public_id' => (string) Str::ulid(),
                'proyecto_id' => $proyectoId,
                'tipo_persona' => $f['tipo_persona'],
                'tipo_identificacion_id' => $f['tipo_identificacion_id'],
                'identificacion' => $f['identificacion'],
                'nombres' => $f['nombres'] ?? null,
                'apellidos' => $f['apellidos'] ?? null,
                'razon_social' => $f['razon_social'] ?? null,
            ]);
        }
    }
}
