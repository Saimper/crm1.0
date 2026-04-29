<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo global de tipos de documento/archivo que el sistema puede almacenar
 * como adjuntos (PDF, imagen, etc.). Usado a partir de módulos que manejen
 * archivos (importaciones, adjuntos a gestiones, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_documento', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 100);
            $table->string('extension', 10);
            $table->string('mime_type', 100);
            $table->unsignedInteger('tamano_max_mb')->default(10);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->index(['activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_documento');
    }
};
