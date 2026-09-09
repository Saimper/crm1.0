<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Livewire;

use App\Models\User;
use App\Modules\Integracion\Application\UseCases\RotacionSecret\RotarSecretMandante;
use App\Modules\Integracion\Infrastructure\Jobs\EmitirWebhookStatusMandante;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
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
 *
 * Sobre las guardas: aquí NO se usa `AutorizaEnProyectoActivo`. Esta pantalla
 * cuelga de `admin.global` y no hay proyecto activo que autorizar contra él, así
 * que la comprobación correcta es la del rol global —la misma que hace
 * `AdminUsuarios::soloAdminGlobal()`—. Y hace falta repetirla en cada método
 * porque el `admin.global` de la ruta protege la PÁGINA: cada acción Livewire es
 * un POST aparte a /livewire/update que no vuelve a pasar por ese middleware.
 * Siendo esto el llavero del SSO —revelar y rotar secrets, y apuntar a dónde se
 * envían—, la guarda va incluso en los métodos que sólo leen.
 */
final class AdminSsoSecrets extends Component
{
    /**
     * `#[Locked]` en todo lo que lleva id o secret: son estado del servidor, no
     * entrada del cliente. Sin esto un `$wire.set('revelado', {3: true})` o un
     * `$wire.set('editandoMandanteId', N)` desde la consola reapunta la pantalla
     * al mandante que quiera quien tenga el componente montado.
     *
     * @var array<int, bool>
     */
    #[Locked]
    public array $revelado = [];

    /** ID del último mandante rotado (mostrar secret completo una sola vez). */
    #[Locked]
    public ?int $rotadoId = null;

    #[Locked]
    public ?string $rotadoSecret = null;

    /** Mandante en edición de webhook URLs (form drawer). */
    #[Locked]
    public ?int $editandoMandanteId = null;

    public string $webhookUrlSecretRotated = '';

    public string $webhookUrlStatusChanged = '';

    public function revelar(int $mandanteId): void
    {
        $this->soloAdminGlobal();
        $this->mandanteVigente($mandanteId);

        $this->revelado[$mandanteId] = true;
    }

    public function ocultar(int $mandanteId): void
    {
        $this->soloAdminGlobal();

        $this->revelado[$mandanteId] = false;
        $this->rotadoId = null;
        $this->rotadoSecret = null;
    }

    public function rotar(int $mandanteId, RotarSecretMandante $useCase): void
    {
        $this->soloAdminGlobal();
        $this->mandanteVigente($mandanteId);

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
        $this->soloAdminGlobal();
        $row = $this->mandanteVigente($mandanteId);

        $this->editandoMandanteId = (int) $row->id;
        $this->webhookUrlSecretRotated = (string) ($row->webhook_url_secret_rotated ?? '');
        $this->webhookUrlStatusChanged = (string) ($row->webhook_url_status_changed ?? '');
    }

    public function cerrarWebhooks(): void
    {
        $this->soloAdminGlobal();

        $this->editandoMandanteId = null;
        $this->webhookUrlSecretRotated = '';
        $this->webhookUrlStatusChanged = '';
    }

    public function guardarWebhooks(): void
    {
        $this->soloAdminGlobal();

        if ($this->editandoMandanteId === null) {
            return;
        }

        $this->mandanteVigente($this->editandoMandanteId);

        $this->validate([
            'webhookUrlSecretRotated' => ['nullable', 'url:http,https', 'max:255'],
            'webhookUrlStatusChanged' => ['nullable', 'url:http,https', 'max:255'],
        ]);

        DB::table('mandantes')
            ->where('id', $this->editandoMandanteId)
            ->whereNull('eliminada_en')
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
        $this->soloAdminGlobal();
        $row = $this->mandanteVigente($mandanteId);

        $url = (string) ($row->webhook_url_status_changed ?? '');
        $activo = (bool) $row->activo;

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
        // El listado ES la fuga: trae los secrets de todos los mandantes. La
        // guarda va aquí y no sólo en las acciones para que un componente
        // reutilizado o un snapshot heredado de una sesión con más derechos no
        // llegue a renderizarlos.
        $this->soloAdminGlobal();

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

    /**
     * El equivalente de `autorizarEn()` para una pantalla sin proyecto activo.
     *
     * `mandante.administrar` no sirve como criterio: ADMIN_MANDANTE lo tiene y
     * esta ruta le está vetada a propósito (§10, F39), porque el secret es del
     * canal entre el wrapper y el CRM, no del mandante.
     */
    private function soloAdminGlobal(): void
    {
        $usuario = auth()->user();

        abort_unless(
            $usuario instanceof User && $usuario->esAdminGlobal(),
            403,
            'Solo ADMIN_GLOBAL gestiona los secrets SSO.',
        );
    }

    /**
     * La pertenencia que sí existe aquí: que el mandante exista y siga vivo.
     *
     * No hay `proyecto_id` que cruzar —`mandantes` es la raíz de la jerarquía— y
     * ADMIN_GLOBAL es cross-mandante por definición, así que lo único que puede
     * llegar mal por el id del cliente es un mandante borrado o inexistente.
     * 404 y no 403, por lo mismo que `filaDelProyecto`: no confirmar qué ids hay.
     */
    private function mandanteVigente(int $mandanteId): object
    {
        $fila = DB::table('mandantes')
            ->where('id', $mandanteId)
            ->whereNull('eliminada_en')
            ->first(['id', 'activo', 'webhook_url_secret_rotated', 'webhook_url_status_changed']);

        abort_if($fila === null, 404);

        return $fila;
    }
}
