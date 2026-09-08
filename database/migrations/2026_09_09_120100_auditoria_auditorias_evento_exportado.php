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
 *
 * No tiene vuelta atrás, y es deliberado. Estrechar el ENUM exige que no quede
 * ninguna fila con `exportado`, así que un `down()` honesto tendría que
 * borrarlas: un `migrate:rollback` —el gesto reflejo cuando un despliegue sale
 * mal, y el deploy corre `migrate --force` solo— destruiría el registro de
 * quién sacó datos del sistema, que es justo lo que esta columna existe para
 * que no se pueda destruir. El `down()` deja el ENUM como está y lo dice.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE auditorias MODIFY evento ENUM('creado','actualizado','eliminado','exportado') NOT NULL");
    }

    public function down(): void
    {
        // A propósito, nada. Volver atrás significaría borrar las huellas de
        // exportación, y una auditoría que se puede deshacer no es una
        // auditoría. Un ENUM con un valor de más no le estorba a nadie.
    }
};
