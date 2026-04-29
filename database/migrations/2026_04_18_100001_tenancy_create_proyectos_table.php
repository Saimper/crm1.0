<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyectos', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('mandante_id')
                ->constrained('mandantes')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('codigo', 80);
            $table->string('nombre', 200);
            $table->string('descripcion', 1000)->nullable();
            $table->enum('tipo_operacion', ['cobranza', 'cx', 'venta', 'servicio']);
            $table->boolean('activo')->default(true);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->unique(['mandante_id', 'codigo'], 'proyectos_mandante_codigo_unique');
            $table->index(['tipo_operacion', 'activo']);
            $table->index(['mandante_id', 'activo']);
            $table->index('eliminada_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectos');
    }
};
