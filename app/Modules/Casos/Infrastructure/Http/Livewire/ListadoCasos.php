<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Http\Livewire;

use App\Models\User;
use App\Modules\Asignaciones\Application\UseCases\AutoasignarCaso;
use App\Modules\Asignaciones\Domain\Exceptions\AutoasignacionNoPermitida;
use App\Modules\Casos\Application\DTOs\FiltrosListadoCasos;
use App\Modules\Casos\Application\Services\ConsultaListadoCasos;
use App\Modules\Casos\Application\Services\PreferenciasColumnasCaso;
use App\Modules\Casos\Domain\Columnas\CatalogoColumnasCaso;
use App\Modules\Casos\Domain\Columnas\ColumnaCaso;
use App\Support\Database\CarterasOperativas;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use stdClass;

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
 * La consulta y los filtros viven en `ConsultaListadoCasos`, compartida con la
 * exportación: «Exportar CSV» descarga las mismas filas que se ven.
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

    /**
     * Sólo las cuentas que no son de nadie. Es el filtro que hace usable
     * «Tomar» cuando el proyecto tiene 5.000 cuentas y 40 sin dueño.
     */
    #[Url(as: 'sin_duenio', except: false)]
    public bool $soloSinDuenio = false;

    public ?string $mensajeAsignacion = null;

    /** @var list<string> */
    public array $columnasVisibles = [];

    public bool $selectorColumnasAbierto = false;

    /**
     * Por qué columna se ordena. Vacío es el orden de siempre: primero lo
     * urgente, y dentro de eso lo más nuevo.
     *
     * La clave llega del cliente y NUNCA se interpola en el ORDER BY: se busca
     * en el catálogo de columnas, que es una lista cerrada, y lo que se usa es
     * la expresión que el catálogo declara.
     */
    #[Url(as: 'orden', except: '')]
    public string $orden = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $direccion = 'asc';

    public function ordenarPor(string $clave): void
    {
        if (! array_key_exists($clave, CatalogoColumnasCaso::indexadoPorClave($this->tipoOperacion()))) {
            return;
        }

        // Segundo clic en la misma cabecera: se da la vuelta.
        $this->direccion = $this->orden === $clave && $this->direccion === 'asc' ? 'desc' : 'asc';
        $this->orden = $clave;
        $this->resetPage();
    }

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

    public function updatingSoloSinDuenio(): void
    {
        $this->resetPage();
    }

    /**
     * El asesor toma la cuenta que va a trabajar.
     *
     * La comprobación de permiso está aquí y en el UseCase (§13 defensa en
     * profundidad): esta pantalla la ven roles que no pueden autoasignarse.
     */
    public function tomarCuenta(int $casoId, AutoasignarCaso $autoasignar): void
    {
        $proyectoId = $this->proyectoId();

        abort_unless(
            $this->usuario()->tienePermiso('asignaciones.autoasignarse', $proyectoId),
            403,
            'No tienes permiso para tomar cuentas en este proyecto.',
        );

        try {
            $autoasignar->execute(
                proyectoId: $proyectoId,
                casoId: $casoId,
                usuarioId: $this->usuarioId(),
                ahora: new DateTimeImmutable,
                carterasPermitidas: $this->usuario()->carterasPermitidas($proyectoId),
            );
            $this->mensajeAsignacion = __('casos.assign_taken');
        } catch (AutoasignacionNoPermitida $e) {
            $this->addError('asignacion', $e->getMessage());
        }
    }

    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->carteraId = '';
        $this->estadoCasoId = '';
        $this->soloSinDuenio = false;
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
        $filtros = FiltrosListadoCasos::desde($this->busqueda, $this->carteraId, $this->estadoCasoId);
        $consulta = app(ConsultaListadoCasos::class);

        // El límite por cartera del rol (F22) va antes que cualquier filtro:
        // sin él, un supervisor limitado a una cartera veía la del proyecto entero.
        $base = $consulta->recortarACarteras(
            $consulta->consultaBase($proyectoId, $tipoOperacion),
            $this->usuario()->carterasPermitidas($proyectoId),
        );

        $filtrada = $consulta->aplicarFiltros($base, $filtros);

        if ($this->soloSinDuenio) {
            $filtrada->whereNotExists(fn (Builder $q) => $q
                ->from('asignaciones as asg')
                ->whereColumn('asg.caso_id', 'c.id')
                ->where('asg.proyecto_id', $proyectoId));
        }

        $casos = $this
            ->ordenar($filtrada->select($this->seleccion($columnas, $visibles)), $columnas)
            ->paginate(25);

        $autoasignar = app(AutoasignarCaso::class);

        return view('casos::livewire.listado-casos', [
            'casos' => $casos,
            'carteras' => $this->carteras($proyectoId),
            'estados' => $this->estados($proyectoId),
            'totalProyecto' => $this->totalProyecto($proyectoId),
            'catalogoColumnas' => CatalogoColumnasCaso::paraTipoOperacion($tipoOperacion),
            'columnasVisibles' => $visibles,
            'urlExportar' => route('proyectos.casos.exportar', ['proyecto_id' => $proyectoId] + $filtros->comoParametros()),
            'puedeTomar' => $this->usuario()->tienePermiso('asignaciones.autoasignarse', $proyectoId)
                && $autoasignar->proyectoLoPermite($proyectoId),
        ]);
    }

    /**
     * El orden elegido, o el de siempre si no hay ninguno.
     *
     * `$this->orden` ya pasó por el catálogo en `ordenarPor`, pero se vuelve a
     * comprobar aquí porque también puede llegar por la URL, que nadie filtró.
     *
     * @param  array<string, ColumnaCaso>  $columnas
     */
    private function ordenar(Builder $consulta, array $columnas): Builder
    {
        $columna = $columnas[$this->orden] ?? null;

        if ($columna === null) {
            return $consulta->orderByDesc('c.prioridad')->orderByDesc('c.creada_en');
        }

        $direccion = $this->direccion === 'desc' ? 'desc' : 'asc';

        // Desempate por PK: sin él, dos filas con el mismo valor pueden salir en
        // distinto orden en cada página y una se repite mientras otra no sale.
        return $consulta
            ->orderBy(DB::raw($columna->expresion), $direccion)
            ->orderBy('c.id');
    }

    /**
     * Columnas siempre presentes en el SELECT: alimentan enlaces y formato de fila.
     *
     * @param  array<string, ColumnaCaso>  $columnas
     * @param  list<string>  $visibles
     * @return list<string|Expression<string>>
     */
    private function seleccion(array $columnas, array $visibles): array
    {
        $seleccion = [
            'c.id', 'c.public_id', 'c.tipo_caso',
            'p.public_id as persona_public_id', 'p.tipo_persona',
            'p.nombres', 'p.apellidos', 'p.razon_social',
            // Quién tiene la cuenta. Subconsulta escalar y no join porque la
            // consulta ya trae una fila por caso: un join, aunque hoy el único
            // `(proyecto_id, caso_id)` garantice que no puede multiplicar,
            // acopla el número de filas del listado a otra tabla.
            DB::raw('(select u.name from asignaciones asg'
                .' inner join users u on u.id = asg.usuario_id'
                .' where asg.caso_id = c.id and asg.proyecto_id = c.proyecto_id'
                .' order by asg.id desc limit 1) as asignado_a'),
        ];

        foreach ($visibles as $clave) {
            $columna = $columnas[$clave] ?? null;
            if ($columna !== null) {
                $seleccion[] = DB::raw($columna->expresion.' as '.$columna->alias());
            }
        }

        return $seleccion;
    }

    /**
     * @return Collection<int, stdClass>
     */
    private function carteras(int $proyectoId): Collection
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
     * @return Collection<int, stdClass>
     */
    private function estados(int $proyectoId): Collection
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
        return CarterasOperativas::casos(DB::connection(), $proyectoId)
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

    private function usuario(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 401);

        return $usuario;
    }
}
