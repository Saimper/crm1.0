<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grupos con los que se ordenan los campos personalizados en pantalla.
 *
 * Es un catálogo por proyecto y no una columna de texto libre a propósito. §13.2
 * prohíbe el texto libre justo para lo que el negocio agrupa, y agrupar es lo
 * único que hace esto: con 255 campos repartidos en tres proyectos y varios
 * administradores, un `varchar` produciría «Saldos», «saldos» y «Saldo » como
 * tres grupos distintos el primer mes.
 *
 * Y es sólo eso: un nombre y un orden. No hay lienzo, ni posiciones, ni anchos,
 * ni iconos. El acordeón lo dibuja el desarrollador; el administrador únicamente
 * dice a qué grupo pertenece cada campo, que es la misma clase de decisión que
 * `orden`, ya permitida por §1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_campo', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('codigo', 50);
            $table->string('nombre', 150);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['proyecto_id', 'codigo'], 'grupos_campo_proyecto_codigo_unique');
            $table->index(['proyecto_id', 'activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos_campo');
    }
};
