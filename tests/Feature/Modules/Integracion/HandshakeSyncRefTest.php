<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Writeback CRM→ViciDial — captura del claim opcional `sync_ref` en el handshake.
 * Se persiste junto al `mandante_id` del MISMO handshake para el webhook posterior.
 */
final class HandshakeSyncRefTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    private \stdClass $mandante;

    private \stdClass $proyecto;

    private string $secret;

    private int $proyectoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->mandante = $this->crearMandante();
        $this->proyecto = $this->crearProyectoCobranza($this->mandante);
        $this->secret = (string) $this->mandante->sso_secret;
        $this->proyectoId = (int) $this->proyecto->id;
    }

    public function test_handshake_con_sync_ref_lo_guarda_en_sesion_con_mandante(): void
    {
        $syncRef = Str::uuid()->toString();

        $jwt = $this->firmar([
            'sub' => 'gestor.sync@wrapper.io',
            'name' => 'Gestor Sync',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $this->mandante->id,
            'proyecto_id' => $this->proyectoId,
            'sync_ref' => $syncRef,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $response = $this->get("/integracion/handshake?token={$jwt}");

        $response->assertSessionHas('crm_sync_ref', $syncRef);
        $response->assertSessionHas('crm_mandante_id', (int) $this->mandante->id);
    }

    public function test_handshake_sin_sync_ref_no_setea_contexto_de_writeback(): void
    {
        $jwt = $this->firmar([
            'sub' => 'gestor.nosync@wrapper.io',
            'name' => 'Gestor Sin Sync',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $this->mandante->id,
            'proyecto_id' => $this->proyectoId,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ]);

        $response = $this->get("/integracion/handshake?token={$jwt}");

        $response->assertRedirect("/proyectos/{$this->proyectoId}/bandeja");
        $response->assertSessionMissing('crm_sync_ref');
        $response->assertSessionMissing('crm_mandante_id');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function firmar(array $claims): string
    {
        return JWT::encode($claims, $this->secret, 'HS256');
    }
}
