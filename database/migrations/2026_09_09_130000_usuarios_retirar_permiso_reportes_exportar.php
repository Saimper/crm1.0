<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retira `reportes.exportar`, un permiso que no gobernaba nada.
 *
 * Estaba sembrado desde el principio y repartido a SUPERVISOR, AUDITOR y
 * ADMIN_MANDANTE, pero ninguna ruta, middleware, Livewire, Blade ni Gate lo
 * comprobaba jamás: `git log -S` lo confirma en todo el historial. La descarga
 * de reportes operativos la protege `gestiones.exportar`, que es lo honesto,
 * porque ese CSV son gestiones crudas con identificación, nombre y notas.
 *
 * No se cablea a esa descarga por dos razones. La regla que fijó la ola 04 es
 * que cada listado lleva el permiso de SU módulo, y moverlo rompería la
 * simetría con personas, casos y compromisos. Y ya existe
 * `reportes.constructor.exportar` (F32), que significa exactamente «exportar
 * desde reportes»: dos permisos casi homónimos en el mismo grupo es lo que
 * quien monta un rol custom no puede distinguir en el selector.
 *
 * Se APAGA (`activo = 0`) en vez de borrarse. `permisos` tiene dos FKs con
 * ON DELETE CASCADE (`rol_permiso` y `rol_custom_permiso`), así que un DELETE
 * se llevaría por delante, en silencio, cualquier rol custom que un cliente
 * hubiera montado apoyándose en él. Apagarlo revoca igual —las tres rutas de
 * evaluación de `User::tienePermiso` filtran por `p.activo`— y además lo saca
 * del selector, pero es reversible con un UPDATE.
 *
 * La fila sale también del seeder: si se quedara ahí, el `upsert` de cada
 * despliegue la volvería a encender y desharía esta migración.
 */
return new class extends Migration
{
    private const CODIGO = 'reportes.exportar';

    public function up(): void
    {
        DB::table('permisos')->where('codigo', self::CODIGO)->update(['activo' => false]);
    }

    public function down(): void
    {
        DB::table('permisos')->where('codigo', self::CODIGO)->update(['activo' => true]);
    }
};
