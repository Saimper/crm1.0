<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué resultados puede dar cada tipo de gestión.
 *
 * El selector de «Resultado» mostraba los nueve resultados del proyecto sin
 * importar el tipo elegido, porque no había ninguna relación entre las dos
 * tablas. «Promesa de pago fraccionado» aparecía como resultado posible de un
 * «No contactado».
 *
 * Es una lista blanca de pares, de la misma clase que las banderas
 * `requiere_compromiso` y `requiere_causa` que ya son configurables por
 * proyecto: no ejecuta lógica de usuario y no abre §13.14. La línea a no cruzar
 * es la siguiente: «si el resultado es X entonces el campo Y es obligatorio»
 * sería un motor de reglas y hay que negarlo.
 *
 * `proyecto_id` va en la tabla aunque las dos puntas ya sean por proyecto: §4
 * exige que toda tabla scoped abra su índice compuesto con él, y tenerlo permite
 * cortar de raíz un par que cruce proyectos.
 *
 * Arranque en abierto y POR TIPO, no por proyecto: un tipo de gestión sin
 * ninguna fila aquí admite todos los resultados. Con la regla al revés, los
 * proyectos que hoy no tienen esto configurado —que son todos— se quedarían sin
 * poder registrar una gestión el día del despliegue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultado_tipo_gestion', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('tipo_gestion_id')
                ->constrained('tipos_gestion')
                ->cascadeOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('resultado_id')
                ->constrained('resultados')
                ->cascadeOnDelete()
                ->restrictOnUpdate();

            $table->unsignedInteger('orden')->default(0);

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['proyecto_id', 'tipo_gestion_id', 'resultado_id'], 'resultado_tipo_gestion_unique');
            $table->index(['proyecto_id', 'tipo_gestion_id', 'orden'], 'resultado_tipo_gestion_lectura');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultado_tipo_gestion');
    }
};
