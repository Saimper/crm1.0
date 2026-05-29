<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\Services;

use App\Modules\Integracion\Domain\Contracts\EmisorWritebackFicha;
use App\Modules\Integracion\Infrastructure\Jobs\EmitirWebhookLeadWriteback;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Resuelve la URL del wrapper por mandante, sanea los cambios y encola el webhook
 * firmado de writeback. Reutiliza el mecanismo de webhooks salientes existente
 * (mismo secreto SSO del mandante, misma firma HMAC).
 */
final readonly class EmisorWritebackFichaPorWebhook implements EmisorWritebackFicha
{
    public function __construct(private ConnectionInterface $db) {}

    public function emitir(int $mandanteId, string $syncRef, array $changes): void
    {
        $row = $this->db->table('mandantes')
            ->where('id', $mandanteId)
            ->first(['id', 'webhook_url_status_changed', 'webhook_url_secret_rotated']);

        if ($row === null) {
            Log::debug('lead-writeback: mandante no encontrado', ['mandante_id' => $mandanteId]);

            return;
        }

        $url = $this->resolverUrl($row);
        if ($url === null) {
            Log::debug('lead-writeback: mandante sin webhook URL configurada', ['mandante_id' => $mandanteId]);

            return;
        }

        $changes = $this->sanear($changes);
        if ($changes === []) {
            return;
        }

        EmitirWebhookLeadWriteback::dispatch(
            $mandanteId,
            $url,
            ['sync_ref' => $syncRef, 'changes' => $changes],
            Str::uuid()->toString(), // UUID v4 — el wrapper rechaza orderedUuid con 401.
        );
    }

    /**
     * Deriva la URL del endpoint lead-writeback a partir del origen (scheme://host[:port])
     * de una webhook URL ya configurada en el mandante, anexando el path fijo del contrato.
     */
    private function resolverUrl(object $row): ?string
    {
        $base = $row->webhook_url_status_changed ?: $row->webhook_url_secret_rotated;
        if (! is_string($base) || $base === '') {
            return null;
        }

        $partes = parse_url($base);
        if (! is_array($partes) || ! isset($partes['scheme'], $partes['host'])) {
            return null;
        }

        $url = $partes['scheme'].'://'.$partes['host'];
        if (isset($partes['port'])) {
            $url .= ':'.$partes['port'];
        }

        return $url.'/api/integracion/lead-writeback';
    }

    /**
     * Normaliza cada valor de `changes` a string (≤255). El wrapper valida
     * `changes.*.* => ['nullable','string','max:255']`; un valor no-string devuelve
     * 422 y tumba el webhook entero. Booleanos → "1"/"0"; arrays (selección
     * múltiple, moneda), null y vacíos se descartan.
     *
     * @param  array<string, array<string, mixed>>  $changes
     * @return array<string, array<string, string>>
     */
    private function sanear(array $changes): array
    {
        $limpio = [];
        foreach ($changes as $grupo => $campos) {
            $grupoLimpio = [];
            foreach ($campos as $clave => $valor) {
                $cadena = $this->aCadena($valor);
                if ($cadena === null || $cadena === '') {
                    continue;
                }
                $grupoLimpio[(string) $clave] = $cadena;
            }

            if ($grupoLimpio !== []) {
                $limpio[(string) $grupo] = $grupoLimpio;
            }
        }

        return $limpio;
    }

    private function aCadena(mixed $valor): ?string
    {
        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }

        if (is_int($valor) || is_float($valor) || is_string($valor)) {
            return mb_substr((string) $valor, 0, 255);
        }

        return null;
    }
}
