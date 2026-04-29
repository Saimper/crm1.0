<?php

declare(strict_types=1);

namespace Database\Seeders\Cobranza;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dos campos personalizados demo para validar el módulo end-to-end:
 *   - Caso × cartera Consumo → "operador_externo" (texto_corto, opcional).
 *   - Gestión × CONFIRMACION_PAGO → "numero_referencia_bancaria" (texto_corto, obligatorio).
 *
 * Siembra también el tipo de gestión CONFIRMACION_PAGO si falta.
 */
final class CamposPersonalizadosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $this->asegurarTipoGestionConfirmacionPago($proyectoId);

        $carteraId = (int) DB::table('carteras')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'CONSUMO')->value('id');
        if ($carteraId > 0) {
            $this->upsertCampo(
                proyectoId: $proyectoId,
                ambito:     'caso',
                ambitoId:   $carteraId,
                codigo:     'operador_externo',
                etiqueta:   'Operador externo que originó la cuenta',
                tipo:       'texto_corto',
                obligatorio: false,
                orden:      10,
                reglas:     ['longitud_max' => 150],
            );
        }

        $tipoConfirmacionId = (int) DB::table('tipos_gestion')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'CONFIRMACION_PAGO')->value('id');
        if ($tipoConfirmacionId > 0) {
            $this->upsertCampo(
                proyectoId: $proyectoId,
                ambito:     'gestion',
                ambitoId:   $tipoConfirmacionId,
                codigo:     'numero_referencia_bancaria',
                etiqueta:   'Número de referencia bancaria',
                tipo:       'texto_corto',
                obligatorio: true,
                orden:      10,
                reglas:     ['longitud_max' => 80],
            );
        }
    }

    private function asegurarTipoGestionConfirmacionPago(int $proyectoId): void
    {
        $existe = DB::table('tipos_gestion')
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', 'CONFIRMACION_PAGO')
            ->exists();
        if ($existe) {
            return;
        }

        DB::table('tipos_gestion')->insert([
            'proyecto_id' => $proyectoId,
            'codigo'      => 'CONFIRMACION_PAGO',
            'nombre'      => 'Confirmación de pago',
            'activo'      => true,
            'orden'       => 50,
        ]);
    }

    /** @param array<string, mixed> $reglas */
    private function upsertCampo(
        int $proyectoId,
        string $ambito,
        int $ambitoId,
        string $codigo,
        string $etiqueta,
        string $tipo,
        bool $obligatorio,
        int $orden,
        array $reglas,
    ): void {
        $existe = DB::table('campos_personalizados')
            ->where('proyecto_id', $proyectoId)
            ->where('ambito', $ambito)
            ->where('ambito_id', $ambitoId)
            ->where('codigo', $codigo)
            ->exists();
        if ($existe) {
            return;
        }

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyectoId,
            'ambito'      => $ambito,
            'ambito_id'   => $ambitoId,
            'tipo'        => $tipo,
            'codigo'      => $codigo,
            'etiqueta'    => $etiqueta,
            'obligatorio' => $obligatorio,
            'activo'      => true,
            'orden'       => $orden,
            'reglas'      => json_encode($reglas),
        ]);
    }
}
