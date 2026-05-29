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
 * Writeback CRM→ViciDial: webhook saliente firmado que propaga al wrapper los
 * cambios de la ficha (sync_ref + changes). El wrapper resuelve lead/lista/tenant
 * a partir del sync_ref y del header X-Mandante-Id.
 *
 * Firma: HMAC-SHA256 de los bytes crudos exactos del body con el sso_secret ACTUAL
 * del mandante (el wrapper acepta también el viejo durante la ventana de rotación).
 *
 * Reintentos (3, backoff 10/60/300s): solo ante 5xx (transitorio), reusando el
 * mismo X-Event-Id (readonly + SerializesModels) para que el wrapper no duplique la
 * escritura. Los 4xx (404 sync_ref_not_found, 410 expired, 403 tenant_mismatch/
 * crm_module_disabled, 422 validación) son permanentes: se loguean y NO se reintentan.
 */
final class EmitirWebhookLeadWriteback implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @param  array{sync_ref: string, changes: array<string, array<string, string>>}  $cuerpo
     */
    public function __construct(
        public readonly int $mandanteId,
        public readonly string $webhookUrl,
        public readonly array $cuerpo,
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
            ->first(['id', 'sso_secret']);

        if ($row === null || ! is_string($row->sso_secret) || $row->sso_secret === '') {
            Log::warning('lead-writeback: mandante sin sso_secret', ['mandante_id' => $this->mandanteId]);

            return;
        }

        $bodyJson = json_encode($this->cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($bodyJson === false) {
            throw new \RuntimeException('No se pudo serializar payload lead-writeback.');
        }

        $signature = hash_hmac('sha256', $bodyJson, (string) $row->sso_secret);

        $response = Http::timeout(10)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Mandante-Id' => (string) $row->id,
                'X-Event-Id' => $this->eventId,
                'X-Signature' => $signature,
            ])
            ->withBody($bodyJson, 'application/json')
            ->post($this->webhookUrl);

        $status = $response->status();

        // 2xx: aplicado / duplicate (idempotente) / no_changes → OK, no reintentar.
        if ($status >= 200 && $status < 300) {
            return;
        }

        // 5xx: transitorio (p.ej. vicidial_unavailable) → reintentar con el mismo X-Event-Id.
        if ($status >= 500) {
            throw new \RuntimeException("lead-writeback transitorio: HTTP {$status}");
        }

        // 4xx: permanente (sync_ref_not_found/expired, tenant_mismatch, validación) → no reintentar.
        Log::warning('lead-writeback: error permanente, no se reintenta', [
            'mandante_id' => $this->mandanteId,
            'event_id' => $this->eventId,
            'status' => $status,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('lead-writeback: agotó reintentos', [
            'mandante_id' => $this->mandanteId,
            'event_id' => $this->eventId,
            'webhook_url' => $this->webhookUrl,
            'error' => $e->getMessage(),
        ]);
    }
}
