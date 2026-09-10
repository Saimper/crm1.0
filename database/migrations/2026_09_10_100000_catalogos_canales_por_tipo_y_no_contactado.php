<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canal_tipo_gestion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos');
            $table->foreignId('tipo_gestion_id')->constrained('tipos_gestion')->cascadeOnDelete();
            $table->foreignId('canal_id')->constrained('canales');
            $table->unique(['proyecto_id', 'tipo_gestion_id', 'canal_id'], 'canal_tipo_gestion_unique');
        });
        Schema::table('resultados', function (Blueprint $table): void {
            $table->boolean('es_no_contactado')->default(false)->after('es_contacto_efectivo');
        });
        DB::table('resultados')->where('codigo', 'NO_CONTACTADO')->where('es_contacto_efectivo', false)
            ->update(['es_no_contactado' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('canal_tipo_gestion');
        Schema::table('resultados', fn (Blueprint $table) => $table->dropColumn('es_no_contactado'));
    }
};
