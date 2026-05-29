<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class CamposDisponiblesApiTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_devuelve_campos_persona_y_contacto_del_proyecto(): void
    {
        $mandante = $this->crearMandante('M_CAMPOS', 'Mandante Campos');
        $proyecto = $this->crearProyectoCobranza($mandante);

        $headers = $this->firmar((int) $mandante->id, (string) $mandante->sso_secret);

        $response = $this->get('/api/integracion/campos?proyecto_id='.$proyecto->id, $headers);

        $response->assertOk();
        $sources = array_column($response->json('campos'), 'source');
        $this->assertContains('persona.nombres', $sources);
        $this->assertContains('persona.identificacion', $sources);
        $this->assertContains('contacto.telefono', $sources);
    }

    public function test_incluye_campos_de_entidades_configurables_persona(): void
    {
        $mandante = $this->crearMandante('M_ENT', 'Mandante Entidades');
        $proyecto = $this->crearProyectoCobranza($mandante);

        $entidadId = DB::table('entidades_configurables')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'codigo' => 'polizas',
            'nombre' => 'Pólizas',
            'relacion_con' => 'persona',
            'activo' => true,
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'entidad_configurable',
            'ambito_id' => $entidadId,
            'tipo' => 'texto_corto',
            'codigo' => 'numero_poliza',
            'etiqueta' => 'Número de póliza',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 0,
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);

        $headers = $this->firmar((int) $mandante->id, (string) $mandante->sso_secret);
        $response = $this->get('/api/integracion/campos?proyecto_id='.$proyecto->id, $headers);

        $response->assertOk();
        $campos = collect($response->json('campos'));
        $item = $campos->firstWhere('source', 'persona_ent.polizas.numero_poliza');

        $this->assertNotNull($item, 'El campo de entidad-persona debe enumerarse.');
        $this->assertSame('Pólizas · Número de póliza', $item['label']);
        $this->assertSame('persona_entidad', $item['grupo']);
        $this->assertSame('texto_corto', $item['tipo']);
    }

    public function test_proyecto_de_otro_mandante_devuelve_campos_vacios(): void
    {
        $mandanteA = $this->crearMandante('M_A_C');
        $mandanteB = $this->crearMandante('M_B_C');
        $proyectoB = $this->crearProyectoCobranza($mandanteB);

        $headers = $this->firmar((int) $mandanteA->id, (string) $mandanteA->sso_secret);

        $response = $this->get('/api/integracion/campos?proyecto_id='.$proyectoB->id, $headers);

        $response->assertOk();
        $this->assertSame([], $response->json('campos'));
    }

    public function test_sin_proyecto_id_devuelve_422(): void
    {
        $mandante = $this->crearMandante('M_NO_PID');
        $headers = $this->firmar((int) $mandante->id, (string) $mandante->sso_secret);

        $this->get('/api/integracion/campos', $headers)->assertStatus(422);
    }

    public function test_firma_invalida_devuelve_401(): void
    {
        $mandante = $this->crearMandante('M_BADSIG');

        $this->get('/api/integracion/campos?proyecto_id=1', [
            'X-Mandante-Id' => (string) $mandante->id,
            'X-Timestamp' => (string) time(),
            'X-Signature' => str_repeat('z', 64),
        ])->assertStatus(401);
    }

    /** @return array<string, string> */
    private function firmar(int $mandanteId, string $secret): array
    {
        $timestamp = time();

        return [
            'X-Mandante-Id' => (string) $mandanteId,
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => hash_hmac('sha256', ''.$timestamp, $secret),
        ];
    }
}
