<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qué canales usa cada proyecto, en qué orden, con qué nombre y con qué reglas.
 *
 * `canales` sigue siendo el catálogo global que declara §8, y esta tabla es la
 * que dice qué hace cada proyecto con él: activarlo, ordenarlo, renombrarlo para
 * su operación, y las dos banderas de comportamiento que pedía el encargo.
 *
 * De las tres formas de bajar canales a por-proyecto, es la única que no toca
 * `gestiones.canal_id`: las gestiones históricas siguen apuntando a los mismos
 * ids, los joins de reportes y exportaciones siguen leyendo `canales.nombre`
 * sin enterarse, y el seeder conserva su unique por `codigo`. Añadir
 * `proyecto_id` nullable a `canales` habría chocado además con el global scope,
 * que aplica igualdad estricta y excluye los NULL; duplicar las filas por
 * proyecto habría reescrito la FK de las gestiones ya registradas.
 *
 * El backfill deja a todos los proyectos como estaban: todos los canales
 * globales activos y con su mismo orden. El día 1 nadie nota nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canal_proyecto', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('canal_id')
                ->constrained('canales')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            // Renombrar el canal para este proyecto sin forkear la fila global:
            // «Llamada saliente» donde el catálogo dice «Teléfono».
            $table->string('etiqueta', 150)->nullable();

            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);

            $table->boolean('requiere_duracion')->default(false);
            $table->boolean('permite_adjunto')->default(false);

            $table->timestamp('creada_en')->useCurrent();
            $table->timestamp('actualizada_en')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['proyecto_id', 'canal_id'], 'canal_proyecto_unique');
            $table->index(['proyecto_id', 'activo', 'orden']);
        });

        $ahora = now();
        $canales = DB::table('canales')->get(['id', 'orden', 'activo']);

        foreach (DB::table('proyectos')->pluck('id') as $proyectoId) {
            $filas = $canales->map(fn (object $canal): array => [
                'proyecto_id' => $proyectoId,
                'canal_id' => $canal->id,
                'etiqueta' => null,
                'activo' => (bool) $canal->activo,
                'orden' => (int) $canal->orden,
                'requiere_duracion' => false,
                'permite_adjunto' => false,
                'creada_en' => $ahora,
                'actualizada_en' => $ahora,
            ])->all();

            if ($filas !== []) {
                DB::table('canal_proyecto')->insert($filas);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('canal_proyecto');
    }
};
