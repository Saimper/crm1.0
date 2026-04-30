<?php

declare(strict_types=1);

namespace Database\Seeders\Usuarios;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UsuarioAdminGlobalSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@crm.local'],
            [
                'name' => 'Administrador Global',
                'password' => Hash::make('password'),
                'activo' => true,
            ],
        );

        $rolAdminGlobalId = (int) DB::table('roles')->where('codigo', 'ADMIN_GLOBAL')->value('id');
        if ($rolAdminGlobalId === 0) {
            return;
        }

        DB::table('usuario_global_rol')->upsert(
            [['usuario_id' => $admin->id, 'rol_id' => $rolAdminGlobalId]],
            ['usuario_id', 'rol_id'],
            ['usuario_id'],
        );
    }
}
