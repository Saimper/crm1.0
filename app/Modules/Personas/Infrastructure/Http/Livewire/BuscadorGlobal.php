<?php

declare(strict_types=1);

namespace App\Modules\Personas\Infrastructure\Http\Livewire;

use App\Models\User;
use App\Modules\Personas\Application\DTOs\FiltrosListadoPersonas;
use App\Modules\Personas\Application\Services\ConsultaListadoPersonas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Buscador global scoped al proyecto activo (§9 CLAUDE.md v2).
 * Busca personas y casos por identificación o nombre dentro del proyecto actual.
 * Usa Ctrl+K para abrir; mínimo 3 caracteres para buscar.
 */
final class BuscadorGlobal extends Component
{
    public bool $abierto = false;

    public string $query = '';

    public function abrir(): void
    {
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->query = '';
    }

    public function render(): View
    {
        $texto = trim($this->query);
        $proyectoActivo = app()->bound('tenancy.proyecto_activo')
            ? app('tenancy.proyecto_activo')
            : null;

        $personas = collect();
        $casos = collect();

        $puedeBuscar = $proyectoActivo !== null
            && Auth::user()?->tienePermiso('casos.ver', (int) $proyectoActivo->id) === true;

        if ($puedeBuscar && mb_strlen($texto) >= 3) {
            $proyectoId = (int) $proyectoActivo->id;

            // El recorte por cartera del rol (F22). El buscador es la puerta más
            // usada de la aplicación: sin esto, un supervisor acotado encuentra
            // por nombre o por cédula a las personas de las carteras que su
            // bandeja le esconde, con el nombre de la cartera al lado.
            $carteras = $this->carterasDelRol($proyectoId);

            $consulta = app(ConsultaListadoPersonas::class);
            $personas = $consulta->aplicarFiltros(
                $consulta->recortarACarteras($consulta->consultaBase($proyectoId), $carteras),
                FiltrosListadoPersonas::desde($texto, ''),
                $carteras,
            )->select(['p.id', 'p.public_id', 'p.tipo_persona', 'p.identificacion', 'p.nombres', 'p.apellidos',
                'p.razon_social', 'ti.codigo as tipo_identificacion_codigo'])->orderBy('p.id')->limit(8)->get();
            $casos = $consulta->cuentasDePersonas($proyectoId, $personas->pluck('id')->map(fn ($id): int => (int) $id)->all(), $carteras);
            foreach ($casos as $caso) {
                $persona = $personas->firstWhere('id', $caso->persona_id);
                $caso->caso_public_id = $caso->public_id;
                $caso->persona_public_id = $persona->public_id;
                $caso->identificacion = $persona->identificacion;
            }

        }

        return view('personas::livewire.buscador-global', [
            'personas' => $personas,
            'casos' => $casos,
            'proyectoActivo' => $proyectoActivo,
        ]);
    }

    /**
     * @return list<int>|null Carteras del rol, o null si el rol no está acotado.
     */
    private function carterasDelRol(int $proyectoId): ?array
    {
        $usuario = Auth::user();

        return $usuario instanceof User ? $usuario->carterasPermitidas($proyectoId) : [];
    }
}
