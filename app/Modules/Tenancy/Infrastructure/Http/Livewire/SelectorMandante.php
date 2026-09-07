<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire;

use App\Modules\Tenancy\Application\Services\ResolutorMandanteActivo;
use App\Modules\Tenancy\Infrastructure\Http\Middleware\ResolverMandanteActivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Elegir dentro de qué cliente se trabaja (decisión D1).
 *
 * El ADMIN_GLOBAL dejaba ver la base entera de golpe: los usuarios de las cuatro
 * empresas en una sola tabla, sin ninguna señal de a quién pertenecía cada uno.
 * Eso es lo que hace posible borrar por error a alguien de otro cliente. A
 * partir de aquí opera siempre dentro de UN mandante y conmuta explícitamente.
 *
 * Quien solo alcanza un cliente no ve esta pantalla: el middleware se lo asigna
 * y sigue de largo.
 */
final class SelectorMandante extends Component
{
    public function seleccionar(int $mandanteId, ResolutorMandanteActivo $resolutor): void
    {
        $usuario = auth()->user();

        // El id llega del cliente: se revalida contra los permisos, no contra
        // la lista que se pintó.
        if ($usuario === null || $resolutor->resolver($usuario, $mandanteId) === null) {
            abort(403, 'No tienes acceso a este cliente.');
        }

        session()->put(ResolverMandanteActivo::CLAVE_SESION, $mandanteId);

        $this->redirect(route('admin.dashboard'), navigate: true);
    }

    public function render(ResolutorMandanteActivo $resolutor): View
    {
        $usuario = auth()->user();
        $permitidos = $usuario === null ? [] : $resolutor->permitidos($usuario);

        $mandantes = $permitidos === []
            ? collect()
            : DB::table('mandantes')
                ->whereIn('id', $permitidos)
                ->whereNull('eliminada_en')
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']);

        return view('livewire.tenancy.selector-mandante', [
            'mandantes' => $mandantes,
            'activoId' => session(ResolverMandanteActivo::CLAVE_SESION),
        ]);
    }
}
