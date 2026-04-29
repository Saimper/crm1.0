<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contactos', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->cascadeOnDelete()
                ->restrictOnUpdate();

            $table->enum('tipo', ['telefono', 'correo', 'direccion']);
            $table->string('valor', 250);
            $table->string('etiqueta', 100)->nullable();
            $table->boolean('es_principal')->default(false);
            $table->boolean('activo')->default(true);

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->index(['cliente_id', 'tipo', 'activo']);
            $table->index(['cliente_id', 'es_principal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactos');
    }
};
