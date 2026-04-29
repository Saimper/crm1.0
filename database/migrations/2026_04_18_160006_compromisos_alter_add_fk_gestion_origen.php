<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la FK real sobre compromisos.gestion_origen_id → gestiones.id.
 * Se difiere a este punto porque la tabla `gestiones` se creó en la migración previa
 * (ver migración 2026_04_18_160005). La columna ya existía en compromisos desde 1.G
 * pero sin constraint; ahora se refuerza a FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compromisos', function (Blueprint $table): void {
            $table->foreign('gestion_origen_id')
                ->references('id')
                ->on('gestiones')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('compromisos', function (Blueprint $table): void {
            $table->dropForeign(['gestion_origen_id']);
        });
    }
};
