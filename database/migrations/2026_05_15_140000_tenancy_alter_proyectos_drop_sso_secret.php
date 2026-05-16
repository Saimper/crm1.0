<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F37c: drop columna proyectos.sso_secret. F37 movió el secret a mandantes;
 * esta columna queda muerta y se elimina ahora que wrapper migró completo.
 *
 * Down: recrea columna nullable + índice. NO backfillea (la fuente de verdad
 * histórica está en mandantes.sso_secret y no se puede deducir per-proyecto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table): void {
            $table->dropIndex(['sso_secret']);
            $table->dropColumn('sso_secret');
        });
    }

    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table): void {
            $table->string('sso_secret', 64)->nullable()->after('id');
            $table->index('sso_secret');
        });
    }
};
