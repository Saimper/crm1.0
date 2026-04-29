<?php

declare(strict_types=1);

namespace Database\Seeders\Gestiones;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra catálogos scoped al proyecto demo de cobranza para permitir registrar gestiones.
 * Incluye: tipos_gestion, resultados (con banderas), motivos_no_contacto, causas_gestion.
 */
final class GestionesCatalogosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $this->sembrarTiposGestion($proyectoId);
        $this->sembrarResultados($proyectoId);
        $this->sembrarMotivosNoContacto($proyectoId);
        $this->sembrarCausas($proyectoId);
    }

    private function sembrarTiposGestion(int $proyectoId): void
    {
        $rows = [
            ['codigo' => 'LLAMADA_SALIENTE', 'nombre' => 'Llamada saliente',   'orden' => 10],
            ['codigo' => 'LLAMADA_ENTRANTE', 'nombre' => 'Llamada entrante',   'orden' => 20],
            ['codigo' => 'VISITA',           'nombre' => 'Visita domiciliaria','orden' => 30],
            ['codigo' => 'WHATSAPP',         'nombre' => 'WhatsApp',           'orden' => 40],
            ['codigo' => 'CORREO',           'nombre' => 'Correo electrónico', 'orden' => 60],
            ['codigo' => 'NOTA',             'nombre' => 'Nota interna',       'orden' => 90],
        ];
        foreach ($rows as $r) {
            if (DB::table('tipos_gestion')->where('proyecto_id', $proyectoId)->where('codigo', $r['codigo'])->exists()) {
                continue;
            }
            DB::table('tipos_gestion')->insert(array_merge($r, ['proyecto_id' => $proyectoId, 'activo' => true]));
        }
    }

    private function sembrarResultados(int $proyectoId): void
    {
        $rows = [
            ['codigo' => 'CONTACTO_TITULAR', 'nombre' => 'Contacto con titular',          'es_contacto_efectivo' => true,  'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 10],
            ['codigo' => 'PROMESA_PAGO',     'nombre' => 'Promesa de pago',               'es_contacto_efectivo' => true,  'requiere_compromiso' => true,  'requiere_causa' => true,  'orden' => 20],
            ['codigo' => 'PAGO_REALIZADO',   'nombre' => 'Pago realizado',                'es_contacto_efectivo' => true,  'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 30],
            ['codigo' => 'NEGOCIACION',      'nombre' => 'Negociación abierta',           'es_contacto_efectivo' => true,  'requiere_compromiso' => false, 'requiere_causa' => true,  'orden' => 40],
            ['codigo' => 'RENUENTE_PAGO',    'nombre' => 'Renuente al pago',              'es_contacto_efectivo' => true,  'requiere_compromiso' => false, 'requiere_causa' => true,  'orden' => 50],
            ['codigo' => 'CONTACTO_TERCERO', 'nombre' => 'Contacto con tercero (recado)', 'es_contacto_efectivo' => false, 'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 60],
            ['codigo' => 'NO_CONTESTA',      'nombre' => 'No contesta',                   'es_contacto_efectivo' => false, 'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 70],
            ['codigo' => 'OCUPADO',          'nombre' => 'Línea ocupada',                 'es_contacto_efectivo' => false, 'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 80],
            ['codigo' => 'BUZON',            'nombre' => 'Buzón de voz',                  'es_contacto_efectivo' => false, 'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 90],
            ['codigo' => 'NUMERO_EQUIVOCADO','nombre' => 'Número equivocado',             'es_contacto_efectivo' => false, 'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 110],
        ];
        foreach ($rows as $r) {
            if (DB::table('resultados')->where('proyecto_id', $proyectoId)->where('codigo', $r['codigo'])->exists()) {
                continue;
            }
            DB::table('resultados')->insert(array_merge($r, ['proyecto_id' => $proyectoId, 'activo' => true]));
        }
    }

    private function sembrarMotivosNoContacto(int $proyectoId): void
    {
        $rows = [
            ['codigo' => 'NO_RESPONDE',       'nombre' => 'No responde llamadas',       'orden' => 10],
            ['codigo' => 'NUMERO_EQUIVOCADO', 'nombre' => 'Número equivocado',          'orden' => 20],
            ['codigo' => 'FUERA_SERVICIO',    'nombre' => 'Teléfono fuera de servicio', 'orden' => 30],
            ['codigo' => 'CAMBIO_DIRECCION',  'nombre' => 'Cambió de dirección',        'orden' => 50],
        ];
        foreach ($rows as $r) {
            if (DB::table('motivos_no_contacto')->where('proyecto_id', $proyectoId)->where('codigo', $r['codigo'])->exists()) {
                continue;
            }
            DB::table('motivos_no_contacto')->insert(array_merge($r, ['proyecto_id' => $proyectoId, 'activo' => true]));
        }
    }

    private function sembrarCausas(int $proyectoId): void
    {
        $rows = [
            ['codigo' => 'DESEMPLEO',          'nombre' => 'Desempleo',             'orden' => 10],
            ['codigo' => 'REDUCCION_INGRESOS', 'nombre' => 'Reducción de ingresos', 'orden' => 20],
            ['codigo' => 'ENFERMEDAD',         'nombre' => 'Enfermedad',            'orden' => 30],
            ['codigo' => 'OLVIDO',             'nombre' => 'Olvido',                'orden' => 80],
            ['codigo' => 'OTRO',               'nombre' => 'Otro',                  'orden' => 999],
        ];
        foreach ($rows as $r) {
            if (DB::table('causas_gestion')->where('proyecto_id', $proyectoId)->where('codigo', $r['codigo'])->exists()) {
                continue;
            }
            DB::table('causas_gestion')->insert(array_merge($r, ['proyecto_id' => $proyectoId, 'activo' => true]));
        }
    }
}
