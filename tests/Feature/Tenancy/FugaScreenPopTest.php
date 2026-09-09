<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Screen-pop (handshake con identificación): la ficha sólo se abre dentro del
 * proyecto que dice el JWT. La misma cédula en otro proyecto del mandante, o
 * en otro mandante, no existe para ese handshake, y el aviso de «no
 * encontrada» no puede filtrar nada de la persona ajena.
 */
final class FugaScreenPopTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_identificacion_de_otro_proyecto_del_mismo_mandante_no_abre_ficha(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCobranza($mandante);
        $ajena = $this->crearPersonaEn($proyectoB, '31313131');

        $response = $this->get('/integracion/handshake?token='.$this->firmar($mandante, $proyectoA, '31313131'));

        $response->assertRedirect("/proyectos/{$proyectoA->id}/bandeja?sin_persona=31313131")
            ->assertSessionMissing('crm_persona_public_id');
        $this->assertStringNotContainsString($ajena->public_id, (string) $response->headers->get('Location'));
    }

    public function test_identificacion_de_otro_mandante_no_abre_ficha(): void
    {
        $mandanteA = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandanteA);
        $mandanteB = $this->crearMandante();
        $proyectoB = $this->crearProyectoCobranza($mandanteB);
        $ajena = $this->crearPersonaEn($proyectoB, '32323232');

        $response = $this->get('/integracion/handshake?token='.$this->firmar($mandanteA, $proyectoA, '32323232'));

        $response->assertRedirect("/proyectos/{$proyectoA->id}/bandeja?sin_persona=32323232")
            ->assertSessionMissing('crm_persona_public_id');
        $this->assertStringNotContainsString($ajena->public_id, (string) $response->headers->get('Location'));
    }

    public function test_la_misma_identificacion_en_dos_proyectos_abre_la_del_proyecto_del_jwt(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCobranza($mandante);
        $propia = $this->crearPersonaEn($proyectoA, '33333333');
        $this->crearPersonaEn($proyectoB, '33333333');

        $this->get('/integracion/handshake?token='.$this->firmar($mandante, $proyectoA, '33333333'))
            ->assertRedirect("/proyectos/{$proyectoA->id}/trabajo/{$propia->public_id}")
            ->assertSessionHas('crm_persona_public_id', $propia->public_id);
    }

    private function firmar(stdClass $mandante, stdClass $proyecto, string $identificacion): string
    {
        return JWT::encode([
            'sub' => 'agente.fuga@wrapper.io',
            'name' => 'Agente Fuga',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $mandante->id,
            'proyecto_id' => (int) $proyecto->id,
            'identificacion' => $identificacion,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], (string) $mandante->sso_secret, 'HS256');
    }
}
