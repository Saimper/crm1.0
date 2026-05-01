<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F34D — elimina personas.hash_identidad.
 *
 * Columna sembrada como "dedupe técnica futura" en F1 pero sin uso real
 * verificable: ningún UseCase la setea, ningún repo la consulta, ningún
 * código de aplicación la referencia (verificado via grep en F34A §5 y
 * en F34D al cerrar deuda). Si en el futuro se necesita un hash para
 * dedupe cross-proyecto se reintroducirá poblándolo desde RegistrarPersona.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table): void {
            $table->dropIndex(['hash_identidad']);
            $table->dropColumn('hash_identidad');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table): void {
            $table->string('hash_identidad', 64)->nullable()->after('fecha_nacimiento');
            $table->index('hash_identidad');
        });
    }
};
