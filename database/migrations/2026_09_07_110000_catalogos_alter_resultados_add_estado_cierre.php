<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un resultado de gestión puede cerrar el caso, y en qué estado terminal lo deja.
 *
 * `EditarCaso` documenta desde el principio que `estado_caso_id` es inmutable
 * "porque las transiciones se hacen vía gestiones", pero esa lógica nunca se
 * escribió: `resultados` tenía `es_contacto_efectivo` y `requiere_compromiso`
 * y ninguna columna que dijera que un resultado da el caso por terminado. Sin
 * ella el UseCase CerrarCaso quedaba sin quien lo invocara y ningún caso podía
 * salir de su estado inicial.
 *
 * Nullable a propósito: la inmensa mayoría de resultados NO cierran el caso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resultados', function (Blueprint $table): void {
            $table->foreignId('estado_caso_cierre_id')
                ->nullable()
                ->after('requiere_compromiso')
                ->constrained('estados_caso')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('resultados', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('estado_caso_cierre_id');
        });
    }
};
