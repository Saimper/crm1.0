<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La asignación deja de colgar de una campaña y pasa a ser lo que el código ya
 * daba por hecho: una cuenta tiene un dueño en su proyecto.
 *
 * El único era `(campana_id, caso_id)`, así que el esquema permitía dos dueños
 * vivos de la misma cuenta en campañas distintas — mientras `duenioActual`,
 * `VistaDeTrabajo::duenioDelCaso` y `Bandeja::consultaPool` resolvían el dueño
 * mirando sólo `caso_id`. Este cambio no relaja nada: endurece.
 *
 * MySQL no envuelve el DDL en transacción, así que un fallo a mitad deja pasos
 * aplicados sin escribir en `migrations`. Por eso cada paso comprueba antes de
 * actuar: reintentar es seguro.
 */
return new class extends Migration
{
    public function up(): void
    {
        // El deploy corre `migrate --force` sin compuerta: mejor rendirse en
        // diez segundos que dejar la tabla bloqueada detrás de una transacción
        // larga de la aplicación.
        DB::statement('SET SESSION lock_wait_timeout = 10');

        if (! Schema::hasColumn('asignaciones', 'campana_id')) {
            $this->crearUnico();

            return;
        }

        $this->dedupe();

        // La FK primero. `campana_id` no tiene índice propio: el que la
        // respalda es el único, y soltarlo antes que la FK da errno 1553.
        //
        // Cada paso pregunta si ya está hecho. No es defensa de manual: con el
        // `lock_wait_timeout` de arriba, basta con que una transacción de la
        // aplicación tenga la tabla tomada diez segundos para que uno de estos
        // ALTER muera; el DDL ya aplicado se queda —MySQL no lo envuelve en
        // transacción— pero la fila de `migrations` no se escribe. Sin estas
        // guardas, el reintento del siguiente despliegue entra otra vez por
        // aquí y revienta con errno 1091 sobre algo que ya no existe, y el
        // deploy se queda en bucle rojo hasta que alguien entre por SSH.
        if ($this->fkExiste('asignaciones_campana_id_foreign')) {
            Schema::table('asignaciones', function (Blueprint $t): void {
                $t->dropForeign('asignaciones_campana_id_foreign');
            });
        }
        if ($this->indiceExiste('asignaciones_campana_caso_unique')) {
            Schema::table('asignaciones', function (Blueprint $t): void {
                $t->dropUnique('asignaciones_campana_caso_unique');
            });
        }
        if ($this->indiceExiste('asignaciones_proyecto_id_campana_id_estado_index')) {
            Schema::table('asignaciones', function (Blueprint $t): void {
                $t->dropIndex('asignaciones_proyecto_id_campana_id_estado_index');
            });
        }
        Schema::table('asignaciones', function (Blueprint $t): void {
            $t->dropColumn('campana_id');
        });

        $this->crearUnico();
    }

    public function down(): void
    {
        if (DB::table('asignaciones')->exists()) {
            throw new RuntimeException(
                'No se puede revertir con datos dentro: la campaña de cada asignación se fue con la '
                .'columna y no hay de dónde recuperarla. Restaura desde copia de seguridad.'
            );
        }

        Schema::table('asignaciones', function (Blueprint $t): void {
            $t->dropUnique('asignaciones_proyecto_caso_unique');
            $t->foreignId('campana_id')->after('proyecto_id')->constrained('campanas')->restrictOnDelete();
            $t->index(['proyecto_id', 'campana_id', 'estado'], 'asignaciones_proyecto_id_campana_id_estado_index');
            $t->unique(['campana_id', 'caso_id'], 'asignaciones_campana_caso_unique');
        });
    }

    /**
     * Nada de DDL hasta que los datos admitan el único nuevo.
     *
     * Gana la fila VIVA, y sólo entre iguales manda el id más alto —que es el
     * criterio con el que la aplicación dice quién tiene la cuenta, en
     * `AutoasignarCaso::duenioActual`, `VistaDeTrabajo` y `ListadoCasos`—.
     *
     * El estado va primero por una razón concreta: si una cuenta tiene una
     * asignación en trabajo en la campaña A y otra cerrada en la B, quedarse
     * con la del id más alto le quitaría la cuenta al asesor que la está
     * trabajando y la dejaría con una fila cerrada, que desde este mismo cambio
     * significa fuera de circulación hasta que un supervisor la reabra.
     *
     * Los perdedores se copian antes de borrarse: `asignaciones` no tiene
     * borrado lógico.
     */
    private function dedupe(): void
    {
        $perdedores = <<<'SQL'
            SELECT id FROM (
                SELECT id, ROW_NUMBER() OVER (
                    PARTITION BY proyecto_id, caso_id
                    ORDER BY (estado = 'cerrada') ASC, id DESC
                ) AS rn FROM asignaciones
            ) AS t WHERE t.rn > 1
        SQL;

        if (! DB::table('asignaciones')->whereRaw("id IN ({$perdedores})")->exists()) {
            return;
        }

        DB::statement(
            'CREATE TABLE IF NOT EXISTS asignaciones_duplicadas_backup AS '
            ."SELECT * FROM asignaciones WHERE id IN ({$perdedores})"
        );
        DB::statement("DELETE FROM asignaciones WHERE id IN ({$perdedores})");
    }

    private function indiceExiste(string $nombre): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'asignaciones')
            ->where('INDEX_NAME', $nombre)
            ->exists();
    }

    private function fkExiste(string $nombre): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->whereRaw('CONSTRAINT_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'asignaciones')
            ->where('CONSTRAINT_NAME', $nombre)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    private function crearUnico(): void
    {
        if ($this->indiceExiste('asignaciones_proyecto_caso_unique')) {
            return;
        }

        Schema::table('asignaciones', function (Blueprint $t): void {
            $t->unique(['proyecto_id', 'caso_id'], 'asignaciones_proyecto_caso_unique');
        });
    }
};
