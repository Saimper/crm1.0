<?php

declare(strict_types=1);

namespace Database\Seeders\Tenancy;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CarterasDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->sembrarParaProyecto('COBRANZA_DEMO_2026', [
            ['codigo' => 'CONSUMO',      'nombre' => 'Consumo',      'descripcion' => 'Cartera de consumo (demo)'],
            ['codigo' => 'MICROEMPRESA', 'nombre' => 'Microempresa', 'descripcion' => 'Cartera de microempresa (demo)'],
        ]);

        $this->sembrarParaProyecto('SOPORTE_DEMO_2026', [
            ['codigo' => 'SOPORTE_GENERAL',  'nombre' => 'Soporte general',  'descripcion' => 'Tickets generales (demo CX)'],
            ['codigo' => 'SOPORTE_TECNICO',  'nombre' => 'Soporte técnico',  'descripcion' => 'Incidencias técnicas (demo CX)'],
        ]);

        $this->sembrarParaProyecto('VENTA_DEMO_2026', [
            ['codigo' => 'PREMIUM', 'nombre' => 'Cartera Premium', 'descripcion' => 'Leads de alto valor (demo Venta)'],
            ['codigo' => 'MASIVO',  'nombre' => 'Cartera Masivo',  'descripcion' => 'Leads de venta masiva (demo Venta)'],
        ]);

        $this->sembrarParaProyecto('SERVICIO_DEMO_2026', [
            ['codigo' => 'RESIDENCIAL', 'nombre' => 'Residencial',           'descripcion' => 'Servicios a hogares (demo Servicio)'],
            ['codigo' => 'EMPRESAS',    'nombre' => 'Empresas / corporativo','descripcion' => 'Servicios a empresas (demo Servicio)'],
        ]);
    }

    /** @param array<int, array{codigo:string, nombre:string, descripcion:string}> $filas */
    private function sembrarParaProyecto(string $codigoProyecto, array $filas): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', $codigoProyecto)->value('id');
        if ($proyectoId === 0) {
            return;
        }

        foreach ($filas as $row) {
            $existe = DB::table('carteras')
                ->where('proyecto_id', $proyectoId)
                ->where('codigo', $row['codigo'])
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('carteras')->insert([
                'public_id'   => (string) Str::ulid(),
                'proyecto_id' => $proyectoId,
                'codigo'      => $row['codigo'],
                'nombre'      => $row['nombre'],
                'descripcion' => $row['descripcion'],
                'activo'      => true,
            ]);
        }
    }
}
