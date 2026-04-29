<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opciones_campo_personalizado', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('campo_personalizado_id')
                ->constrained('campos_personalizados')
                ->cascadeOnDelete();

            $table->string('codigo', 80);
            $table->string('etiqueta', 200);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['campo_personalizado_id', 'codigo'], 'opciones_cp_codigo_unique');
            $table->index(['campo_personalizado_id', 'activo', 'orden'], 'opciones_cp_lectura');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opciones_campo_personalizado');
    }
};
