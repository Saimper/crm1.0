<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retira la campaña: sus seis permisos y su tabla.
 *
 * De sus once columnas sólo se leía `estado`, y en un único sitio: elegir a qué
 * campaña colgar una asignación. `fecha_inicio`, `fecha_fin` y el resto no
 * entraban en ningún `WHERE` de la aplicación. No tenía `cartera_id`, ni meta,
 * ni criterio de selección, así que estructuralmente no podía agrupar nada, y
 * ni un solo fichero de `app/Modules/Reportes` la mencionaba: no existía un
 * informe por campaña. Lo único que sostenía era el único
 * `(campana_id, caso_id)`, que la migración anterior sustituye por
 * `(proyecto_id, caso_id)`.
 *
 * Que vuelva el día que el negocio pida tandas de verdad es `asignaciones.
 * lote_id`, con criterio y reportería, no resucitar esta entidad.
 *
 * Los permisos se APAGAN, no se borran: `permisos` tiene dos FKs con
 * ON DELETE CASCADE (`rol_permiso` y `rol_custom_permiso`), así que un DELETE
 * se llevaría por delante, en silencio, cualquier rol custom que un cliente
 * hubiera montado apoyándose en ellos. Apagarlos revoca igual —las tres rutas
 * de `User::tienePermiso` filtran por `p.activo`— y los saca del selector.
 * Mismo criterio que `2026_09_09_130000_usuarios_retirar_permiso_reportes_exportar`.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'campanas.ver',
        'campanas.crear',
        'campanas.editar',
        'campanas.eliminar',
        'campanas.gestionar',
        'campanas.administrar',
    ];

    public function up(): void
    {
        DB::table('permisos')->whereIn('codigo', self::PERMISOS)->update(['activo' => false]);

        Schema::dropIfExists('campanas');
    }

    /**
     * Se recupera la forma, no el contenido: las campañas que hubiera se fueron
     * con el `DROP`. Sirve para que `migrate:rollback` no deje el esquema a
     * medias, no para volver atrás en producción.
     */
    public function down(): void
    {
        if (! Schema::hasTable('campanas')) {
            Schema::create('campanas', function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('proyecto_id')->constrained('proyectos')->restrictOnDelete()->restrictOnUpdate();
                $table->string('codigo', 80);
                $table->string('nombre', 200);
                $table->string('descripcion', 1000)->nullable();
                $table->enum('estado', ['programada', 'activa', 'pausada', 'finalizada'])->default('programada');
                $table->date('fecha_inicio');
                $table->date('fecha_fin')->nullable();
                $table->foreignId('creada_por_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('creada_en')->useCurrent();
                $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
                $table->timestamp('eliminada_en')->nullable();
                $table->unique(['proyecto_id', 'codigo'], 'campanas_proyecto_codigo_unique');
                $table->index(['proyecto_id', 'estado', 'fecha_inicio']);
                $table->index(['proyecto_id', 'eliminada_en']);
            });
        }

        DB::table('permisos')->whereIn('codigo', self::PERMISOS)->update(['activo' => true]);
    }
};
