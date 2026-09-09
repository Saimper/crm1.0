<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * `contactos` gana `public_id` y `origen`.
 *
 * `public_id`: es la única entidad operativa sin él (§4 pide bigint interno más
 * ulid público). Con la tabla vacía en los tres proyectos el momento de
 * arreglarlo es ahora, antes de meterle decenas de miles de filas sacadas de la
 * importación; después habría que rellenarlo a posteriori.
 *
 * `origen`: de dónde salió el contacto. Un teléfono extraído de una celda de
 * texto no es lo mismo que uno que un gestor escribió mientras hablaba con la
 * persona, y cuando el primero resulte estar mal hay que poder distinguirlos
 * sin adivinar.
 *
 * El unique por persona, tipo y valor es lo que hace idempotente la extracción:
 * volver a importar el mismo fichero no duplica contactos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contactos', function (Blueprint $table): void {
            $table->char('public_id', 26)->nullable()->after('id');
            $table->string('origen', 20)->default('manual')->after('activo');
        });

        DB::table('contactos')->whereNull('public_id')->orderBy('id')->chunkById(500, function ($filas): void {
            foreach ($filas as $fila) {
                DB::table('contactos')->where('id', $fila->id)->update(['public_id' => (string) Str::ulid()]);
            }
        });

        Schema::table('contactos', function (Blueprint $table): void {
            $table->char('public_id', 26)->nullable(false)->change();
            $table->unique('public_id', 'contactos_public_id_unique');
            $table->unique(['persona_id', 'tipo', 'valor'], 'contactos_persona_tipo_valor_unique');
            $table->index(['proyecto_id', 'origen']);
        });
    }

    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $table): void {
            $table->dropUnique('contactos_public_id_unique');
            $table->dropUnique('contactos_persona_tipo_valor_unique');
            $table->dropIndex(['proyecto_id', 'origen']);
            $table->dropColumn(['public_id', 'origen']);
        });
    }
};
