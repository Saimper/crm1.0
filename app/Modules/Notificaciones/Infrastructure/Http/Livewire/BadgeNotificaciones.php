<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Campana en el header. Muestra conteo de notificaciones no-leídas del usuario logueado
 * en el proyecto activo. Escucha evento 'notificaciones-actualizadas' para refrescar.
 */
final class BadgeNotificaciones extends Component
{
    public int $noLeidas = 0;

    public function mount(): void
    {
        $this->refrescar();
    }

    #[On('notificaciones-actualizadas')]
    public function refrescar(): void
    {
        $proyectoActivo = app()->bound('tenancy.proyecto_activo')
            ? app('tenancy.proyecto_activo')
            : null;

        if ($proyectoActivo === null || auth()->id() === null) {
            $this->noLeidas = 0;

            return;
        }

        $this->noLeidas = (int) DB::table('notificaciones')
            ->where('proyecto_id', (int) $proyectoActivo->id)
            ->where('destinatario_usuario_id', (int) auth()->id())
            ->whereNull('leida_en')
            ->count();
    }

    public function render(): View
    {
        return view('notificaciones::livewire.badge-notificaciones');
    }
}
