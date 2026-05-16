<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Modules\Integracion\Application\UseCases\RotacionSecret\RotarSecretMandante;
use App\Modules\Integracion\Infrastructure\Jobs\EmitirWebhookSecretRotado;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class WebhookSecretRotadoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_rotar_genera_nuevo_secret_y_mueve_actual_a_old(): void
    {
        $mandante = $this->crearMandante();
        $secretAntes = (string) $mandante->sso_secret;

        $useCase = $this->app->make(RotarSecretMandante::class);
        $output = $useCase->execute((int) $mandante->id);

        $this->assertNotSame($secretAntes, $output->secretNuevo);
        $this->assertSame(64, strlen($output->secretNuevo));
        $this->assertNotNull($output->secretAnteriorExpiraEn);

        $row = DB::table('mandantes')->where('id', $mandante->id)
            ->first(['sso_secret', 'sso_secret_old', 'sso_secret_old_expires_at']);

        $this->assertSame($output->secretNuevo, (string) $row->sso_secret);
        $this->assertSame($secretAntes, (string) $row->sso_secret_old);
    }

    public function test_webhook_envia_payload_con_firma_hmac_del_secret_viejo(): void
    {
        Http::fake();

        $mandante = $this->crearMandante();
        $secretViejo = (string) $mandante->sso_secret;

        DB::table('mandantes')->where('id', $mandante->id)->update([
            'webhook_url_secret_rotated' => 'https://wrapper.example.com/api/integracion/secret-rotated',
        ]);

        $useCase = $this->app->make(RotarSecretMandante::class);
        $output = $useCase->execute((int) $mandante->id);

        // Job vive en cola; lo despachamos sync para verificar HTTP outbound.
        $job = new EmitirWebhookSecretRotado(
            mandanteId: (int) $mandante->id,
            webhookUrl: 'https://wrapper.example.com/api/integracion/secret-rotated',
            eventId: '550e8400-e29b-41d4-a716-446655440000',
        );
        $job->handle();

        Http::assertSent(function ($request) use ($mandante, $secretViejo, $output): bool {
            if ($request->url() !== 'https://wrapper.example.com/api/integracion/secret-rotated') {
                return false;
            }

            $body = $request->body();
            $signatureRecibida = (string) $request->header('X-Signature')[0];
            $signatureEsperada = hash_hmac('sha256', $body, $secretViejo);

            $payload = json_decode($body, true);

            return hash_equals($signatureEsperada, $signatureRecibida)
                && $payload['mandante_id'] === (int) $mandante->id
                && $payload['sso_secret_nuevo'] === $output->secretNuevo
                && $payload['evento'] === 'secret_rotated';
        });
    }

    public function test_webhook_lanza_excepcion_si_http_falla(): void
    {
        Http::fake([
            '*' => Http::response('boom', 500),
        ]);

        $mandante = $this->crearMandante();

        $job = new EmitirWebhookSecretRotado(
            mandanteId: (int) $mandante->id,
            webhookUrl: 'https://wrapper.example.com/api/integracion/secret-rotated',
            eventId: '550e8400-e29b-41d4-a716-446655440000',
        );

        $this->expectException(\RuntimeException::class);
        $job->handle();
    }

    public function test_rotar_mandante_inexistente_lanza_excepcion(): void
    {
        $this->expectException(\DomainException::class);
        $this->app->make(RotarSecretMandante::class)->execute(999_999);
    }

    public function test_webhook_envia_x_event_id_uuid_v4(): void
    {
        Http::fake();

        $mandante = $this->crearMandante();
        $eventId = '550e8400-e29b-41d4-a716-446655440000';

        $job = new EmitirWebhookSecretRotado(
            mandanteId: (int) $mandante->id,
            webhookUrl: 'https://wrapper.example.com/api/integracion/secret-rotated',
            eventId: $eventId,
        );
        $job->handle();

        Http::assertSent(fn ($req): bool => $req->header('X-Event-Id')[0] === $eventId);
    }

    public function test_x_event_id_estable_entre_reintentos(): void
    {
        // El eventId vive en propiedad readonly del Job; SerializesModels lo persiste
        // intacto en cada reintento. Verificamos serializando + deserializando el job.
        $eventId = '12345678-1234-4567-8901-123456789012';
        $job = new EmitirWebhookSecretRotado(
            mandanteId: 1,
            webhookUrl: 'https://wrapper.example.com/api/integracion/secret-rotated',
            eventId: $eventId,
        );

        $serialized = serialize($job);
        $jobReconstruido = unserialize($serialized);

        $this->assertSame($eventId, $jobReconstruido->eventId);
    }
}
