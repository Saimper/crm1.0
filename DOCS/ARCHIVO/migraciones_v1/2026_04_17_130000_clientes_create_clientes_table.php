<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->enum('tipo_persona', ['fisica', 'juridica']);
            $table->foreignId('tipo_identificacion_id')
                ->constrained('tipos_identificacion')
                ->restrictOnDelete()
                ->restrictOnUpdate();
            $table->string('identificacion', 50)->unique();

            $table->string('nombres', 150)->nullable();
            $table->string('apellidos', 150)->nullable();
            $table->string('razon_social', 250)->nullable();
            $table->date('fecha_nacimiento')->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('eliminada_en')->nullable();

            $table->index('eliminada_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
