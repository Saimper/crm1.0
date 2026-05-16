<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * F37: autenticación HMAC para endpoints API server-to-server.
 *
 * Headers requeridos:
 *  - X-Mandante-Id: id del mandante CRM.
 *  - X-Timestamp: epoch en segundos. Leeway 60s contra clock skew.
 *  - X-Signature: HMAC-SHA256(body+timestamp, sso_secret_mandante) en hex.
 *
 * Acepta firma con sso_secret actual o con sso_secret_old (vigente). Esto
 * permite que la rotación de secret no rompa llamadas en vuelo del wrapper.
 *
 * Inyecta `mandante_id` en el request como atributo para uso del controller.
 */
final class VerificarFirmaHmacMandante
{
    private const LEEWAY_SEGUNDOS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $mandanteId = (int) $request->header('X-Mandante-Id', '0');
        $timestamp = (int) $request->header('X-Timestamp', '0');
        $signature = (string) $request->header('X-Signature', '');

        if ($mandanteId <= 0 || $timestamp <= 0 || $signature === '') {
            throw new HttpException(400, 'Headers HMAC requeridos: X-Mandante-Id, X-Timestamp, X-Signature.');
        }

        $ahora = time();
        if (abs($ahora - $timestamp) > self::LEEWAY_SEGUNDOS) {
            throw new HttpException(401, 'Timestamp fuera de rango.');
        }

        $mandante = DB::table('mandantes')
            ->where('id', $mandanteId)
            ->whereNull('eliminada_en')
            ->where('activo', true)
            ->first(['id', 'sso_secret', 'sso_secret_old', 'sso_secret_old_expires_at']);

        if ($mandante === null) {
            throw new HttpException(401, 'Mandante inválido.');
        }

        $payload = ((string) $request->getContent()).$timestamp;

        if (! $this->firmaCoincide($payload, $signature, (string) $mandante->sso_secret)) {
            $secretOld = (string) ($mandante->sso_secret_old ?? '');
            $expiresAt = $mandante->sso_secret_old_expires_at ?? null;
            $oldVigente = $secretOld !== '' && $expiresAt !== null && strtotime((string) $expiresAt) > $ahora;

            if (! $oldVigente || ! $this->firmaCoincide($payload, $signature, $secretOld)) {
                throw new HttpException(401, 'Firma HMAC inválida.');
            }
        }

        $request->attributes->set('mandante_id', $mandanteId);

        return $next($request);
    }

    private function firmaCoincide(string $payload, string $firmaRecibida, string $secret): bool
    {
        if ($secret === '') {
            return false;
        }

        $esperada = hash_hmac('sha256', $payload, $secret);

        return hash_equals($esperada, $firmaRecibida);
    }
}
