<?php

declare(strict_types=1);

namespace Database\Seeders\Catalogos;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ResultadosSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'codigo' => 'CONTACTO_TITULAR',
                'nombre' => 'Contacto con titular',
                'activo' => true,
                'orden' => 10,
                'metadata' => json_encode([
                    'es_contacto_efectivo' => true,
                    'requiere_promesa' => false,
                    'requiere_causa_mora' => false,
                ]),
            ],
            [
                'codigo' => 'PROMESA_PAGO',
                'nombre' => 'Promesa de pago',
                'activo' => true,
                'orden' => 20,
                'metadata' => json_encode([
                    'es_contacto_efectivo' => true,
                    'requiere_promesa' => true,
                    'requiere_causa_mora' => true,
                ]),
            ],
            [
                'codigo' => 'PAGO_REALIZADO',
                'nombre' => 'Pago realizado',
                'activo' => true,
                'orden' => 30,
                'metadata' => json_encode([
                    'es_contacto_efectivo' => true,
                    'requiere_promesa' => false,
                    'requiere_causa_mora' => false,
                ]),
            ],
            [
                'codigo' => 'NEGOCIACION',
                'nombre' => 'Negociación abierta',
                'activo' => true,
                'orden' => 40,
                'metadata' => json_encode([
                    'es_contacto_efectivo' => true,
                    'requiere_promesa' => false,
                    'requiere_causa_mora' => true,
                ]),
            ],
            [
                'codigo' => 'RENUENTE_PAGO',
                'nombre' => 'Renuente al pago',
                'activo' => true,
                'orden' => 50,
                'metadata' => json_encode([
                    'es_contacto_efectivo' => true,
                    'requiere_promesa' => false,
                    'requiere_causa_mora' => true,
                ]),
            ],
            [
                'codigo' => 'CONTACTO_TERCERO',
                'nombre' => 'Contacto con tercero (recado)',
                'activo' => true,
                'orden' => 60,
                'metadata' => json_encode([
                    'es_contacto_efectivo' => false,
                    'requiere_promesa' => false,
                    'requiere_causa_mora' => false,
                ]),
            ],
            [
                'codigo' => 'NO_CONTESTA',
                'nombre' => 'No contesta',
                'activo' => true,
                'orden' => 70,
                'metadata' => json_encode([
                    'es_contacto_efectivo' => false,
                    'requiere_promesa' => false,
                    'requiere_causa_mora' => false,
                ]),
            ],
            [
                'codigo' => 'OCUPADO',
                'nombre' => 'Línea ocupada',
                'activo' => true,
                'orden' => 80,
                'metadata' => json_encode([
                    'es_contacto_efectivo' => false,
                    'requiere_promesa' => false,
                    'requiere_causa_mora' => false,
                ]),
            ],
            [
                'codigo' => 'BUZON',
                'nombre' => 'Buzón de voz',
                'activo' => true,
                'orden' => 90,
                'metadata' => json_encode([
                    'es_contacto_efectivo' => false,
                    'requiere_promesa' => false,
                    'requiere_causa_mora' => false,
                ]),
            ],
            [
                'codigo' => 'FUERA_SERVICIO',
                'nombre' => 'Teléfono fuera de servicio',
                'activo' => true,
                'orden' => 100,
                'metadata' => json_encode([
                    'es_contacto_efectivo' => false,
                    'requiere_promesa' => false,
                    'requiere_causa_mora' => false,
                ]),
            ],
            [
                'codigo' => 'NUMERO_EQUIVOCADO',
                'nombre' => 'Número equivocado',
                'activo' => true,
                'orden' => 110,
                'metadata' => json_encode([
                    'es_contacto_efectivo' => false,
                    'requiere_promesa' => false,
                    'requiere_causa_mora' => false,
                ]),
            ],
            [
                'codigo' => 'NO_UBICADO',
                'nombre' => 'No ubicado en dirección',
                'activo' => true,
                'orden' => 120,
                'metadata' => json_encode([
                    'es_contacto_efectivo' => false,
                    'requiere_promesa' => false,
                    'requiere_causa_mora' => false,
                ]),
            ],
        ];

        DB::table('resultados')->upsert(
            $rows,
            ['codigo'],
            ['nombre', 'activo', 'orden', 'metadata'],
        );
    }
}
