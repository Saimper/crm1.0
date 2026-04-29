<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla base del patrón CTI para compromisos (§6 CLAUDE.md v2).
 * Cada tipo (promesa_pago, resolucion_ticket, cierre_venta, accion_servicio) agrega
 * una tabla especializada compromisos_<tipo> con relación 1:1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compromisos', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('caso_id')
                ->constrained('casos')
                ->restrictOnDelete();

            // gestion_origen_id se crea sin FK porque la tabla gestiones se define en Fase 1.H;
            // allí se agregará la FK vía migración ALTER. Mientras, mantiene unique para la invariante
            // "una gestión origina a lo sumo un compromiso" (§6 CLAUDE.md v2).
            $table->unsignedBigInteger('gestion_origen_id')->nullable();

            $table->enum('tipo_compromiso', [
                'promesa_pago',
                'resolucion_ticket',
                'cierre_venta',
                'accion_servicio',
            ]);

            $table->enum('estado', ['pendiente', 'cumplido', 'roto', 'cancelado'])->default('pendiente');

            $table->date('fecha_vencimiento');
            $table->date('fecha_resolucion')->nullable();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->unique('gestion_origen_id', 'compromisos_gestion_origen_id_unique');
            $table->index(['proyecto_id', 'fecha_vencimiento', 'estado']);
            $table->index(['proyecto_id', 'caso_id']);
            $table->index(['proyecto_id', 'usuario_id', 'estado']);
            $table->index(['proyecto_id', 'tipo_compromiso', 'estado']);
            $table->index(['proyecto_id', 'eliminada_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compromisos');
    }
};
