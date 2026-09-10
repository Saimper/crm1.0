<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $names = [
            'ver' => 'Ver carteras', 'crear' => 'Crear carteras',
            'editar' => 'Editar y desactivar carteras', 'eliminar' => 'Eliminar carteras (conservar historial)',
        ];
        foreach ($names as $action => $name) {
            DB::table('permisos')->insertOrIgnore([
                'codigo' => 'carteras.'.$action, 'nombre' => $name, 'grupo' => 'carteras', 'activo' => true,
            ]);
            $permissionId = DB::table('permisos')->where('codigo', 'carteras.'.$action)->value('id');
            $roles = $action === 'ver' ? ['ADMIN_GLOBAL', 'ADMIN_MANDANTE', 'SUPERVISOR'] : ['ADMIN_GLOBAL', 'ADMIN_MANDANTE'];
            foreach (DB::table('roles')->whereIn('codigo', $roles)->pluck('id') as $roleId) {
                DB::table('rol_permiso')->insertOrIgnore(['rol_id' => $roleId, 'permiso_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        // Keep permissions and explicit project grants when rolling back application code.
    }
};
