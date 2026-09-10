<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos;

use App\Modules\Tenancy\Application\UseCases\AdministrarDisponibilidad;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoCartera;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use App\Support\Livewire\AutorizaEnProyectoActivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Paso 2 del wizard F36 — CRUD de carteras del proyecto.
 *
 * Mutación directa (DB::table) siguiendo el patrón AdminCarterasProyecto.
 * Multi-tenancy: todas las queries scoped por proyecto_id (excepción explícita
 * solo en chequeos de unicidad por (proyecto_id, codigo)).
 * Reusa CodigoCartera VO para regla de unicidad de formato.
 */
final class PasoCarteras extends Component
{
    use AutorizaEnProyectoActivo;

    /**
     * `#[Locked]` porque el proyecto lo fija la ruta (`{proyecto:public_id}`) y
     * es contra él contra quien se comprueban permiso y pertenencia. Sin el
     * candado, el commit de Livewire —que no vuelve a pasar por el `can:` de la
     * ruta— llega con el proyecto que mande el cliente.
     */
    #[Locked]
    public ProyectoModel $proyecto;

    public string $busqueda = '';

    public bool $formVisible = false;

    /**
     * `#[Locked]` porque decide qué fila actualiza `guardarCartera()`: sólo lo
     * fijan `abrirFormCrear`/`abrirFormEditar`/`cerrarForm`, que ya comprueban
     * que la cartera sea de este proyecto.
     */
    #[Locked]
    public ?int $editandoId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'codigo' => '',
        'nombre' => '',
        'descripcion' => '',
        'activo' => true,
    ];

    public function mount(ProyectoModel $proyecto): void
    {
        $this->proyecto = $proyecto;
        $this->autorizar();
    }

    public function abrirFormCrear(): void
    {
        $this->autorizar('carteras.crear');
        $this->editandoId = null;
        $this->form = ['codigo' => '', 'nombre' => '', 'descripcion' => '', 'activo' => true];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $id): void
    {
        $this->autorizar('carteras.editar');

        $row = DB::table('carteras')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->whereNull('eliminada_en')
            ->first();

        if ($row === null) {
            return;
        }

        $this->editandoId = $id;
        $this->form = [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'descripcion' => (string) ($row->descripcion ?? ''),
            'activo' => (bool) $row->activo,
        ];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function cerrarForm(): void
    {
        $this->formVisible = false;
        $this->editandoId = null;
        $this->resetErrorBag();
    }

    public function guardarCartera(AdministrarDisponibilidad $disponibilidad): void
    {
        $this->autorizar($this->editandoId === null ? 'carteras.crear' : 'carteras.editar');

        // Editar exige además que la cartera sea de este proyecto. El UPDATE ya
        // filtra por `proyecto_id`, pero sin esto una cartera ajena daba cero
        // filas afectadas y el mismo "Cartera guardada" que un guardado real.
        if ($this->editandoId !== null) {
            $this->exigirDelProyecto('carteras', $this->editandoId);
        }

        $this->validate([
            'form.codigo' => ['required', 'string', 'max:80'],
            'form.nombre' => ['required', 'string', 'max:200'],
            'form.descripcion' => ['nullable', 'string', 'max:500'],
            'form.activo' => ['required', 'boolean'],
        ], [], [
            'form.codigo' => 'código',
            'form.nombre' => 'nombre',
            'form.descripcion' => 'descripción',
            'form.activo' => 'estado',
        ]);

        try {
            $codigoVO = new CodigoCartera((string) $this->form['codigo']);
        } catch (InvalidArgumentException $e) {
            $this->addError('form.codigo', $e->getMessage());

            return;
        }

        $codigoNormalizado = $codigoVO->asString();
        $proyectoId = (int) $this->proyecto->id;

        $duplicadoQuery = DB::table('carteras')
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', $codigoNormalizado);

        if ($this->editandoId !== null) {
            $duplicadoQuery->where('id', '!=', $this->editandoId);
        }

        if ($duplicadoQuery->exists()) {
            $this->addError('form.codigo', 'Ese código ya pertenece a una cartera del proyecto, activa o eliminada. Usa otro código.');

            return;
        }

        $payload = [
            'codigo' => $codigoNormalizado,
            'nombre' => trim((string) $this->form['nombre']),
            'descripcion' => $this->descripcionOpcional(),
            'activo' => (bool) $this->form['activo'],
            'actualizada_en' => Carbon::now(),
        ];

        if ($this->editandoId === null) {
            $payload['public_id'] = (string) Str::ulid();
            $payload['proyecto_id'] = $proyectoId;
            $payload['creada_en'] = Carbon::now();

            DB::table('carteras')->insert($payload);
        } else {
            unset($payload['activo']);
            DB::transaction(function () use ($payload, $proyectoId, $disponibilidad): void {
                DB::table('carteras')->where('id', $this->editandoId)
                    ->where('proyecto_id', $proyectoId)->update($payload);
                $disponibilidad->cambiarEstadoCartera($proyectoId, (int) $this->editandoId, (bool) $this->form['activo']);
            });
        }

        $this->cerrarForm();
        session()->flash('paso-carteras-ok', 'Cartera guardada.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function eliminarCartera(int $id, AdministrarDisponibilidad $disponibilidad): void
    {
        $this->autorizar('carteras.eliminar');
        $this->exigirDelProyecto('carteras', $id);
        $disponibilidad->eliminarCartera((int) $this->proyecto->id, $id);
        $this->cerrarForm();
        session()->flash('paso-carteras-ok', 'Cartera eliminada. Sus cuentas se retiraron de la operación y se conserva el historial.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function toggleActivo(int $id, AdministrarDisponibilidad $disponibilidad): void
    {
        $this->autorizar('carteras.editar');
        $cartera = $this->filaDelProyecto('carteras', $id);
        $disponibilidad->cambiarEstadoCartera((int) $this->proyecto->id, $id, ! (bool) $cartera->activo);
        session()->flash('paso-carteras-ok', 'Estado actualizado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function render(): View
    {
        $this->autorizar();
        $proyectoId = (int) $this->proyecto->id;
        $busqueda = trim($this->busqueda);

        $query = DB::table('carteras as c')
            ->leftJoin('casos as cs', function ($join): void {
                $join->on('cs.cartera_id', '=', 'c.id')->whereNull('cs.eliminada_en');
            })
            ->where('c.proyecto_id', $proyectoId)
            ->whereNull('c.eliminada_en');

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('c.codigo', 'like', $like)
                    ->orWhere('c.nombre', 'like', $like);
            });
        }

        $carteras = $query
            ->select([
                'c.id', 'c.codigo', 'c.nombre', 'c.descripcion', 'c.activo',
                DB::raw('count(cs.id) as total_casos'),
            ])
            ->groupBy('c.id', 'c.codigo', 'c.nombre', 'c.descripcion', 'c.activo')
            ->orderBy('c.codigo')
            ->get();

        return view('livewire.tenancy.configurador-pasos.paso-carteras', [
            'carteras' => $carteras,
            'puedeCrear' => $this->puede('carteras.crear'),
            'puedeEditar' => $this->puede('carteras.editar'),
            'puedeEliminar' => $this->puede('carteras.eliminar'),
        ]);
    }

    private function descripcionOpcional(): ?string
    {
        $valor = trim((string) ($this->form['descripcion'] ?? ''));

        return $valor === '' ? null : $valor;
    }

    /**
     * Esta pantalla es de administración y NO cuelga del binding
     * `tenancy.proyecto_activo`: el proyecto lo fija la ruta
     * `/admin/proyectos/{proyecto:public_id}/configurar`. Se sobrescribe el
     * método del trait para que `autorizarEn()` y `exigirDelProyecto()`
     * comprueben contra ese proyecto y no contra un binding que aquí no existe.
     */
    protected function proyectoActivoId(): int
    {
        return (int) $this->proyecto->id;
    }

    private function puede(string $permiso): bool
    {
        $user = auth()->user();
        $projectId = (int) $this->proyecto->id;

        return $user !== null && ($user->tienePermiso($permiso, $projectId)
            || $user->tienePermiso('proyectos.configurar', $projectId));
    }

    private function autorizar(string $permiso = 'carteras.ver'): void
    {
        abort_unless($this->puede($permiso), 403);
    }
}
