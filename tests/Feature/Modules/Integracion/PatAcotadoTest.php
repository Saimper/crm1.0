<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Modules\Integracion\Application\UseCases\EmitirSanctumTokenDesdeJwt;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El token que el CRM entrega al wrapper nacía con `['*']` y sin caducidad.
 *
 * `createToken()` sin segundo argumento concede permiso para todo lo que la API
 * llegue a exponer, y con `sanctum.expiration = null` seguía siendo válido meses
 * después: aunque el mandante se hubiera desactivado y aunque su `sso_secret` se
 * hubiera rotado.
 */
final class PatAcotadoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_el_token_nace_con_habilidades_acotadas_y_no_con_comodin(): void
    {
        $usuario = $this->crearGestor($this->crearProyectoCobranza());

        $usuario->createToken('prueba', EmitirSanctumTokenDesdeJwt::HABILIDADES, now()->addMinutes(480));

        $abilities = json_decode(
            (string) DB::table('personal_access_tokens')->where('tokenable_id', $usuario->id)->value('abilities'),
            true,
        );

        $this->assertNotContains('*', $abilities, 'El comodín concede lo que la API exponga en el futuro.');
        $this->assertSame(['integracion:persona', 'auth:logout'], $abilities);
    }

    public function test_el_token_caduca(): void
    {
        $usuario = $this->crearGestor($this->crearProyectoCobranza());

        $usuario->createToken('prueba', EmitirSanctumTokenDesdeJwt::HABILIDADES, now()->addMinutes(480));

        $this->assertNotNull(
            DB::table('personal_access_tokens')->where('tokenable_id', $usuario->id)->value('expires_at'),
            'Sin caducidad, un token filtrado vale para siempre.'
        );
    }

    public function test_un_token_sin_la_habilidad_no_entra_al_preview_de_persona(): void
    {
        $usuario = $this->crearGestor($this->crearProyectoCobranza());
        $token = $usuario->createToken('recortado', ['auth:logout'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/integracion/persona?identificacion=1-2-3')
            ->assertForbidden();
    }

    public function test_un_token_con_la_habilidad_si_entra(): void
    {
        $usuario = $this->crearGestor($this->crearProyectoCobranza());
        $token = $usuario->createToken('bueno', EmitirSanctumTokenDesdeJwt::HABILIDADES)->plainTextToken;

        // Lo que importa es que el middleware de habilidades deja pasar; lo que
        // el endpoint responda sobre una identificación inventada es asunto suyo.
        $respuesta = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/integracion/persona?identificacion=1-2-3');

        $this->assertNotSame(403, $respuesta->getStatusCode(), 'El middleware de habilidades no debería cortar aquí.');
    }

    public function test_los_tokens_antiguos_con_comodin_siguen_funcionando(): void
    {
        $usuario = $this->crearGestor($this->crearProyectoCobranza());

        // Los PAT que ya están en circulación llevan `['*']`. Sanctum lo
        // interpreta como «todas las habilidades», así que exigirlas en las rutas
        // no invalida ninguno: el endurecimiento no corta al wrapper en marcha.
        $token = $usuario->createToken('antiguo', ['*'])->plainTextToken;

        $respuesta = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/integracion/persona?identificacion=1-2-3');

        $this->assertNotSame(403, $respuesta->getStatusCode(), 'El middleware de habilidades no debería cortar aquí.');
    }
}
