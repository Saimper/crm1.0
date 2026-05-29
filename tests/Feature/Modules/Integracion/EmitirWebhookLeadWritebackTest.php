<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Modules\Integracion\Infrastructure\Jobs\EmitirWebhookLeadWriteback;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Job de webhook lead-writeback: firma HMAC de los bytes crudos con el sso_secret
 * actual, headers del contrato, y manejo de respuestas 2xx/5xx(retry)/4xx(permanente).
 */
final class EmitirWebhookLeadWritebackTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    private const URL = 'https://wrapper.example.com/api/integracion/lead-writeback';

    private const EVENT_ID = '550e8400-e29b-41d4-a716-446655440000';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_envia_body_firmado_con_headers_correctos(): void
    {
        Http::fake();

        $mandante = $this->crearMandante();
        $secret = (string) $mandante->sso_secret;
        $cuerpo = [
            'sync_ref' => 'sync-abc',
            'changes' => ['persona' => ['nombres' => 'Ana', 'apellidos' => 'Pérez']],
        ];

        (new EmitirWebhookLeadWriteback((int) $mandante->id, self::URL, $cuerpo, self::EVENT_ID))->handle();

        Http::assertSent(function ($request) use ($mandante, $secret, $cuerpo): bool {
            if ($request->url() !== self::URL) {
                return false;
            }

            $body = $request->body();
            $firmaEsperada = hash_hmac('sha256', $body, $secret);

            return hash_equals($firmaEsperada, (string) $request->header('X-Signature')[0])
                && (string) $request->header('X-Mandante-Id')[0] === (string) $mandante->id
                && (string) $request->header('X-Event-Id')[0] === self::EVENT_ID
                && json_decode($body, true) === $cuerpo;
        });
    }

    public function test_5xx_lanza_excepcion_para_reintentar(): void
    {
        Http::fake(['*' => Http::response('vicidial down', 503)]);

        $mandante = $this->crearMandante();

        $this->expectException(\RuntimeException::class);

        (new EmitirWebhookLeadWriteback(
            (int) $mandante->id,
            self::URL,
            ['sync_ref' => 's', 'changes' => ['persona' => ['nombres' => 'A']]],
            self::EVENT_ID,
        ))->handle();
    }

    public function test_4xx_no_lanza_ni_reintenta(): void
    {
        Http::fake(['*' => Http::response('sync_ref_not_found', 404)]);

        $mandante = $this->crearMandante();

        (new EmitirWebhookLeadWriteback(
            (int) $mandante->id,
            self::URL,
            ['sync_ref' => 's', 'changes' => ['persona' => ['nombres' => 'A']]],
            self::EVENT_ID,
        ))->handle();

        // No lanzó (la cola no reintentaría); el request se envió una sola vez.
        Http::assertSentCount(1);
    }

    public function test_event_id_estable_entre_reintentos(): void
    {
        $eventId = '12345678-1234-4567-8901-123456789012';

        $job = new EmitirWebhookLeadWriteback(1, self::URL, ['sync_ref' => 's', 'changes' => []], $eventId);

        /** @var EmitirWebhookLeadWriteback $reconstruido */
        $reconstruido = unserialize(serialize($job));

        $this->assertSame($eventId, $reconstruido->eventId);
    }
}
