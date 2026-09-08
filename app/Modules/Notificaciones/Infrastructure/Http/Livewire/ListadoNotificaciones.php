<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Infrastructure\Http\Livewire;

use App\Support\Livewire\AutorizaEnProyectoActivo;
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
    use AutorizaEnProyectoActivo;
    use WithPagination;

    public string $filtro = 'no_leidas';

    public function updatingFiltro(): void
    {
        $this->resetPage();
    }

    public function marcarLeida(int $id): void
    {
        // `notificaciones.ver` es el mínimo de la ruta y aquí no cabe uno mayor:
        // marcar leída no es una acción sobre el negocio, es sobre la bandeja de
        // quien la recibió. Lo que sí hace falta es que el commit vuelva a
        // exigirlo, porque /livewire/update no repasa el `can:` de la ruta.
        $this->autorizarEn('notificaciones.ver');

        // Y que la notificación sea suya. El id llega como argumento del método,
        // así que lo elige el cliente entero: sin esta comprobación, un
        // `$wire.marcarLeida(N)` desde la consola tacha la bandeja del compañero.
        $this->exigirNotificacionPropia($id);

        DB::table('notificaciones')
            ->where('id', $id)
            ->where('proyecto_id', $this->proyectoActivoId())
            ->where('destinatario_usuario_id', (int) auth()->id())
            ->whereNull('leida_en')
            ->update(['leida_en' => Carbon::now()]);
    }

    public function marcarTodasLeidas(): void
    {
        $this->autorizarEn('notificaciones.ver');

        // No lleva id: el WHERE por destinatario y proyecto activo es a la vez la
        // guarda de pertenencia y el alcance de la escritura.
        DB::table('notificaciones')
            ->where('proyecto_id', $this->proyectoActivoId())
            ->where('destinatario_usuario_id', (int) auth()->id())
            ->whereNull('leida_en')
            ->update(['leida_en' => Carbon::now()]);
    }

    /**
     * 404 si la notificación no es del usuario autenticado en el proyecto activo.
     *
     * `exigirDelProyecto()` del trait no sirve aquí: sólo sabe de `proyecto_id`,
     * y la pertenencia de una notificación es por destinatario. Dos gestores del
     * mismo proyecto tienen cada uno la suya.
     *
     * 404 y no 403 por lo mismo que el trait: un 403 sobre un id ajeno confirma
     * que ese id existe.
     */
    private function exigirNotificacionPropia(int $id): void
    {
        $esSuya = DB::table('notificaciones')
            ->where('id', $id)
            ->where('proyecto_id', $this->proyectoActivoId())
            ->where('destinatario_usuario_id', (int) auth()->id())
            ->exists();

        abort_unless($esSuya, 404);
    }

    public function render(): View
    {
        $proyectoId = $this->proyectoActivoId();
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
     *                                                                                            Map de notificacion_id → datos para construir route('proyectos.trabajo').
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
