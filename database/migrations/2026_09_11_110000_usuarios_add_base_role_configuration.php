<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->timestamp('configurado_en')->nullable();
        });
        Schema::create('rol_proyecto_permiso', function (Blueprint $table): void {
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('rol_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('permiso_id')->constrained('permisos')->restrictOnDelete();
            $table->boolean('permitido');
            $table->primary(['proyecto_id', 'rol_id', 'permiso_id'], 'rol_proyecto_permiso_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_proyecto_permiso');
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('configurado_en');
        });
    }
};
