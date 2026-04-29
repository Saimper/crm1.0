<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('numero_prestamo', 80)->unique();

            $table->foreignId('cartera_id')
                ->constrained('carteras')
                ->restrictOnDelete();
            $table->foreignId('estado_producto_id')
                ->constrained('estados_producto')
                ->restrictOnDelete();
            $table->foreignId('tramo_mora_id')
                ->nullable()
                ->constrained('tramos_mora')
                ->nullOnDelete();

            $table->decimal('monto_original', 15, 2);
            $table->decimal('saldo_capital', 15, 2);
            $table->decimal('saldo_total', 15, 2);
            $table->decimal('cuota_mensual', 15, 2);
            $table->unsignedInteger('dias_mora')->default(0);
            $table->unsignedSmallInteger('cuotas_totales');
            $table->unsignedSmallInteger('cuotas_pagadas')->default(0);
            $table->char('moneda', 3)->default('USD');
            $table->date('fecha_desembolso');
            $table->date('fecha_vencimiento');

            // Desnormalización controlada — §3.3 CLAUDE.md
            $table->timestamp('fecha_ultima_gestion')->nullable();
            $table->foreignId('resultado_ultima_gestion_id')
                ->nullable()
                ->constrained('resultados')
                ->nullOnDelete();
            $table->foreignId('usuario_ultima_gestion_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('tiene_promesa_vigente')->default(false);

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->index('cliente_id');
            $table->index(['estado_producto_id', 'tramo_mora_id']);
            $table->index('fecha_ultima_gestion');
            $table->index('tiene_promesa_vigente');
            $table->index('eliminada_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
