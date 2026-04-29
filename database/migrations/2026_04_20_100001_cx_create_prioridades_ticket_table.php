<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prioridades_ticket', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('codigo', 50);
            $table->string('nombre', 150);
            $table->unsignedInteger('peso')->default(100);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(100);

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['proyecto_id', 'codigo'], 'prioridades_ticket_proyecto_codigo_unique');
            $table->index(['proyecto_id', 'activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prioridades_ticket');
    }
};
