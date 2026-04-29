<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promesas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();
            $table->foreignId('gestion_origen_id')
                ->unique()
                ->constrained('gestiones')
                ->restrictOnDelete();
            $table->foreignId('usuario_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('tipo_pago_id')
                ->nullable()
                ->constrained('tipos_pago')
                ->nullOnDelete();

            $table->decimal('monto_promesa', 15, 2);
            $table->date('fecha_promesa');
            $table->enum('estado', ['pendiente', 'cumplida', 'rota', 'cancelada'])->default('pendiente');
            $table->date('fecha_resolucion')->nullable();
            $table->text('notas')->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            // Índice obligatorio §3.5
            $table->index(['fecha_promesa', 'estado']);
            $table->index('producto_id');
            $table->index(['usuario_id', 'estado']);
            $table->index('eliminada_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promesas');
    }
};
