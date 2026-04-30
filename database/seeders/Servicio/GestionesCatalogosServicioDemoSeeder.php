<?php

declare(strict_types=1);

namespace Database\Seeders\Servicio;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogos scoped al proyecto Servicio demo (tipos, resultados, motivos_no_contacto,
 * causas = motivos de intervención / fallo).
 */
final class GestionesCatalogosServicioDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SERVICIO_DEMO_2026')->value('id');
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
            ['codigo' => 'COORDINACION',     'nombre' => 'Coordinación telefónica', 'orden' => 10],
            ['codigo' => 'VISITA_TECNICA',   'nombre' => 'Visita técnica',          'orden' => 20],
            ['codigo' => 'WHATSAPP',         'nombre' => 'WhatsApp',                'orden' => 30],
            ['codigo' => 'EMAIL',            'nombre' => 'Correo electrónico',      'orden' => 40],
            ['codigo' => 'NOTA',             'nombre' => 'Nota interna',            'orden' => 90],
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
            ['codigo' => 'AGENDADO',          'nombre' => 'Visita agendada',          'es_contacto_efectivo' => true,  'requiere_compromiso' => true,  'requiere_causa' => false, 'orden' => 10],
            ['codigo' => 'EJECUTADO_OK',      'nombre' => 'Servicio ejecutado OK',    'es_contacto_efectivo' => true,  'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 20],
            ['codigo' => 'REPROGRAMADO',      'nombre' => 'Reprogramado',             'es_contacto_efectivo' => true,  'requiere_compromiso' => true,  'requiere_causa' => true,  'orden' => 30],
            ['codigo' => 'INCIDENCIA_TECNICA', 'nombre' => 'Incidencia técnica',       'es_contacto_efectivo' => true,  'requiere_compromiso' => false, 'requiere_causa' => true,  'orden' => 40],
            ['codigo' => 'NO_CONTESTA',       'nombre' => 'No contesta',              'es_contacto_efectivo' => false, 'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 70],
            ['codigo' => 'DIRECCION_ERRADA',  'nombre' => 'Dirección errada',         'es_contacto_efectivo' => false, 'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 80],
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
            ['codigo' => 'NO_RESPONDE',       'nombre' => 'No responde llamadas',        'orden' => 10],
            ['codigo' => 'DIRECCION_ERRADA',  'nombre' => 'Dirección errada',            'orden' => 20],
            ['codigo' => 'CLIENTE_AUSENTE',   'nombre' => 'Cliente ausente en terreno',  'orden' => 30],
            ['codigo' => 'ACCESO_DENEGADO',   'nombre' => 'Acceso al domicilio denegado', 'orden' => 40],
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
            ['codigo' => 'FALTA_MATERIAL',    'nombre' => 'Falta de material',           'orden' => 10],
            ['codigo' => 'CLIMA_ADVERSO',     'nombre' => 'Clima adverso',               'orden' => 20],
            ['codigo' => 'FALLA_EQUIPO',      'nombre' => 'Falla en equipo del cliente', 'orden' => 30],
            ['codigo' => 'AJUSTE_AGENDA',     'nombre' => 'Ajuste de agenda',            'orden' => 40],
            ['codigo' => 'OTRA',              'nombre' => 'Otra',                        'orden' => 999],
        ];
        foreach ($rows as $r) {
            if (DB::table('causas_gestion')->where('proyecto_id', $proyectoId)->where('codigo', $r['codigo'])->exists()) {
                continue;
            }
            DB::table('causas_gestion')->insert(array_merge($r, [
                'proyecto_id' => $proyectoId,
                'activo' => true,
                'metadata' => json_encode(['tipo' => 'servicio']),
            ]));
        }
    }
}
