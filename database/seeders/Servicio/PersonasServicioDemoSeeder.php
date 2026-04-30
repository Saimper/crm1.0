<?php

declare(strict_types=1);

namespace Database\Seeders\Servicio;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PersonasServicioDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SERVICIO_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $tipoRuc = (int) DB::table('tipos_identificacion')->where('codigo', 'RUC')->value('id');

        $filas = [
            ['tipo_persona' => 'fisica',   'tipo_identificacion_id' => $tipoCed, 'identificacion' => '0910111213',    'nombres' => 'Andrés',   'apellidos' => 'Vargas'],
            ['tipo_persona' => 'fisica',   'tipo_identificacion_id' => $tipoCed, 'identificacion' => '1011121314',    'nombres' => 'Gabriela', 'apellidos' => 'Molina'],
            ['tipo_persona' => 'fisica',   'tipo_identificacion_id' => $tipoCed, 'identificacion' => '1112131415',    'nombres' => 'Miguel',   'apellidos' => 'Castro'],
            ['tipo_persona' => 'juridica', 'tipo_identificacion_id' => $tipoRuc, 'identificacion' => '1795678901001', 'razon_social' => 'Editorial Andes S.A.'],
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
