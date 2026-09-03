<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Http\Livewire;

use App\Modules\Casos\Application\Services\PreferenciasColumnasCaso;
use App\Modules\Casos\Domain\Columnas\CatalogoColumnasCaso;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado paginado de casos del proyecto activo (todos los tipos CTI).
 *
 * Filtros: búsqueda libre (identificación/nombre/razón persona), cartera,
 * estado_caso, tipo_caso (cuando el proyecto soporta múltiples — actualmente
 * un proyecto = un tipo).
 *
 * F41: las columnas visibles las elige el usuario desde el catálogo cerrado
 * `CatalogoColumnasCaso` y se recuerdan por (usuario, proyecto) en
 * `preferencias_columnas`.
 *
 * Permiso: casos.ver.
 */
final class ListadoCasos extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busqueda = '';

    #[Url(as: 'cartera', except: '')]
    public string $carteraId = '';

    #[Url(as: 'estado', except: '')]
    public string $estadoCasoId = '';

    /** @var list<string> */
    public array $columnasVisibles = [];

    public bool $selectorColumnasAbierto = false;

    public function mount(): void
    {
        $this->columnasVisibles = $this->preferencias()->cargar(
            $this->usuarioId(),
            $this->proyectoId(),
            $this->tipoOperacion(),
        );
    }

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingCarteraId(): void
    {
        $this->resetPage();
    }

    public function updatingEstadoCasoId(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->carteraId = '';
        $this->estadoCasoId = '';
        $this->resetPage();
    }

    public function alternarSelectorColumnas(): void
    {
        $this->selectorColumnasAbierto = ! $this->selectorColumnasAbierto;
    }

    public function alternarColumna(string $clave): void
    {
        $visibles = $this->columnasVisibles;

        $this->columnasVisibles = in_array($clave, $visibles, true)
            ? array_values(array_filter($visibles, static fn (string $c): bool => $c !== $clave))
            : [...$visibles, $clave];

        $this->persistirColumnas();
    }

    public function moverColumna(string $clave, int $desplazamiento): void
    {
        $visibles = $this->columnasVisibles;
        $posicion = array_search($clave, $visibles, true);
        $destino = is_int($posicion) ? $posicion + $desplazamiento : -1;

        if (! is_int($posicion) || $destino < 0 || $destino >= count($visibles)) {
            return;
        }

        [$visibles[$posicion], $visibles[$destino]] = [$visibles[$destino], $visibles[$posicion]];
        $this->columnasVisibles = $visibles;

        $this->persistirColumnas();
    }

    public function restaurarColumnas(): void
    {
        $this->preferencias()->olvidar($this->usuarioId(), $this->proyectoId());
        $this->columnasVisibles = CatalogoColumnasCaso::POR_DEFECTO;
    }

    public function render(): View
    {
        $proyectoId = $this->proyectoId();
        $tipoOperacion = $this->tipoOperacion();
        $columnas = CatalogoColumnasCaso::indexadoPorClave($tipoOperacion);
        $visibles = CatalogoColumnasCaso::sanear($this->columnasVisibles, $tipoOperacion);

        $casos = $this->consultaBase($proyectoId, $tipoOperacion)
            ->select($this->seleccion($columnas, $visibles))
            ->orderByDesc('c.prioridad')
            ->orderByDesc('c.creada_en')
            ->paginate(25);

        return view('casos::livewire.listado-casos', [
            'casos' => $casos,
            'carteras' => $this->carteras($proyectoId),
            'estados' => $this->estados($proyectoId),
            'totalProyecto' => $this->totalProyecto($proyectoId),
            'catalogoColumnas' => CatalogoColumnasCaso::paraTipoOperacion($tipoOperacion),
            'columnasVisibles' => $visibles,
        ]);
    }

    /**
     * Columnas siempre presentes en el SELECT: alimentan enlaces y formato de fila.
     *
     * @param  array<string, \App\Modules\Casos\Domain\Columnas\ColumnaCaso>  $columnas
     * @param  list<string>  $visibles
     * @return list<string|\Illuminate\Contracts\Database\Query\Expression>
     */
    private function seleccion(array $columnas, array $visibles): array
    {
        $seleccion = [
            'c.id', 'c.public_id', 'c.tipo_caso',
            'p.public_id as persona_public_id', 'p.tipo_persona',
            'p.nombres', 'p.apellidos', 'p.razon_social',
        ];

        foreach ($visibles as $clave) {
            $columna = $columnas[$clave] ?? null;
            if ($columna !== null) {
                $seleccion[] = DB::raw($columna->expresion.' as '.$columna->alias());
            }
        }

        return $seleccion;
    }

    private function consultaBase(int $proyectoId, string $tipoOperacion): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('casos as c')
            ->join('personas as p', 'p.id', '=', 'c.persona_id')
            ->leftJoin('carteras as ca', 'ca.id', '=', 'c.cartera_id')
            ->leftJoin('estados_caso as ec', 'ec.id', '=', 'c.estado_caso_id')
            ->where('c.proyecto_id', $proyectoId)
            ->whereNull('c.eliminada_en');

        $tablaCti = CatalogoColumnasCaso::tablaCti($tipoOperacion);
        if ($tablaCti !== null) {
            $q->leftJoin($tablaCti.' as cti', 'cti.caso_id', '=', 'c.id');
        }

        return $this->aplicarFiltros($q);
    }

    private function aplicarFiltros(\Illuminate\Database\Query\Builder $q): \Illuminate\Database\Query\Builder
    {
        $busqueda = trim($this->busqueda);

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $q->where(function ($w) use ($like): void {
                $w->where('p.identificacion', 'like', $like)
                    ->orWhere('p.nombres', 'like', $like)
                    ->orWhere('p.apellidos', 'like', $like)
                    ->orWhere('p.razon_social', 'like', $like);
            });
        }

        if ($this->carteraId !== '' && ctype_digit($this->carteraId)) {
            $q->where('c.cartera_id', (int) $this->carteraId);
        }

        if ($this->estadoCasoId !== '' && ctype_digit($this->estadoCasoId)) {
            $q->where('c.estado_caso_id', (int) $this->estadoCasoId);
        }

        return $q;
    }

    /**
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    private function carteras(int $proyectoId): \Illuminate\Support\Collection
    {
        return DB::table('carteras')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->where('activo', true)
            ->orderBy('nombre')
            ->select(['id', 'nombre', 'codigo'])
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    private function estados(int $proyectoId): \Illuminate\Support\Collection
    {
        return DB::table('estados_caso')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->orderBy('orden')
            ->select(['id', 'nombre', 'codigo'])
            ->get();
    }

    private function totalProyecto(int $proyectoId): int
    {
        return (int) DB::table('casos')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->count();
    }

    private function persistirColumnas(): void
    {
        $this->preferencias()->guardar(
            $this->usuarioId(),
            $this->proyectoId(),
            $this->tipoOperacion(),
            $this->columnasVisibles,
        );
    }

    private function preferencias(): PreferenciasColumnasCaso
    {
        return app(PreferenciasColumnasCaso::class);
    }

    private function proyectoId(): int
    {
        return (int) app('tenancy.proyecto_activo')->id;
    }

    private function tipoOperacion(): string
    {
        return (string) (app('tenancy.proyecto_activo')->tipo_operacion ?? '');
    }

    private function usuarioId(): int
    {
        return (int) Auth::id();
    }
}
