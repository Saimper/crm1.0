<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frases hechas para el campo de notas, por proyecto.
 *
 * Las «chips de plantillas» que pedía el encargo no son un detalle de UI: son un
 * catálogo. Hardcodearlas es inútil —cada mandante habla distinto, y en cobranza
 * no se escribe lo mismo que en soporte—, así que van por proyecto como el resto
 * (§8), con su código, su orden y su pantalla.
 *
 * `resultado_id` opcional: una plantilla puede ser general o colgar de un
 * resultado concreto, que es cuando de verdad ahorra escribir —«El titular pide
 * que se le llame después de las 6» sólo tiene sentido bajo «Contacto con
 * titular»—. Las generales salen siempre.
 *
 * Lo que NO es: no ejecuta nada, no rellena otros campos, no condiciona el
 * formulario. Es texto que se pega en un textarea, y el gestor lo edita después.
 * En cuanto alguien pida contar su uso, eso es §5 y §13.2 y hay que hablarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_nota', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('resultado_id')
                ->nullable()
                ->constrained('resultados')
                ->cascadeOnDelete()
                ->restrictOnUpdate();

            $table->string('etiqueta', 60);
            $table->string('texto', 500);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->index(['proyecto_id', 'activo', 'orden'], 'plantillas_nota_lectura');
            $table->index(['proyecto_id', 'resultado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_nota');
    }
};
