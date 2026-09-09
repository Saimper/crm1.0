<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El espacio anti-replay pasa a ser de cada cliente.
 *
 * `sso_tokens_consumidos` tenía el `jti` como clave primaria a secas, así que
 * era un espacio de nombres GLOBAL: el wrapper del mandante B consumía un
 * identificador y, si el del mandante A generaba el mismo, su handshake se
 * rechazaba por «token ya usado». Un UUID v4 no colisiona por azar, pero el
 * identificador lo elige el wrapper, no el CRM: un cliente puede tumbar los
 * accesos de otro simplemente numerando sus tokens del 1 en adelante.
 *
 * La columna `mandante_id` existe desde F37 y es NOT NULL; lo único que faltaba
 * era que decidiera. La clave pasa a ser `(mandante_id, jti)`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sso_tokens_consumidos', function (Blueprint $tabla): void {
            $tabla->dropPrimary();
            $tabla->primary(['mandante_id', 'jti']);
        });
    }

    public function down(): void
    {
        // Volver atrás exige que no haya dos clientes con el mismo jti; si los
        // hay, la clave vieja no se puede reconstruir y el rollback falla, que
        // es mejor que borrar filas de anti-replay para hacer sitio.
        Schema::table('sso_tokens_consumidos', function (Blueprint $tabla): void {
            $tabla->dropPrimary(['mandante_id', 'jti']);
            $tabla->primary('jti');
        });
    }
};
