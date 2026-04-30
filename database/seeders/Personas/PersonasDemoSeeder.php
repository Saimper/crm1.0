<?php

declare(strict_types=1);

namespace Database\Seeders\Personas;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PersonasDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $tipoRuc = (int) DB::table('tipos_identificacion')->where('codigo', 'RUC')->value('id');

        $filas = [
            ['tipo_persona' => 'fisica',   'tipo_identificacion_id' => $tipoCed, 'identificacion' => '0102030405',    'nombres' => 'Juan',  'apellidos' => 'Pérez'],
            ['tipo_persona' => 'fisica',   'tipo_identificacion_id' => $tipoCed, 'identificacion' => '0203040506',    'nombres' => 'María', 'apellidos' => 'González'],
            ['tipo_persona' => 'juridica', 'tipo_identificacion_id' => $tipoRuc, 'identificacion' => '1792345678001', 'razon_social' => 'Comercial Austral S.A.'],
            ['tipo_persona' => 'fisica',   'tipo_identificacion_id' => $tipoCed, 'identificacion' => '0304050607',    'nombres' => 'Carlos', 'apellidos' => 'Ramírez'],
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
