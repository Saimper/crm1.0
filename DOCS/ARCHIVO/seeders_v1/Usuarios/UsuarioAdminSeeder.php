<?php

declare(strict_types=1);

namespace Database\Seeders\Usuarios;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UsuarioAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@crm.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'activo' => true,
            ],
        );

        $rolAdminId = (int) DB::table('roles')->where('codigo', 'ADMIN')->value('id');
        if ($rolAdminId > 0) {
            DB::table('usuario_rol')->upsert(
                [['usuario_id' => $admin->id, 'rol_id' => $rolAdminId]],
                ['usuario_id', 'rol_id'],
                ['usuario_id'],
            );
        }
    }
}
