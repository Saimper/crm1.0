<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla base del patrón CTI (Class Table Inheritance) para casos.
 * Campos comunes a cualquier tipo de operación (cobranza, cx, venta, servicio).
 * Cada tipo agrega una tabla especializada casos_<tipo> con relación 1:1 a este PK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casos', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('cartera_id')
                ->constrained('carteras')
                ->restrictOnDelete();

            $table->foreignId('persona_id')
                ->constrained('personas')
                ->restrictOnDelete();

            $table->enum('tipo_caso', ['cobranza', 'ticket_cx', 'lead_venta', 'servicio']);

            $table->foreignId('estado_caso_id')
                ->constrained('estados_caso')
                ->restrictOnDelete();

            $table->date('fecha_ingreso');
            $table->unsignedInteger('prioridad')->default(100);
            $table->timestamp('cerrado_en')->nullable();

            // Desnormalización controlada §4.3 CLAUDE.md v2.
            // Alimentada por listeners de Gestiones y Compromisos en fases posteriores.
            $table->timestamp('fecha_ultima_gestion')->nullable();
            $table->unsignedBigInteger('resultado_ultima_gestion_id')->nullable();
            $table->foreignId('usuario_ultima_gestion_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('tiene_compromiso_vigente')->default(false);

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->index(['proyecto_id', 'cartera_id', 'persona_id']);
            $table->index(['proyecto_id', 'estado_caso_id']);
            $table->index(['proyecto_id', 'tipo_caso']);
            $table->index(['proyecto_id', 'fecha_ultima_gestion']);
            $table->index(['proyecto_id', 'tiene_compromiso_vigente']);
            $table->index(['proyecto_id', 'cerrado_en']);
            $table->index(['proyecto_id', 'eliminada_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casos');
    }
};
