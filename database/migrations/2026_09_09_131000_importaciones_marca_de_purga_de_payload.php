<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de cuándo se depuró el contenido del archivo de una importación.
 *
 * `importacion_filas.payload` guarda la fila cruda que subió el cliente —cédula,
 * teléfonos, saldos— y hasta ahora no caducaba nunca: en la base de desarrollo
 * son 84 MB de datos personales de hace meses que ya no le sirven a nadie. La
 * purga (`importaciones:purgar-payloads`) los sustituye por un objeto vacío
 * pasados los días de retención, y esta columna es la que dice que eso pasó.
 *
 * Hace falta una marca y no basta con mirar el payload: una fila con `{}` y una
 * importación depurada entera se ven igual desde abajo, y la pantalla necesita
 * saberlo para no ofrecer una descarga de filas rechazadas que saldría vacía.
 *
 * El índice es para la propia purga, que busca importaciones terminadas y sin
 * depurar; sin él, cada pasada nocturna recorre la tabla entera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('importaciones', function (Blueprint $tabla): void {
            $tabla->timestamp('payload_purgado_en')->nullable()->after('terminado_en');
            $tabla->index(['payload_purgado_en', 'terminado_en'], 'importaciones_purga_payload_idx');
        });
    }

    public function down(): void
    {
        Schema::table('importaciones', function (Blueprint $tabla): void {
            $tabla->dropIndex('importaciones_purga_payload_idx');
            $tabla->dropColumn('payload_purgado_en');
        });
    }
};
