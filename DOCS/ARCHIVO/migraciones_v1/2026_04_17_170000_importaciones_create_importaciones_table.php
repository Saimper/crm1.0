<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importaciones', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->enum('tipo', ['clientes', 'productos', 'asignaciones', 'contactos']);
            $table->string('archivo', 500);
            $table->foreignId('usuario_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->enum('estado', [
                'subida',
                'validando',
                'validada',
                'procesando',
                'completada',
                'con_errores',
                'cancelada',
            ])->default('subida');

            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('filas_validas')->default(0);
            $table->unsignedInteger('filas_invalidas')->default(0);
            $table->unsignedInteger('filas_procesadas')->default(0);

            $table->timestamp('iniciada_en')->nullable();
            $table->timestamp('completada_en')->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->index(['usuario_id', 'estado']);
            $table->index(['tipo', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones');
    }
};
