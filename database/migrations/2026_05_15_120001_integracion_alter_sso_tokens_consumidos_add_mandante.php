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
        Schema::table('sso_tokens_consumidos', function (Blueprint $table): void {
            $table->foreignId('mandante_id')
                ->nullable()
                ->after('jti')
                ->constrained('mandantes')
                ->cascadeOnDelete();
        });

        // Backfill mandante_id desde proyecto.mandante_id para filas existentes.
        DB::statement('
            UPDATE sso_tokens_consumidos stc
            JOIN proyectos p ON p.id = stc.proyecto_id
            SET stc.mandante_id = p.mandante_id
            WHERE stc.mandante_id IS NULL
        ');

        Schema::table('sso_tokens_consumidos', function (Blueprint $table): void {
            // Tras backfill, mandante_id pasa a ser obligatorio.
            $table->foreignId('mandante_id')->nullable(false)->change();
            $table->foreignId('proyecto_id')->nullable()->change();

            $table->index(['mandante_id', 'expira_en']);
        });
    }

    public function down(): void
    {
        Schema::table('sso_tokens_consumidos', function (Blueprint $table): void {
            $table->dropIndex(['mandante_id', 'expira_en']);
            $table->dropForeign(['mandante_id']);
            $table->dropColumn('mandante_id');
            $table->foreignId('proyecto_id')->nullable(false)->change();
        });
    }
};
