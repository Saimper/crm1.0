<?php

declare(strict_types=1);

namespace Database\Seeders\Usuarios;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UsuariosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $rolSupervisorId = (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id');
        $rolGestorId     = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        $supervisor = User::query()->updateOrCreate(
            ['email' => 'supervisor.demo@crm.local'],
            [
                'name'     => 'Supervisor Demo',
                'password' => Hash::make('password'),
                'activo'   => true,
            ],
        );

        $gestor = User::query()->updateOrCreate(
            ['email' => 'gestor.demo@crm.local'],
            [
                'name'     => 'Gestor Demo',
                'password' => Hash::make('password'),
                'activo'   => true,
            ],
        );

        $codigosProyectos = ['COBRANZA_DEMO_2026', 'SOPORTE_DEMO_2026', 'VENTA_DEMO_2026', 'SERVICIO_DEMO_2026'];

        $rows = [];
        foreach ($codigosProyectos as $codigo) {
            $proyectoId = (int) DB::table('proyectos')->where('codigo', $codigo)->value('id');
            if ($proyectoId === 0) {
                continue;
            }

            if ($rolSupervisorId > 0) {
                $rows[] = [
                    'usuario_id'  => $supervisor->id,
                    'proyecto_id' => $proyectoId,
                    'rol_id'      => $rolSupervisorId,
                    'equipo_id'   => null,
                    'activo'      => true,
                ];
            }
            if ($rolGestorId > 0) {
                $rows[] = [
                    'usuario_id'  => $gestor->id,
                    'proyecto_id' => $proyectoId,
                    'rol_id'      => $rolGestorId,
                    'equipo_id'   => null,
                    'activo'      => true,
                ];
            }
        }

        if ($rows === []) {
            return;
        }

        DB::table('usuario_proyecto_rol')->upsert(
            $rows,
            ['usuario_id', 'proyecto_id', 'rol_id'],
            ['equipo_id', 'activo'],
        );
    }
}
