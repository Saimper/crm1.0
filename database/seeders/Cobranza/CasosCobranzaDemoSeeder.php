<?php

declare(strict_types=1);

namespace Database\Seeders\Cobranza;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 5 casos de cobranza demo (CTI: casos + casos_cobranza) para el proyecto Cobranza Demo 2026.
 * Replica el escenario que traía v1 de préstamos con distintos niveles de mora.
 */
final class CasosCobranzaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $carteraId = (int) DB::table('carteras')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'CONSUMO')->value('id');
        $estadoAbiertoId = (int) DB::table('estados_caso')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'ABIERTO')->value('id');

        if ($carteraId === 0 || $estadoAbiertoId === 0) {
            return;
        }

        $personas = DB::table('personas')->where('proyecto_id', $proyectoId)->get()->keyBy('identificacion');

        $filas = [
            [
                'cedula' => '0102030405',   // Juan Pérez
                'numero_prestamo' => 'PRST-001',
                'monto_original' => '10000.00',
                'saldo_capital' => '8500.00',
                'saldo_interes' => '250.00',
                'saldo_total' => '8750.00',
                'cuota_mensual' => '850.00',
                'cuotas_totales' => 12,
                'cuotas_pagadas' => 2,
                'dias_mora' => 35,
                'fecha_desembolso' => '2026-01-10',
                'fecha_vencimiento' => '2027-01-10',
            ],
            [
                'cedula' => '0203040506',   // María González
                'numero_prestamo' => 'PRST-002',
                'monto_original' => '5000.00',
                'saldo_capital' => '4200.00',
                'saldo_interes' => '80.00',
                'saldo_total' => '4280.00',
                'cuota_mensual' => '420.00',
                'cuotas_totales' => 12,
                'cuotas_pagadas' => 2,
                'dias_mora' => 15,
                'fecha_desembolso' => '2026-01-15',
                'fecha_vencimiento' => '2027-01-15',
            ],
            [
                'cedula' => '1792345678001', // Comercial Austral S.A.
                'numero_prestamo' => 'PRST-003',
                'monto_original' => '50000.00',
                'saldo_capital' => '40000.00',
                'saldo_interes' => '1500.00',
                'saldo_total' => '41500.00',
                'cuota_mensual' => '2500.00',
                'cuotas_totales' => 24,
                'cuotas_pagadas' => 5,
                'dias_mora' => 75,
                'fecha_desembolso' => '2025-11-01',
                'fecha_vencimiento' => '2027-11-01',
            ],
            [
                'cedula' => '0304050607',   // Carlos Ramírez
                'numero_prestamo' => 'PRST-004',
                'monto_original' => '3000.00',
                'saldo_capital' => '2800.00',
                'saldo_interes' => '180.00',
                'saldo_total' => '2980.00',
                'cuota_mensual' => '280.00',
                'cuotas_totales' => 12,
                'cuotas_pagadas' => 1,
                'dias_mora' => 110,
                'fecha_desembolso' => '2025-12-20',
                'fecha_vencimiento' => '2026-12-20',
            ],
            [
                'cedula' => '0102030405',   // Juan Pérez (segundo préstamo)
                'numero_prestamo' => 'PRST-005',
                'monto_original' => '7500.00',
                'saldo_capital' => '7500.00',
                'saldo_interes' => '0.00',
                'saldo_total' => '7500.00',
                'cuota_mensual' => '625.00',
                'cuotas_totales' => 12,
                'cuotas_pagadas' => 0,
                'dias_mora' => 0,
                'fecha_desembolso' => '2026-04-01',
                'fecha_vencimiento' => '2027-04-01',
            ],
        ];

        foreach ($filas as $f) {
            $persona = $personas->get($f['cedula']);
            if ($persona === null) {
                continue;
            }

            $existe = DB::table('casos_cobranza')
                ->where('proyecto_id', $proyectoId)
                ->where('numero_prestamo', $f['numero_prestamo'])
                ->exists();
            if ($existe) {
                continue;
            }

            $tramoId = $this->resolverTramo($proyectoId, $f['dias_mora']);

            $casoId = (int) DB::table('casos')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'proyecto_id' => $proyectoId,
                'cartera_id' => $carteraId,
                'persona_id' => (int) $persona->id,
                'tipo_caso' => 'cobranza',
                'estado_caso_id' => $estadoAbiertoId,
                'fecha_ingreso' => '2026-04-17',
                'prioridad' => 100,
            ]);

            DB::table('casos_cobranza')->insert([
                'caso_id' => $casoId,
                'proyecto_id' => $proyectoId,
                'numero_prestamo' => $f['numero_prestamo'],
                'moneda' => 'USD',
                'monto_original' => $f['monto_original'],
                'saldo_capital' => $f['saldo_capital'],
                'saldo_interes' => $f['saldo_interes'],
                'saldo_total' => $f['saldo_total'],
                'cuota_mensual' => $f['cuota_mensual'],
                'cuotas_totales' => $f['cuotas_totales'],
                'cuotas_pagadas' => $f['cuotas_pagadas'],
                'dias_mora' => $f['dias_mora'],
                'tramo_mora_id' => $tramoId,
                'fecha_desembolso' => $f['fecha_desembolso'],
                'fecha_vencimiento' => $f['fecha_vencimiento'],
            ]);
        }
    }

    private function resolverTramo(int $proyectoId, int $diasMora): ?int
    {
        $tramo = DB::table('tramos_mora')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->where('dias_desde', '<=', $diasMora)
            ->where(function ($q) use ($diasMora): void {
                $q->whereNull('dias_hasta')->orWhere('dias_hasta', '>=', $diasMora);
            })
            ->orderBy('dias_desde', 'desc')
            ->value('id');

        return $tramo === null ? null : (int) $tramo;
    }
}
