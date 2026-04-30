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

        $rutas = $this->resolverRutasPorCaso($proyectoId, $notificaciones->items());

        $totalNoLeidas = (int) DB::table('notificaciones')
            ->where('proyecto_id', $proyectoId)
            ->where('destinatario_usuario_id', $usuarioId)
            ->whereNull('leida_en')
            ->count();

        return view('notificaciones::livewire.listado-notificaciones', [
            'notificaciones' => $notificaciones,
            'totalNoLeidas' => $totalNoLeidas,
            'rutas' => $rutas,
        ]);
    }

    /**
     * @param  array<int, object>  $notificaciones
     * @return array<int, array{caso_id: int, persona_public_id: string, caso_public_id: string}>
     *                                                                                          Map de notificacion_id → datos para construir route('proyectos.trabajo').
     */
    private function resolverRutasPorCaso(int $proyectoId, array $notificaciones): array
    {
        $casoIds = [];
        $idsPorNotif = [];
        foreach ($notificaciones as $n) {
            $meta = is_array($n->metadata) ? $n->metadata : json_decode((string) $n->metadata, true);
            $casoId = is_array($meta) && isset($meta['caso_id']) ? (int) $meta['caso_id'] : null;
            if ($casoId !== null && $casoId > 0) {
                $casoIds[$casoId] = true;
                $idsPorNotif[(int) $n->id] = $casoId;
            }
        }

        if ($casoIds === []) {
            return [];
        }

        $datosCasos = DB::table('casos as c')
            ->join('personas as p', 'p.id', '=', 'c.persona_id')
            ->where('c.proyecto_id', $proyectoId)
            ->whereIn('c.id', array_keys($casoIds))
            ->select(['c.id', 'c.public_id as caso_public_id', 'p.public_id as persona_public_id'])
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($idsPorNotif as $notifId => $casoId) {
            $row = $datosCasos->get($casoId);
            if ($row === null) {
                continue;
            }
            $out[$notifId] = [
                'caso_id' => $casoId,
                'persona_public_id' => (string) $row->persona_public_id,
                'caso_public_id' => (string) $row->caso_public_id,
            ];
        }

        return $out;
    }
}
