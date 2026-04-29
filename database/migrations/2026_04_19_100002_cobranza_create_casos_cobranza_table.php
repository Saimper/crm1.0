<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Especialización CTI 1:1 del caso para operaciones de cobranza (§3.2 CLAUDE.md v2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casos_cobranza', function (Blueprint $table): void {
            $table->unsignedBigInteger('caso_id')->primary();

            $table->foreign('caso_id')
                ->references('id')->on('casos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('numero_prestamo', 100);

            $table->char('moneda', 3)->default('USD');
            $table->decimal('monto_original', 15, 2);
            $table->decimal('saldo_capital', 15, 2);
            $table->decimal('saldo_interes', 15, 2)->default(0);
            $table->decimal('saldo_total', 15, 2);
            $table->decimal('cuota_mensual', 15, 2);
            $table->unsignedInteger('cuotas_totales');
            $table->unsignedInteger('cuotas_pagadas')->default(0);
            $table->unsignedInteger('dias_mora')->default(0);

            $table->foreignId('tramo_mora_id')
                ->nullable()
                ->constrained('tramos_mora')
                ->nullOnDelete();

            $table->date('fecha_desembolso');
            $table->date('fecha_vencimiento');

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['proyecto_id', 'numero_prestamo'], 'casos_cobranza_proyecto_numero_prestamo_unique');
            $table->index(['proyecto_id', 'tramo_mora_id']);
            $table->index(['proyecto_id', 'dias_mora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casos_cobranza');
    }
};
