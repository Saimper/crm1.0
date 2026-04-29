<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permisos', function (Blueprint $table): void {
            $table->id();
            // codigo con formato recurso.accion — ej: gestiones.crear, promesas.resolver
            $table->string('codigo', 80)->unique();
            $table->string('nombre', 150);
            $table->string('grupo', 50)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->index(['grupo', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos');
    }
};
