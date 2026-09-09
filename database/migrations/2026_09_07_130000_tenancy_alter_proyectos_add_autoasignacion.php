<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién se queda la cuenta cuando un asesor la gestiona.
 *
 * Hasta ahora una cuenta solo llegaba a un gestor por reparto del supervisor.
 * Si el asesor encontraba una cuenta buscando y registraba una promesa, la
 * cuenta seguía sin dueño: al día siguiente no aparecía en su bandeja y no había
 * forma de que supiera que tenía un compromiso pendiente.
 *
 * La bandera es POR PROYECTO y va en false por defecto: hay operaciones donde
 * repartir es decisión del supervisor y sólo suya, y cambiar eso sin pedirlo
 * sería cambiarle el modelo de trabajo a quien ya lo tiene montado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table): void {
            $table->boolean('permite_autoasignacion')
                ->default(false)
                ->after('tipo_operacion');
        });
    }

    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $table): void {
            $table->dropColumn('permite_autoasignacion');
        });
    }
};
