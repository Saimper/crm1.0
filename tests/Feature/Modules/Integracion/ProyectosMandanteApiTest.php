<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ProyectosMandanteApiTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_firma_hmac_valida_devuelve_proyectos_del_mandante(): void
    {
        $mandante = $this->crearMandante('M_API', 'Mandante API');
        $this->crearProyectoCobranza($mandante);
        $this->crearProyectoCx($mandante);

        $headers = $this->firmar((int) $mandante->id, (string) $mandante->sso_secret, '');

        $response = $this->get('/api/integracion/proyectos', $headers);

        $response->assertOk();
        $json = $response->json();

        $this->assertSame((int) $mandante->id, $json['mandante_id']);
        $this->assertCount(2, $json['proyectos']);
    }

    public function test_proyectos_de_otros_mandantes_no_se_filtran(): void
    {
        $mandanteA = $this->crearMandante('M_A');
        $mandanteB = $this->crearMandante('M_B');
        $this->crearProyectoCobranza($mandanteA);
        $this->crearProyectoCobranza($mandanteB);

        $headers = $this->firmar((int) $mandanteA->id, (string) $mandanteA->sso_secret, '');

        $response = $this->get('/api/integracion/proyectos', $headers);
        $response->assertOk();
        $this->assertCount(1, $response->json('proyectos'));
    }

    public function test_firma_invalida_devuelve_401(): void
    {
        $mandante = $this->crearMandante();

        $headers = [
            'X-Mandante-Id' => (string) $mandante->id,
            'X-Timestamp' => (string) time(),
            'X-Signature' => str_repeat('z', 64),
        ];

        $this->get('/api/integracion/proyectos', $headers)->assertStatus(401);
    }

    public function test_timestamp_fuera_de_rango_devuelve_401(): void
    {
        $mandante = $this->crearMandante();
        $timestampViejo = time() - 600;
        $signature = hash_hmac('sha256', '0'.$timestampViejo, (string) $mandante->sso_secret);

        $headers = [
            'X-Mandante-Id' => (string) $mandante->id,
            'X-Timestamp' => (string) $timestampViejo,
            'X-Signature' => $signature,
        ];

        $this->get('/api/integracion/proyectos', $headers)->assertStatus(401);
    }

    public function test_headers_faltantes_devuelve_400(): void
    {
        $this->get('/api/integracion/proyectos')->assertStatus(400);
    }

    public function test_mandante_inexistente_devuelve_401(): void
    {
        $headers = [
            'X-Mandante-Id' => '999999',
            'X-Timestamp' => (string) time(),
            'X-Signature' => hash_hmac('sha256', '0'.time(), 'whatever'),
        ];

        $this->get('/api/integracion/proyectos', $headers)->assertStatus(401);
    }

    public function test_secret_old_vigente_acepta_firma(): void
    {
        $mandante = $this->crearMandante();
        $secretViejo = (string) $mandante->sso_secret;
        $secretNuevo = bin2hex(random_bytes(32));

        DB::table('mandantes')->where('id', $mandante->id)->update([
            'sso_secret' => $secretNuevo,
            'sso_secret_old' => $secretViejo,
            'sso_secret_old_expires_at' => now()->addHours(24),
        ]);

        $this->crearProyectoCobranza($mandante);

        $headers = $this->firmar((int) $mandante->id, $secretViejo, '');

        $this->get('/api/integracion/proyectos', $headers)->assertOk();
    }

    public function test_proyecto_eliminado_no_aparece(): void
    {
        $mandante = $this->crearMandante();
        $p1 = $this->crearProyectoCobranza($mandante);
        $p2 = $this->crearProyectoCx($mandante);

        DB::table('proyectos')->where('id', $p2->id)->update(['eliminada_en' => now()]);

        $headers = $this->firmar((int) $mandante->id, (string) $mandante->sso_secret, '');

        $response = $this->get('/api/integracion/proyectos', $headers);
        $this->assertCount(1, $response->json('proyectos'));
        $this->assertSame((int) $p1->id, $response->json('proyectos.0.id'));
    }

    /** @return array<string, string> */
    private function firmar(int $mandanteId, string $secret, string $body): array
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $body.$timestamp, $secret);

        return [
            'X-Mandante-Id' => (string) $mandanteId,
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => $signature,
        ];
    }
}
