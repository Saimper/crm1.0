<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de notificaciones del usuario logueado en el proyecto activo.
 * Filtro por estado (todas / no-leídas). Acciones: marcar una / marcar todas como leídas.
 */
final class ListadoNotificaciones extends Component
{
    use WithPagination;

    public string $filtro = 'no_leidas';

    public function updatingFiltro(): void
    {
        $this->resetPage();
    }

    public function marcarLeida(int $id): void
    {
        DB::table('notificaciones')
            ->where('id', $id)
            ->where('proyecto_id', (int) app('tenancy.proyecto_activo')->id)
            ->where('destinatario_usuario_id', (int) auth()->id())
            ->whereNull('leida_en')
            ->update(['leida_en' => Carbon::now()]);
    }

    public function marcarTodasLeidas(): void
    {
        DB::table('notificaciones')
            ->where('proyecto_id', (int) app('tenancy.proyecto_activo')->id)
            ->where('destinatario_usuario_id', (int) auth()->id())
            ->whereNull('leida_en')
            ->update(['leida_en' => Carbon::now()]);
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $usuarioId = (int) auth()->id();

        $q = DB::table('notificaciones')
            ->where('proyecto_id', $proyectoId)
            ->where('destinatario_usuario_id', $usuarioId);

        if ($this->filtro === 'no_leidas') {
            $q->whereNull('leida_en');
        }

        $notificaciones = $q->orderByDesc('creada_en')->paginate(25);

        $totalNoLeidas = (int) DB::table('notificaciones')
            ->where('proyecto_id', $proyectoId)
            ->where('destinatario_usuario_id', $usuarioId)
            ->whereNull('leida_en')
            ->count();

        return view('notificaciones::livewire.listado-notificaciones', [
            'notificaciones' => $notificaciones,
            'totalNoLeidas' => $totalNoLeidas,
        ]);
    }
}
