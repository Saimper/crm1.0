<?php

declare(strict_types=1);

namespace Database\Seeders\Servicio;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 4 casos de servicio técnico demo (CTI: casos + casos_servicio) para el proyecto Servicio Demo 2026.
 */
final class CasosServicioDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SERVICIO_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $carteraId = (int) DB::table('carteras')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'RESIDENCIAL')->value('id');
        $estadoPendienteId = (int) DB::table('estados_caso')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'PENDIENTE')->value('id');

        if ($carteraId === 0 || $estadoPendienteId === 0) {
            return;
        }

        $personas = DB::table('personas')->where('proyecto_id', $proyectoId)->get()->keyBy('identificacion');

        $filas = [
            [
                'cedula' => '0910111213',
                'codigo_servicio' => 'SVC-001',
                'tipo_accion' => 'INSTALACION',
                'estado_tecnico' => 'AGENDADO',
                'direccion' => 'Av. América N23-45, Quito',
                'tecnico' => 'Carlos Peña',
                'fecha_solicitud' => '2026-04-17',
                'fecha_programada' => '2026-04-22 10:00:00',
            ],
            [
                'cedula' => '1011121314',
                'codigo_servicio' => 'SVC-002',
                'tipo_accion' => 'REPARACION',
                'estado_tecnico' => 'SIN_AGENDA',
                'direccion' => 'Calle 6 de diciembre N45-67',
                'tecnico' => null,
                'fecha_solicitud' => '2026-04-18',
                'fecha_programada' => null,
            ],
            [
                'cedula' => '1112131415',
                'codigo_servicio' => 'SVC-003',
                'tipo_accion' => 'MANTENIMIENTO',
                'estado_tecnico' => 'AGENDADO',
                'direccion' => 'Av. Eloy Alfaro N56-78',
                'tecnico' => 'María López',
                'fecha_solicitud' => '2026-04-16',
                'fecha_programada' => '2026-04-21 14:00:00',
            ],
            [
                'cedula' => '1795678901001',
                'codigo_servicio' => 'SVC-004',
                'tipo_accion' => 'CONFIGURACION',
                'estado_tecnico' => 'EN_TERRENO',
                'direccion' => 'Zona industrial sur, bodega 12',
                'tecnico' => 'Roberto Jiménez',
                'fecha_solicitud' => '2026-04-18',
                'fecha_programada' => '2026-04-19 09:00:00',
            ],
        ];

        foreach ($filas as $f) {
            $persona = $personas->get($f['cedula']);
            if ($persona === null) {
                continue;
            }
            if (DB::table('casos_servicio')->where('proyecto_id', $proyectoId)->where('codigo_servicio', $f['codigo_servicio'])->exists()) {
                continue;
            }

            $tipoAccionId = (int) DB::table('tipos_accion_servicio')->where('proyecto_id', $proyectoId)->where('codigo', $f['tipo_accion'])->value('id');
            $estadoTecId = (int) DB::table('estados_tecnicos')->where('proyecto_id', $proyectoId)->where('codigo', $f['estado_tecnico'])->value('id');

            $casoId = (int) DB::table('casos')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'proyecto_id' => $proyectoId,
                'cartera_id' => $carteraId,
                'persona_id' => (int) $persona->id,
                'tipo_caso' => 'servicio',
                'estado_caso_id' => $estadoPendienteId,
                'fecha_ingreso' => $f['fecha_solicitud'],
                'prioridad' => 100,
            ]);

            DB::table('casos_servicio')->insert([
                'caso_id' => $casoId,
                'proyecto_id' => $proyectoId,
                'codigo_servicio' => $f['codigo_servicio'],
                'tipo_accion_servicio_id' => $tipoAccionId > 0 ? $tipoAccionId : null,
                'estado_tecnico_id' => $estadoTecId > 0 ? $estadoTecId : null,
                'direccion_servicio' => $f['direccion'],
                'tecnico_asignado' => $f['tecnico'],
                'fecha_solicitud' => $f['fecha_solicitud'],
                'fecha_programada' => $f['fecha_programada'],
            ]);
        }
    }
}
