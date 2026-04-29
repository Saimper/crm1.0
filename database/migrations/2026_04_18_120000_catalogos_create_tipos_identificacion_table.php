<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo GLOBAL de tipos de identificación (§8.1 CLAUDE.md v2).
 * No lleva proyecto_id — es compartido por toda la plataforma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_identificacion', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->string('pais', 3)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->index(['activo', 'orden']);
            $table->index('pais');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_identificacion');
    }
};
