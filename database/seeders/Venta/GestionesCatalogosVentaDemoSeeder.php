<?php

declare(strict_types=1);

namespace Database\Seeders\Venta;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogos scoped al proyecto Venta demo (tipos, resultados, causas = razones de rechazo).
 */
final class GestionesCatalogosVentaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'VENTA_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $this->sembrarTiposGestion($proyectoId);
        $this->sembrarResultados($proyectoId);
        $this->sembrarMotivosNoContacto($proyectoId);
        $this->sembrarRazonesRechazo($proyectoId);
    }

    private function sembrarTiposGestion(int $proyectoId): void
    {
        $rows = [
            ['codigo' => 'LLAMADA_SALIENTE', 'nombre' => 'Llamada saliente (outbound)',  'orden' => 10],
            ['codigo' => 'LLAMADA_ENTRANTE', 'nombre' => 'Llamada entrante (inbound)',   'orden' => 20],
            ['codigo' => 'VISITA',           'nombre' => 'Visita comercial',             'orden' => 30],
            ['codigo' => 'EMAIL',            'nombre' => 'Correo electrónico',           'orden' => 40],
            ['codigo' => 'WHATSAPP',         'nombre' => 'WhatsApp',                     'orden' => 50],
            ['codigo' => 'NOTA',             'nombre' => 'Nota interna',                 'orden' => 90],
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
            ['codigo' => 'INTERESADO',     'nombre' => 'Interesado',                 'es_contacto_efectivo' => true,  'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 10],
            ['codigo' => 'PROPUESTA_ENVIADA', 'nombre' => 'Propuesta enviada',       'es_contacto_efectivo' => true,  'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 20],
            ['codigo' => 'PROMESA_CIERRE', 'nombre' => 'Promesa de cierre',          'es_contacto_efectivo' => true,  'requiere_compromiso' => true,  'requiere_causa' => false, 'orden' => 30],
            ['codigo' => 'NEGOCIANDO',     'nombre' => 'En negociación',             'es_contacto_efectivo' => true,  'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 40],
            ['codigo' => 'RECHAZADO',      'nombre' => 'Rechazado por el lead',      'es_contacto_efectivo' => true,  'requiere_compromiso' => false, 'requiere_causa' => true,  'orden' => 50],
            ['codigo' => 'NO_CONTESTA',    'nombre' => 'No contesta',                'es_contacto_efectivo' => false, 'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 70],
            ['codigo' => 'BUZON',          'nombre' => 'Buzón de voz',               'es_contacto_efectivo' => false, 'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 80],
            ['codigo' => 'NUMERO_EQUIVOCADO', 'nombre' => 'Número equivocado',       'es_contacto_efectivo' => false, 'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 90],
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
            ['codigo' => 'BUZON_LLENO',       'nombre' => 'Buzón lleno',                'orden' => 30],
            ['codigo' => 'NO_INTERESADO',     'nombre' => 'Directamente no interesado', 'orden' => 40],
        ];
        foreach ($rows as $r) {
            if (DB::table('motivos_no_contacto')->where('proyecto_id', $proyectoId)->where('codigo', $r['codigo'])->exists()) {
                continue;
            }
            DB::table('motivos_no_contacto')->insert(array_merge($r, ['proyecto_id' => $proyectoId, 'activo' => true]));
        }
    }

    private function sembrarRazonesRechazo(int $proyectoId): void
    {
        $rows = [
            ['codigo' => 'PRECIO_ALTO',       'nombre' => 'Precio alto',                'orden' => 10],
            ['codigo' => 'NO_NECESITA',       'nombre' => 'No lo necesita',             'orden' => 20],
            ['codigo' => 'COMPETENCIA',       'nombre' => 'Ya tiene con la competencia','orden' => 30],
            ['codigo' => 'SIN_PRESUPUESTO',   'nombre' => 'Sin presupuesto',            'orden' => 40],
            ['codigo' => 'FALTA_INFORMACION', 'nombre' => 'Falta información',          'orden' => 50],
            ['codigo' => 'OTRA',              'nombre' => 'Otra razón',                 'orden' => 999],
        ];
        foreach ($rows as $r) {
            if (DB::table('causas_gestion')->where('proyecto_id', $proyectoId)->where('codigo', $r['codigo'])->exists()) {
                continue;
            }
            DB::table('causas_gestion')->insert(array_merge($r, [
                'proyecto_id' => $proyectoId,
                'activo'      => true,
                'metadata'    => json_encode(['tipo' => 'rechazo']),
            ]));
        }
    }
}
