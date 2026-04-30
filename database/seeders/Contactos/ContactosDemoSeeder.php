<?php

declare(strict_types=1);

namespace Database\Seeders\Contactos;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ContactosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $contactosPorIdentificacion = [
            '0102030405' => [
                ['tipo' => 'telefono', 'valor' => '+593 98 123 4567', 'etiqueta' => 'móvil', 'es_principal' => true],
                ['tipo' => 'correo',   'valor' => 'juan.perez@correo.com'],
            ],
            '0203040506' => [
                ['tipo' => 'telefono', 'valor' => '+593 99 765 4321', 'etiqueta' => 'móvil', 'es_principal' => true],
                ['tipo' => 'telefono', 'valor' => '+593 2 222 3333',  'etiqueta' => 'casa'],
            ],
            '1792345678001' => [
                ['tipo' => 'telefono', 'valor' => '+593 2 444 5566', 'etiqueta' => 'oficina', 'es_principal' => true],
                ['tipo' => 'correo',   'valor' => 'contacto@austral.com.ec'],
            ],
            '0304050607' => [
                ['tipo' => 'telefono', 'valor' => '+593 96 111 2233', 'etiqueta' => 'móvil', 'es_principal' => true],
            ],
        ];

        foreach ($contactosPorIdentificacion as $identificacion => $contactos) {
            $personaId = (int) DB::table('personas')
                ->where('proyecto_id', $proyectoId)
                ->where('identificacion', $identificacion)
                ->value('id');

            if ($personaId === 0) {
                continue;
            }

            foreach ($contactos as $c) {
                $yaExiste = DB::table('contactos')
                    ->where('proyecto_id', $proyectoId)
                    ->where('persona_id', $personaId)
                    ->where('valor', $c['valor'])
                    ->exists();
                if ($yaExiste) {
                    continue;
                }

                DB::table('contactos')->insert([
                    'proyecto_id' => $proyectoId,
                    'persona_id' => $personaId,
                    'tipo' => $c['tipo'],
                    'valor' => $c['valor'],
                    'etiqueta' => $c['etiqueta'] ?? null,
                    'es_principal' => $c['es_principal'] ?? false,
                    'activo' => true,
                ]);
            }
        }
    }
}
