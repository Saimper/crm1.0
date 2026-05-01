<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * F34C — pantalla admin global para auditar tokens SSO emitidos.
 * Read-only + acción "revocar" (marcar consumido_en = now() para invalidar).
 *
 * Permiso: ADMIN_GLOBAL via Gate::before (ruta protegida con admin.global).
 */
final class AdminTokensSso extends Component
{
    use WithPagination;

    #[Url(as: 'estado', except: '')]
    public string $estado = '';

    public function updatingEstado(): void
    {
        $this->resetPage();
    }

    public function revocar(int $id): void
    {
        DB::table('integracion_tokens_sso')
            ->where('id', $id)
            ->whereNull('consumido_en')
            ->update(['consumido_en' => Carbon::now()]);

        session()->flash('admin-tokens-ok', 'Token revocado.');
    }

    public function render(): View
    {
        $q = DB::table('integracion_tokens_sso as t')
            ->leftJoin('users as u', 'u.id', '=', 't.usuario_id')
            ->leftJoin('proyectos as p', 'p.id', '=', 't.proyecto_id')
            ->select([
                't.id', 't.public_id', 't.usuario_id', 't.proyecto_id',
                't.expira_en', 't.consumido_en', 't.creado_en',
                't.ip_origen', 't.identificacion',
                'u.name as usuario_nombre', 'u.email as usuario_email',
                'p.codigo as proyecto_codigo', 'p.nombre as proyecto_nombre',
            ]);

        $ahora = Carbon::now();
        match ($this->estado) {
            'vigentes' => $q->whereNull('t.consumido_en')->where('t.expira_en', '>', $ahora),
            'consumidos' => $q->whereNotNull('t.consumido_en'),
            'expirados' => $q->whereNull('t.consumido_en')->where('t.expira_en', '<=', $ahora),
            default => null,
        };

        $tokens = $q->orderByDesc('t.creado_en')->paginate(25);

        $resumen = [
            'vigentes' => (int) DB::table('integracion_tokens_sso')
                ->whereNull('consumido_en')->where('expira_en', '>', $ahora)->count(),
            'consumidos' => (int) DB::table('integracion_tokens_sso')
                ->whereNotNull('consumido_en')->count(),
            'expirados' => (int) DB::table('integracion_tokens_sso')
                ->whereNull('consumido_en')->where('expira_en', '<=', $ahora)->count(),
        ];

        return view('integracion::admin.tokens-sso', [
            'tokens' => $tokens,
            'resumen' => $resumen,
            'ahora' => $ahora,
        ]);
    }
}
