<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Especialización CTI 1:1 del compromiso para cobranza: promesa de pago.
 * El tipo_compromiso en la tabla base es 'promesa_pago'; aquí viven sus datos específicos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compromisos_promesa_pago', function (Blueprint $table): void {
            $table->unsignedBigInteger('compromiso_id')->primary();

            $table->foreign('compromiso_id')
                ->references('id')->on('compromisos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->decimal('monto', 15, 2);
            $table->char('moneda', 3)->default('USD');

            $table->foreignId('tipo_pago_id')
                ->nullable()
                ->constrained('tipos_pago')
                ->nullOnDelete();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->index(['proyecto_id', 'tipo_pago_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compromisos_promesa_pago');
    }
};
