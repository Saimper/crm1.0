<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registra qué mandante provisionó cada usuario por SSO.
 *
 * Sin esta columna no hay forma de saber a qué tenant "pertenece" un usuario
 * recién creado que todavía no tiene pivot: el AutenticadorPorJwt resolvía la
 * identidad solo por email, de modo que cualquier mandante con su propio
 * sso_secret podía firmar un JWT con el email de un usuario de otro mandante.
 *
 * Es aditiva y nullable: los usuarios creados a mano (sin SSO) la dejan en
 * NULL y siguen validándose por sus pivots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('mandante_origen_id')
                ->nullable()
                ->after('sso_provisioned')
                ->constrained('mandantes')
                ->nullOnDelete();
        });

        // Backfill conservador: solo cuando el usuario está ligado a UN único
        // mandante (vía pivot de mandante o de proyecto). Si abarca varios, se
        // deja NULL a propósito — la comprobación por pivot ya lo cubre y
        // adivinar un origen sería inventar dato.
        $mandantesPorUsuario = DB::table('usuario_mandante_rol')
            ->where('activo', true)
            ->select('usuario_id', 'mandante_id')
            ->union(
                DB::table('usuario_proyecto_rol as upr')
                    ->join('proyectos as p', 'p.id', '=', 'upr.proyecto_id')
                    ->where('upr.activo', true)
                    ->select('upr.usuario_id', 'p.mandante_id')
            )
            ->union(
                DB::table('usuario_proyecto_rol_custom as uprc')
                    ->join('proyectos as p2', 'p2.id', '=', 'uprc.proyecto_id')
                    ->where('uprc.activo', true)
                    ->select('uprc.usuario_id', 'p2.mandante_id')
            )
            ->get()
            ->groupBy('usuario_id');

        foreach ($mandantesPorUsuario as $usuarioId => $filas) {
            $unicos = $filas->pluck('mandante_id')->unique();

            if ($unicos->count() !== 1) {
                continue;
            }

            DB::table('users')
                ->where('id', $usuarioId)
                ->update(['mandante_origen_id' => (int) $unicos->first()]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('mandante_origen_id');
        });
    }
};
