<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * F37: webhook saliente CRM → wrapper notificando rotación de sso_secret.
 *
 * El wrapper firma el body con el secret VIEJO (que aún tiene) para
 * confirmar autenticidad, y aplica el secret NUEVO incluido en el payload.
 * El CRM acepta firmas con ambos secrets durante 24h vía sso_secret_old.
 *
 * Reintentos: 3 con backoff exponencial 10/60/300s. Falla silenciosa al
 * cuarto intento → log warning. Admin global puede reintentar manual.
 */
final class EmitirWebhookSecretRotado implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $mandanteId,
        public readonly string $webhookUrl,
        public readonly string $eventId,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        $row = DB::table('mandantes')
            ->where('id', $this->mandanteId)
            ->first(['id', 'codigo', 'sso_secret', 'sso_secret_old', 'sso_secret_old_expires_at']);

        if ($row === null) {
            Log::warning('webhook secret-rotated: mandante no encontrado', ['mandante_id' => $this->mandanteId]);

            return;
        }

        $payload = [
            'mandante_id' => (int) $row->id,
            'mandante_codigo' => (string) $row->codigo,
            'sso_secret_nuevo' => (string) $row->sso_secret,
            'sso_secret_old_expires_at' => $row->sso_secret_old_expires_at,
            'evento' => 'secret_rotated',
            'emitido_en' => now()->toIso8601String(),
        ];

        $secretParaFirmar = (string) ($row->sso_secret_old ?? $row->sso_secret);
        $bodyJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($bodyJson === false) {
            throw new \RuntimeException('No se pudo serializar payload webhook.');
        }
        $signature = hash_hmac('sha256', $bodyJson, $secretParaFirmar);

        $response = Http::timeout(10)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Mandante-Id' => (string) $row->id,
                'X-Event-Id' => $this->eventId,
                'X-Signature' => $signature,
            ])
            ->withBody($bodyJson, 'application/json')
            ->post($this->webhookUrl);

        if ($response->failed()) {
            throw new \RuntimeException("Webhook secret-rotated falló: HTTP {$response->status()}");
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('webhook secret-rotated: agotó reintentos', [
            'mandante_id' => $this->mandanteId,
            'webhook_url' => $this->webhookUrl,
            'error' => $e->getMessage(),
        ]);
    }
}
