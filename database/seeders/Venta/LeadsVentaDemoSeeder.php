<?php

declare(strict_types=1);

namespace Database\Seeders\Venta;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 4 leads demo (CTI: casos + casos_lead_venta) para el proyecto Venta Demo 2026.
 */
final class LeadsVentaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'VENTA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $carteraId = (int) DB::table('carteras')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'PREMIUM')->value('id');
        $estadoNuevoId = (int) DB::table('estados_caso')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'NUEVO')->value('id');

        if ($carteraId === 0 || $estadoNuevoId === 0) {
            return;
        }

        $personas = DB::table('personas')->where('proyecto_id', $proyectoId)->get()->keyBy('identificacion');

        $filas = [
            [
                'cedula' => '0607080910', // Sofía
                'codigo_lead' => 'LEAD-001',
                'producto' => 'TARJETA_PREMIUM',
                'etapa' => 'CALIFICACION',
                'valor' => '1200.00',
                'origen' => 'Campaña Google Ads',
                'fecha_primer' => '2026-04-15',
                'fecha_estimada' => '2026-05-15',
            ],
            [
                'cedula' => '0708091011', // Diego
                'codigo_lead' => 'LEAD-002',
                'producto' => 'SEGURO_AUTO',
                'etapa' => 'PROPUESTA',
                'valor' => '850.00',
                'origen' => 'Referido cliente',
                'fecha_primer' => '2026-04-12',
                'fecha_estimada' => '2026-05-01',
            ],
            [
                'cedula' => '0809101112', // Valeria
                'codigo_lead' => 'LEAD-003',
                'producto' => 'PLAN_AHORRO',
                'etapa' => 'PROSPECCION',
                'valor' => '300.00',
                'origen' => 'Landing page',
                'fecha_primer' => '2026-04-18',
                'fecha_estimada' => null,
            ],
            [
                'cedula' => '1890123456001', // Corporación Delta
                'codigo_lead' => 'LEAD-004',
                'producto' => 'PRESTAMO_CONSUMO',
                'etapa' => 'NEGOCIACION',
                'valor' => '50000.00',
                'origen' => 'Visita comercial',
                'fecha_primer' => '2026-04-10',
                'fecha_estimada' => '2026-04-30',
            ],
        ];

        foreach ($filas as $f) {
            $persona = $personas->get($f['cedula']);
            if ($persona === null) {
                continue;
            }
            $existe = DB::table('casos_lead_venta')
                ->where('proyecto_id', $proyectoId)
                ->where('codigo_lead', $f['codigo_lead'])
                ->exists();
            if ($existe) {
                continue;
            }

            $productoId = (int) DB::table('productos_venta')->where('proyecto_id', $proyectoId)->where('codigo', $f['producto'])->value('id');
            $etapaId = (int) DB::table('etapas_embudo')->where('proyecto_id', $proyectoId)->where('codigo', $f['etapa'])->value('id');

            $casoId = (int) DB::table('casos')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'proyecto_id' => $proyectoId,
                'cartera_id' => $carteraId,
                'persona_id' => (int) $persona->id,
                'tipo_caso' => 'lead_venta',
                'estado_caso_id' => $estadoNuevoId,
                'fecha_ingreso' => $f['fecha_primer'],
                'prioridad' => 100,
            ]);

            DB::table('casos_lead_venta')->insert([
                'caso_id' => $casoId,
                'proyecto_id' => $proyectoId,
                'codigo_lead' => $f['codigo_lead'],
                'producto_venta_id' => $productoId > 0 ? $productoId : null,
                'etapa_embudo_id' => $etapaId > 0 ? $etapaId : null,
                'valor_estimado' => $f['valor'],
                'moneda' => 'USD',
                'origen_lead' => $f['origen'],
                'fecha_primer_contacto' => $f['fecha_primer'],
                'fecha_estimada_cierre' => $f['fecha_estimada'],
            ]);
        }
    }
}
