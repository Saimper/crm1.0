<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `auditorias` no sabía de qué cliente era cada evento.
 *
 * El mandante sólo se deducía saltando a `proyectos`, con tres consecuencias
 * que se pagaban en la pantalla:
 *
 *  a) filtrar por cliente exigía recalcular un IN de ids de proyecto en cada
 *     render;
 *  b) un evento con `proyecto_id = NULL` —toda acción administrativa— no era
 *     de nadie: el `whereIn(proyecto_id, ...)` del listado lo descartaba, así
 *     que su dueño legítimo no lo veía y sólo ADMIN_GLOBAL llegaba a él;
 *  c) si un proyecto cambiaba de mandante, el historial de auditoría se
 *     reescribía solo — justo lo contrario de lo que un registro de auditoría
 *     promete.
 *
 * La columna es NULLABLE a propósito: las filas históricas se rellenan aquí
 * desde su proyecto, y las que no tienen proyecto se quedan sin atribuir en vez
 * de inventarles un dueño. `AlcanceAuditoria` sabe leer los tres casos.
 *
 * `nullOnDelete` y no `cascadeOnDelete`: borrar un cliente no puede borrar la
 * prueba de lo que se hizo con sus datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias', function (Blueprint $table): void {
            $table->foreignId('mandante_id')
                ->nullable()
                ->after('public_id')
                ->constrained('mandantes')
                ->nullOnDelete();

            $table->index(['mandante_id', 'creada_en'], 'auditorias_mandante_fecha_idx');
        });

        // Backfill del histórico: el mandante que tenía el proyecto en el
        // momento de migrar. Subconsulta correlacionada sobre OTRA tabla, que
        // es lo único portable entre MySQL y SQLite.
        DB::table('auditorias')
            ->whereNotNull('proyecto_id')
            ->update([
                'mandante_id' => DB::raw(
                    '(select p.mandante_id from proyectos p where p.id = auditorias.proyecto_id)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table): void {
            $table->dropIndex('auditorias_mandante_fecha_idx');
            $table->dropConstrainedForeignId('mandante_id');
        });
    }
};
