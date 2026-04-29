<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $tipoCed     = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $tipoRuc     = (int) DB::table('tipos_identificacion')->where('codigo', 'RUC')->value('id');
        $cartera     = (int) DB::table('carteras')->where('codigo', 'CONSUMO')->value('id');
        $estadoMora  = (int) DB::table('estados_producto')->where('codigo', 'MORA')->value('id');
        $tramo3160   = (int) DB::table('tramos_mora')->where('codigo', 'TRAMO_31_60')->value('id');

        $clientes = [
            [
                'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
                'identificacion' => '0102030405', 'nombres' => 'Juan', 'apellidos' => 'Pérez',
                'prestamos' => [['numero' => 'P-00001', 'saldo' => '3200.50', 'dias' => 45]],
            ],
            [
                'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
                'identificacion' => '0203040506', 'nombres' => 'María', 'apellidos' => 'González',
                'prestamos' => [
                    ['numero' => 'P-00002', 'saldo' => '1850.00', 'dias' => 15],
                    ['numero' => 'P-00003', 'saldo' => '9200.00', 'dias' => 90],
                ],
            ],
            [
                'tipo_persona' => 'juridica', 'tipo_identificacion_id' => $tipoRuc,
                'identificacion' => '1792345678001', 'razon_social' => 'Comercial Austral S.A.',
                'prestamos' => [['numero' => 'P-00004', 'saldo' => '25000.00', 'dias' => 10]],
            ],
            [
                'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
                'identificacion' => '0304050607', 'nombres' => 'Carlos', 'apellidos' => 'Ramírez',
                'prestamos' => [['numero' => 'P-00005', 'saldo' => '560.00', 'dias' => 180]],
            ],
        ];

        foreach ($clientes as $data) {
            $prestamos = $data['prestamos'];
            unset($data['prestamos']);

            $existente = DB::table('clientes')->where('identificacion', $data['identificacion'])->first();
            $clienteId = $existente
                ? (int) $existente->id
                : (int) DB::table('clientes')->insertGetId(array_merge(
                    $data,
                    ['public_id' => (string) Str::ulid()],
                ));

            foreach ($prestamos as $p) {
                if (DB::table('productos')->where('numero_prestamo', $p['numero'])->exists()) {
                    continue;
                }

                DB::table('productos')->insert([
                    'public_id'          => (string) Str::ulid(),
                    'cliente_id'         => $clienteId,
                    'numero_prestamo'    => $p['numero'],
                    'cartera_id'         => $cartera,
                    'estado_producto_id' => $estadoMora,
                    'tramo_mora_id'      => $tramo3160,
                    'monto_original'     => bcmul($p['saldo'], '1.5', 2),
                    'saldo_capital'      => $p['saldo'],
                    'saldo_total'        => bcmul($p['saldo'], '1.1', 2),
                    'cuota_mensual'      => bcmul($p['saldo'], '0.05', 2),
                    'dias_mora'          => $p['dias'],
                    'cuotas_totales'     => 24,
                    'cuotas_pagadas'     => 6,
                    'moneda'             => 'USD',
                    'fecha_desembolso'   => '2026-01-15',
                    'fecha_vencimiento'  => '2028-01-15',
                ]);
            }

            $contactosPorIdentificacion = [
                '0102030405'    => [['tipo' => 'telefono', 'valor' => '+593 98 123 4567', 'es_principal' => true], ['tipo' => 'correo', 'valor' => 'juan.perez@correo.com']],
                '0203040506'    => [['tipo' => 'telefono', 'valor' => '+593 99 765 4321', 'es_principal' => true], ['tipo' => 'telefono', 'valor' => '+593 2 222 3333']],
                '1792345678001' => [['tipo' => 'telefono', 'valor' => '+593 2 444 5566', 'es_principal' => true], ['tipo' => 'correo', 'valor' => 'contacto@austral.com.ec']],
                '0304050607'    => [['tipo' => 'telefono', 'valor' => '+593 96 111 2233', 'es_principal' => true]],
            ];

            $contactos = $contactosPorIdentificacion[$data['identificacion']] ?? [];
            foreach ($contactos as $c) {
                if (DB::table('contactos')->where('cliente_id', $clienteId)->where('valor', $c['valor'])->exists()) {
                    continue;
                }
                DB::table('contactos')->insert([
                    'cliente_id'   => $clienteId,
                    'tipo'         => $c['tipo'],
                    'valor'        => $c['valor'],
                    'es_principal' => $c['es_principal'] ?? false,
                    'activo'       => true,
                ]);
            }
        }
    }
}
