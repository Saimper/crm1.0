<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Livewire;

use App\Modules\Integracion\Application\UseCases\RotacionSecret\RotarSecretMandante;
use App\Modules\Integracion\Infrastructure\Jobs\EmitirWebhookStatusMandante;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * F37: pantalla admin global para gestionar el sso_secret POR MANDANTE.
 *
 * (Antes F35: secret por proyecto. F37 lo movió a mandante para 1 tenant
 * wrapper = N proyectos.)
 *
 * Permiso: ADMIN_GLOBAL via Gate::before (ruta protegida con admin.global).
 *
 * Acciones:
 *  - Revelar/ocultar secret (enmascarado por defecto).
 *  - Rotar: genera nuevo, mueve actual a sso_secret_old (válido 24h),
 *    despacha webhook al wrapper.
 *  - Editar webhook URLs (rotación + status changed).
 */
final class AdminSsoSecrets extends Component
{
    /** @var array<int, bool> */
    public array $revelado = [];

    /** ID del último mandante rotado (mostrar secret completo una sola vez). */
    public ?int $rotadoId = null;

    public ?string $rotadoSecret = null;

    /** Mandante en edición de webhook URLs (form drawer). */
    public ?int $editandoMandanteId = null;

    public string $webhookUrlSecretRotated = '';

    public string $webhookUrlStatusChanged = '';

    public function revelar(int $mandanteId): void
    {
        $this->revelado[$mandanteId] = true;
    }

    public function ocultar(int $mandanteId): void
    {
        $this->revelado[$mandanteId] = false;
        $this->rotadoId = null;
        $this->rotadoSecret = null;
    }

    public function rotar(int $mandanteId, RotarSecretMandante $useCase): void
    {
        $output = $useCase->execute($mandanteId);

        $this->rotadoId = $output->mandanteId;
        $this->rotadoSecret = $output->secretNuevo;
        $this->revelado[$mandanteId] = true;

        $msg = $output->secretAnteriorExpiraEn !== null
            ? 'Secret rotado. Anterior válido hasta '.$output->secretAnteriorExpiraEn->format('d/m/Y H:i').'.'
            : 'Secret rotado por primera vez.';

        session()->flash('admin-sso-ok', $msg);
    }

    public function abrirWebhooks(int $mandanteId): void
    {
        $row = DB::table('mandantes')
            ->where('id', $mandanteId)
            ->first(['id', 'webhook_url_secret_rotated', 'webhook_url_status_changed']);

        if ($row === null) {
            return;
        }

        $this->editandoMandanteId = (int) $row->id;
        $this->webhookUrlSecretRotated = (string) ($row->webhook_url_secret_rotated ?? '');
        $this->webhookUrlStatusChanged = (string) ($row->webhook_url_status_changed ?? '');
    }

    public function cerrarWebhooks(): void
    {
        $this->editandoMandanteId = null;
        $this->webhookUrlSecretRotated = '';
        $this->webhookUrlStatusChanged = '';
    }

    public function guardarWebhooks(): void
    {
        if ($this->editandoMandanteId === null) {
            return;
        }

        $this->validate([
            'webhookUrlSecretRotated' => ['nullable', 'url:http,https', 'max:255'],
            'webhookUrlStatusChanged' => ['nullable', 'url:http,https', 'max:255'],
        ]);

        DB::table('mandantes')
            ->where('id', $this->editandoMandanteId)
            ->update([
                'webhook_url_secret_rotated' => $this->webhookUrlSecretRotated !== '' ? $this->webhookUrlSecretRotated : null,
                'webhook_url_status_changed' => $this->webhookUrlStatusChanged !== '' ? $this->webhookUrlStatusChanged : null,
                'actualizada_en' => now(),
            ]);

        session()->flash('admin-sso-ok', 'Webhooks actualizados.');
        $this->cerrarWebhooks();
    }

    public function probarWebhookStatus(int $mandanteId): void
    {
        $url = (string) DB::table('mandantes')
            ->where('id', $mandanteId)
            ->value('webhook_url_status_changed');

        $activo = (bool) DB::table('mandantes')
            ->where('id', $mandanteId)
            ->value('activo');

        if ($url === '') {
            session()->flash('admin-sso-ok', 'No hay webhook_url_status_changed configurada.');

            return;
        }

        EmitirWebhookStatusMandante::dispatch($mandanteId, $activo, $url, Str::uuid()->toString());
        session()->flash('admin-sso-ok', 'Webhook status encolado.');
    }

    #[Computed]
    public function mandantes(): Collection
    {
        return DB::table('mandantes')
            ->whereNull('eliminada_en')
            ->select([
                'id', 'codigo', 'nombre', 'activo',
                'sso_secret', 'sso_secret_old', 'sso_secret_old_expires_at',
                'webhook_url_secret_rotated', 'webhook_url_status_changed',
                'actualizada_en',
            ])
            ->orderBy('codigo')
            ->get();
    }

    public function render(): View
    {
        return view('integracion::admin.sso-secrets');
    }
}
