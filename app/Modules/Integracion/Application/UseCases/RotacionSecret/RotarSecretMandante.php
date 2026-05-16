<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\UseCases\RotacionSecret;

use App\Modules\Integracion\Domain\ValueObjects\MandanteSsoSecret;
use App\Modules\Integracion\Infrastructure\Jobs\EmitirWebhookSecretRotado;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * F37: rota el sso_secret de un mandante.
 *
 * Mueve el secret actual a sso_secret_old (válido 24h vía
 * sso_secret_old_expires_at), genera un secret nuevo y lo persiste.
 * Despacha webhook saliente para notificar al wrapper.
 *
 * El secret nuevo se devuelve en plano UNA vez (la UI lo muestra al admin
 * para copiar). Tras eso solo queda almacenado en DB.
 */
final class RotarSecretMandante
{
    private const VENTANA_OLD_HORAS = 24;

    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    public function execute(int $mandanteId): RotarSecretMandanteOutput
    {
        $mandante = $this->db->table('mandantes')
            ->where('id', $mandanteId)
            ->whereNull('eliminada_en')
            ->first(['id', 'sso_secret', 'webhook_url_secret_rotated']);

        if ($mandante === null) {
            throw new \DomainException("Mandante {$mandanteId} no existe.");
        }

        $secretNuevo = MandanteSsoSecret::generar();
        $secretAnterior = (string) ($mandante->sso_secret ?? '');
        $expiraOld = Carbon::now()->addHours(self::VENTANA_OLD_HORAS);

        $this->db->transaction(function () use ($mandanteId, $secretNuevo, $secretAnterior, $expiraOld): void {
            $this->db->table('mandantes')
                ->where('id', $mandanteId)
                ->update([
                    'sso_secret' => $secretNuevo->valor,
                    'sso_secret_old' => $secretAnterior !== '' ? $secretAnterior : null,
                    'sso_secret_old_expires_at' => $secretAnterior !== '' ? $expiraOld : null,
                    'actualizada_en' => Carbon::now(),
                ]);
        });

        $webhookUrl = (string) ($mandante->webhook_url_secret_rotated ?? '');
        if ($webhookUrl !== '') {
            EmitirWebhookSecretRotado::dispatch(
                $mandanteId,
                $webhookUrl,
                Str::uuid()->toString(),
            );
        }

        return new RotarSecretMandanteOutput(
            mandanteId: $mandanteId,
            secretNuevo: $secretNuevo->valor,
            secretAnteriorExpiraEn: $secretAnterior !== '' ? $expiraOld->toImmutable()->toDateTimeImmutable() : null,
        );
    }
}
