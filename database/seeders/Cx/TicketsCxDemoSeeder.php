<?php

declare(strict_types=1);

namespace Database\Seeders\Cx;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 4 tickets CX demo (CTI: casos + casos_ticket_cx) para el proyecto Soporte Demo 2026.
 */
final class TicketsCxDemoSeeder extends Seeder
{
    public function run(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        if ($proyectoId === 0) {
            return;
        }

        $carteraId = (int) DB::table('carteras')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'SOPORTE_GENERAL')->value('id');
        $estadoAbiertoId = (int) DB::table('estados_caso')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'ABIERTO')->value('id');

        if ($carteraId === 0 || $estadoAbiertoId === 0) {
            return;
        }

        $personas = DB::table('personas')->where('proyecto_id', $proyectoId)->get()->keyBy('identificacion');

        $filas = [
            [
                'cedula'          => '0102030405', // Juan Pérez (CX)
                'codigo_ticket'   => 'TKT-001',
                'asunto'          => 'No puede acceder al portal',
                'descripcion'     => 'Usuario reporta que tras cambio de contraseña no logra entrar.',
                'categoria'       => 'ACCESO',
                'prioridad'       => 'ALTA',
                'sla'             => 'SLA_24H',
                'escalamiento'    => 'N1',
                'fecha_reporte'   => '2026-04-17 09:15:00',
                'fecha_limite_sla' => '2026-04-18 09:15:00',
            ],
            [
                'cedula'          => '0405060708',
                'codigo_ticket'   => 'TKT-002',
                'asunto'          => 'Factura con monto incorrecto',
                'descripcion'     => 'Cliente reporta sobrecargo en última factura.',
                'categoria'       => 'FACTURACION',
                'prioridad'       => 'MEDIA',
                'sla'             => 'SLA_48H',
                'escalamiento'    => 'N1',
                'fecha_reporte'   => '2026-04-17 14:30:00',
                'fecha_limite_sla' => '2026-04-19 14:30:00',
            ],
            [
                'cedula'          => '0506070809',
                'codigo_ticket'   => 'TKT-003',
                'asunto'          => 'Servicio intermitente',
                'descripcion'     => 'Cortes frecuentes en el último mes.',
                'categoria'       => 'SERVICIO',
                'prioridad'       => 'URGENTE',
                'sla'             => 'SLA_4H',
                'escalamiento'    => 'N2',
                'fecha_reporte'   => '2026-04-18 08:00:00',
                'fecha_limite_sla' => '2026-04-18 12:00:00',
            ],
            [
                'cedula'          => '0990123456789', // Soluciones Cloud C.A.
                'codigo_ticket'   => 'TKT-004',
                'asunto'          => 'Instalación pendiente',
                'descripcion'     => 'Esperando coordinación de instalación en oficina sucursal.',
                'categoria'       => 'INSTALACION',
                'prioridad'       => 'MEDIA',
                'sla'             => 'SLA_72H',
                'escalamiento'    => 'N1',
                'fecha_reporte'   => '2026-04-18 10:00:00',
                'fecha_limite_sla' => '2026-04-21 10:00:00',
            ],
        ];

        foreach ($filas as $f) {
            $persona = $personas->get($f['cedula']);
            if ($persona === null) {
                continue;
            }
            $existe = DB::table('casos_ticket_cx')
                ->where('proyecto_id', $proyectoId)
                ->where('codigo_ticket', $f['codigo_ticket'])
                ->exists();
            if ($existe) {
                continue;
            }

            $categoriaId    = (int) DB::table('categorias_ticket')->where('proyecto_id', $proyectoId)->where('codigo', $f['categoria'])->value('id');
            $prioridadId    = (int) DB::table('prioridades_ticket')->where('proyecto_id', $proyectoId)->where('codigo', $f['prioridad'])->value('id');
            $slaId          = (int) DB::table('niveles_sla')->where('proyecto_id', $proyectoId)->where('codigo', $f['sla'])->value('id');
            $escalamientoId = (int) DB::table('niveles_escalamiento')->where('proyecto_id', $proyectoId)->where('codigo', $f['escalamiento'])->value('id');

            $casoId = (int) DB::table('casos')->insertGetId([
                'public_id'      => (string) Str::ulid(),
                'proyecto_id'    => $proyectoId,
                'cartera_id'     => $carteraId,
                'persona_id'     => (int) $persona->id,
                'tipo_caso'      => 'ticket_cx',
                'estado_caso_id' => $estadoAbiertoId,
                'fecha_ingreso'  => substr($f['fecha_reporte'], 0, 10),
                'prioridad'      => 100,
            ]);

            DB::table('casos_ticket_cx')->insert([
                'caso_id'                => $casoId,
                'proyecto_id'            => $proyectoId,
                'codigo_ticket'          => $f['codigo_ticket'],
                'asunto'                 => $f['asunto'],
                'descripcion'            => $f['descripcion'],
                'categoria_ticket_id'    => $categoriaId > 0 ? $categoriaId : null,
                'prioridad_ticket_id'    => $prioridadId > 0 ? $prioridadId : null,
                'nivel_sla_id'           => $slaId > 0 ? $slaId : null,
                'nivel_escalamiento_id'  => $escalamientoId > 0 ? $escalamientoId : null,
                'fecha_reporte'          => $f['fecha_reporte'],
                'fecha_limite_sla'       => $f['fecha_limite_sla'],
            ]);
        }
    }
}
