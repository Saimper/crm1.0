<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caso_cartera_movimientos', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('proyecto_id')->constrained('proyectos')->restrictOnDelete();
            $table->foreignId('caso_id')->constrained('casos')->restrictOnDelete();
            $table->foreignId('cartera_origen_id')->constrained('carteras')->restrictOnDelete();
            $table->foreignId('cartera_destino_id')->constrained('carteras')->restrictOnDelete();
            $table->foreignId('importacion_id')->nullable()->constrained('importaciones')->nullOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('asesor_anterior_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('trasladada_en');
            $table->json('instantanea');
            $table->index(['proyecto_id', 'caso_id', 'trasladada_en'], 'movimientos_caso_fecha_idx');
            $table->index(['proyecto_id', 'cartera_origen_id', 'id'], 'movimientos_origen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caso_cartera_movimientos');
    }
};
