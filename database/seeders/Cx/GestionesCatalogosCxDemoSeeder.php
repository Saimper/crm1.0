<?php

declare(strict_types=1);

namespace Database\Seeders\Cx;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogos scoped al proyecto CX demo para permitir registrar gestiones (tipos, resultados, causas).
 */
final class GestionesCatalogosCxDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
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
            ['codigo' => 'LLAMADA_ENTRANTE', 'nombre' => 'Llamada entrante',  'orden' => 10],
            ['codigo' => 'LLAMADA_SALIENTE', 'nombre' => 'Llamada saliente',  'orden' => 20],
            ['codigo' => 'EMAIL',            'nombre' => 'Correo electrónico','orden' => 30],
            ['codigo' => 'WHATSAPP',         'nombre' => 'WhatsApp',          'orden' => 40],
            ['codigo' => 'CHAT',             'nombre' => 'Chat web',          'orden' => 50],
            ['codigo' => 'NOTA',             'nombre' => 'Nota interna',      'orden' => 90],
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
            ['codigo' => 'CONTACTADO',        'nombre' => 'Contactado, sin resolución aún','es_contacto_efectivo' => true,  'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 10],
            ['codigo' => 'COMPROMISO_SLA',    'nombre' => 'Compromiso de resolución',     'es_contacto_efectivo' => true,  'requiere_compromiso' => true,  'requiere_causa' => false, 'orden' => 20],
            ['codigo' => 'RESUELTO_CONTACTO', 'nombre' => 'Resuelto en el contacto',      'es_contacto_efectivo' => true,  'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 30],
            ['codigo' => 'ESCALADO',          'nombre' => 'Escalado a nivel superior',    'es_contacto_efectivo' => true,  'requiere_compromiso' => true,  'requiere_causa' => true,  'orden' => 40],
            ['codigo' => 'QUEJA',             'nombre' => 'Queja/reclamo registrado',     'es_contacto_efectivo' => true,  'requiere_compromiso' => false, 'requiere_causa' => true,  'orden' => 50],
            ['codigo' => 'NO_CONTESTA',       'nombre' => 'No contesta',                  'es_contacto_efectivo' => false, 'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 70],
            ['codigo' => 'BUZON',             'nombre' => 'Buzón de voz',                 'es_contacto_efectivo' => false, 'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 80],
            ['codigo' => 'NUMERO_EQUIVOCADO', 'nombre' => 'Número equivocado',            'es_contacto_efectivo' => false, 'requiere_compromiso' => false, 'requiere_causa' => false, 'orden' => 90],
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
            ['codigo' => 'EMAIL_REBOTADO',    'nombre' => 'Correo rebotado',            'orden' => 40],
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
            ['codigo' => 'SERVICIO_LENTO',    'nombre' => 'Servicio lento',         'orden' => 10],
            ['codigo' => 'CAIDO',             'nombre' => 'Servicio caído',         'orden' => 20],
            ['codigo' => 'ERROR_FACTURA',     'nombre' => 'Error en factura',       'orden' => 30],
            ['codigo' => 'ATENCION_PREVIA',   'nombre' => 'Mala atención previa',   'orden' => 40],
            ['codigo' => 'OTRO',              'nombre' => 'Otro motivo',            'orden' => 999],
        ];
        foreach ($rows as $r) {
            if (DB::table('causas_gestion')->where('proyecto_id', $proyectoId)->where('codigo', $r['codigo'])->exists()) {
                continue;
            }
            DB::table('causas_gestion')->insert(array_merge($r, ['proyecto_id' => $proyectoId, 'activo' => true, 'metadata' => json_encode(['tipo' => 'queja'])]));
        }
    }
}
