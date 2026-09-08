<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La auditoría sólo sabía de tres cosas: creado, actualizado, eliminado. Sacar
 * del sistema el padrón completo de personas de un cliente —que es la acción
 * con más peso en privacidad de toda la aplicación— no dejaba rastro en el
 * único sitio donde ese cliente puede comprobar quién tocó sus datos.
 *
 * `exportado` es el cuarto evento: quién, cuándo, qué tabla, con qué filtros y
 * cuántas filas. Lo escribe `RegistroDeExportaciones` desde cada descarga.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE auditorias MODIFY evento ENUM('creado','actualizado','eliminado','exportado') NOT NULL");
    }

    public function down(): void
    {
        DB::table('auditorias')->where('evento', 'exportado')->delete();
        DB::statement("ALTER TABLE auditorias MODIFY evento ENUM('creado','actualizado','eliminado') NOT NULL");
    }
};
