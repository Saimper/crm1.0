<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presentación de un campo personalizado: a qué grupo pertenece y si se asoma
 * a la Vista de Trabajo.
 *
 * Van como columnas y no dentro de `reglas` (JSON) por tres motivos. `reglas`
 * es la bolsa de VALIDACIÓN —lo dice su evaluador— y esto no valida nada; §7 ya
 * guarda presentación en columnas (`etiqueta`, `descripcion`, `activo`,
 * `orden`); y las dos pantallas que editan definiciones reconstruyen ese JSON al
 * guardar, así que cualquier clave nueva que viviera ahí se borraría sola la
 * primera vez que alguien tocara el campo.
 *
 * Los defaults conservan el comportamiento de hoy para los 255 campos que ya
 * existen: sin grupo y visibles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campos_personalizados', function (Blueprint $table): void {
            $table->foreignId('grupo_campo_id')
                ->nullable()
                ->after('ambito_id')
                ->constrained('grupos_campo')
                ->nullOnDelete()
                ->restrictOnUpdate();

            $table->boolean('visible_en_gestion')
                ->default(true)
                ->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('campos_personalizados', function (Blueprint $table): void {
            $table->dropForeign(['grupo_campo_id']);
            $table->dropColumn(['grupo_campo_id', 'visible_en_gestion']);
        });
    }
};
