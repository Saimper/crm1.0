<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Definición de campos personalizados §7 CLAUDE.md v2.
 * Ámbitos cerrados: caso|gestion|compromiso. Tipos cerrados (§7.2).
 * La unicidad del código es por (proyecto, ámbito, ámbito_id) — mismo código puede
 * existir en carteras distintas o tipos distintos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campos_personalizados', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->enum('ambito', ['caso', 'gestion', 'compromiso']);

            // `ambito_id` referencia según `ambito`:
            //   caso       → carteras.id
            //   gestion    → tipos_gestion.id
            //   compromiso → tipo_compromiso (enum de compromisos → usamos string)
            // No FK física porque polimórfico; integridad a nivel dominio.
            $table->unsignedBigInteger('ambito_id');

            $table->enum('tipo', [
                'texto_corto', 'texto_largo',
                'numero_entero', 'numero_decimal',
                'fecha', 'fecha_hora', 'booleano',
                'seleccion_unica', 'seleccion_multiple',
                'moneda',
            ]);

            $table->string('codigo', 80);
            $table->string('etiqueta', 200);
            $table->string('descripcion', 500)->nullable();
            $table->boolean('obligatorio')->default(false);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->json('reglas')->nullable();

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['proyecto_id', 'ambito', 'ambito_id', 'codigo'], 'campos_perso_unico');
            $table->index(['proyecto_id', 'ambito', 'ambito_id', 'activo', 'orden'], 'campos_perso_lectura');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campos_personalizados');
    }
};
