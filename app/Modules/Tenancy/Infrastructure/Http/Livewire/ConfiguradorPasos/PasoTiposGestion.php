<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos;

use App\Modules\Tenancy\Application\UseCases\ConfigurarCanalesTipoGestion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use stdClass;

/**
 * Paso 4 del wizard — canales y tipos de gestión del proyecto.
 *
 * Los canales viven aquí y no en un paso propio porque la cascada de la Vista
 * de Trabajo empieza en el canal y sigue por el tipo: se configuran juntos
 * porque se usan juntos.
 *
 * `canales` es el catálogo global (§8) y `canal_proyecto` dice qué hace este
 * proyecto con él: cuáles usa, en qué orden, con qué nombre, y si el canal pide
 * duración o admite adjunto.
 *
 * Schema de `tipos_gestion` sin `descripcion` ni `eliminada_en`. Basta con
 * bloquear el borrado si hay gestiones que referencian el tipo.
 */
final class PasoTiposGestion extends Component
{
    #[Locked]
    public ProyectoModel $proyecto;

    public string $busqueda = '';

    public bool $formVisible = false;

    #[Locked]
    public ?int $editandoId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'codigo' => '',
        'nombre' => '',
        'orden' => 0,
        'activo' => true,
        'canales' => [],
    ];

    public function mount(ProyectoModel $proyecto): void
    {
        $this->authorize('proyectos.configurar', (int) $proyecto->id);
        $this->proyecto = $proyecto;
    }

    // -----------------------------------------------------------------
    // Canales del proyecto
    // -----------------------------------------------------------------

    public function alternarCanal(int $canalId): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $fila = $this->filaDeCanal($canalId);

        if ($fila === null) {
            return;
        }

        DB::table('canal_proyecto')
            ->where('id', $fila->id)
            ->update(['activo' => ! (bool) $fila->activo, 'actualizada_en' => Carbon::now()]);
    }

    public function alternarBanderaCanal(int $canalId, string $bandera): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        if (! in_array($bandera, ['requiere_duracion', 'permite_adjunto'], true)) {
            return;
        }

        $fila = $this->filaDeCanal($canalId);

        if ($fila === null) {
            return;
        }

        DB::table('canal_proyecto')
            ->where('id', $fila->id)
            ->update([$bandera => ! (bool) $fila->{$bandera}, 'actualizada_en' => Carbon::now()]);
    }

    public function moverCanal(int $canalId, int $direccion): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $canales = $this->canalesDelProyecto();
        $posicion = $canales->search(fn (stdClass $c): bool => (int) $c->canal_id === $canalId);

        if ($posicion === false) {
            return;
        }

        $destino = $posicion + ($direccion < 0 ? -1 : 1);

        if ($destino < 0 || $destino >= $canales->count()) {
            return;
        }

        $a = $canales[$posicion];
        $b = $canales[$destino];

        DB::table('canal_proyecto')->where('id', $a->id)->update(['orden' => (int) $b->orden, 'actualizada_en' => Carbon::now()]);
        DB::table('canal_proyecto')->where('id', $b->id)->update(['orden' => (int) $a->orden, 'actualizada_en' => Carbon::now()]);
    }

    /**
     * El nombre que este proyecto le da al canal. Vacío devuelve al nombre del
     * catálogo global en vez de dejar la etiqueta en blanco.
     */
    public function renombrarCanal(int $canalId, string $etiqueta): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $fila = $this->filaDeCanal($canalId);

        if ($fila === null) {
            return;
        }

        $limpia = trim($etiqueta);

        DB::table('canal_proyecto')
            ->where('id', $fila->id)
            ->update([
                'etiqueta' => $limpia === '' ? null : mb_substr($limpia, 0, 150),
                'actualizada_en' => Carbon::now(),
            ]);
    }

    private function filaDeCanal(int $canalId): ?stdClass
    {
        return DB::table('canal_proyecto')
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->where('canal_id', $canalId)
            ->first(['id', 'activo', 'requiere_duracion', 'permite_adjunto']);
    }

    /** @return Collection<int, stdClass> */
    private function canalesDelProyecto(): Collection
    {
        return DB::table('canal_proyecto as cp')
            ->join('canales as c', 'c.id', '=', 'cp.canal_id')
            ->where('cp.proyecto_id', (int) $this->proyecto->id)
            ->orderBy('cp.orden')
            ->orderBy('c.id')
            ->get([
                'cp.id', 'cp.canal_id', 'cp.etiqueta', 'cp.activo', 'cp.orden',
                'cp.requiere_duracion', 'cp.permite_adjunto',
                'c.codigo', 'c.nombre as nombre_global',
            ])
            ->values();
    }

    public function abrirFormCrear(): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);
        $this->editandoId = null;
        $this->form = ['codigo' => '', 'nombre' => '', 'orden' => 0, 'activo' => true, 'canales' => []];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $row = DB::table('tipos_gestion')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->first();

        if ($row === null) {
            return;
        }

        $this->editandoId = $id;
        $this->form = [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'orden' => (int) $row->orden,
            'activo' => (bool) $row->activo,
            'canales' => DB::table('canal_tipo_gestion')->where('proyecto_id', (int) $this->proyecto->id)
                ->where('tipo_gestion_id', $id)->pluck('canal_id')->map(fn ($id): string => (string) $id)->all(),
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

    public function guardar(ConfigurarCanalesTipoGestion $canales): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $this->validate([
            'form.codigo' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]{2,50}$/'],
            'form.nombre' => ['required', 'string', 'max:150'],
            'form.orden' => ['required', 'integer', 'min:0'],
            'form.activo' => ['required', 'boolean'],
            'form.canales' => ['array'],
            'form.canales.*' => ['integer', 'distinct'],
        ], [], [
            'form.codigo' => 'código',
            'form.nombre' => 'nombre',
            'form.orden' => 'orden',
            'form.activo' => 'estado',
        ]);

        $proyectoId = (int) $this->proyecto->id;
        $codigo = strtoupper(trim((string) $this->form['codigo']));

        $duplicado = DB::table('tipos_gestion')
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', $codigo)
            ->when($this->editandoId !== null, fn ($q) => $q->where('id', '!=', $this->editandoId))
            ->exists();

        if ($duplicado) {
            $this->addError('form.codigo', 'Ya existe otro tipo de gestión con ese código en el proyecto.');

            return;
        }

        $payload = [
            'codigo' => $codigo,
            'nombre' => trim((string) $this->form['nombre']),
            'orden' => (int) $this->form['orden'],
            'activo' => (bool) $this->form['activo'],
            'actualizada_en' => Carbon::now(),
        ];

        DB::transaction(function () use ($payload, $proyectoId, $canales): void {
            if ($this->editandoId === null) {
                $tipoId = (int) DB::table('tipos_gestion')->insertGetId($payload + [
                    'proyecto_id' => $proyectoId, 'creada_en' => Carbon::now(),
                ]);
            } else {
                $tipoId = $this->editandoId;
                DB::table('tipos_gestion')->where('id', $tipoId)->where('proyecto_id', $proyectoId)->update($payload);
            }
            $canales->execute($proyectoId, $tipoId, array_map('intval', $this->form['canales'] ?? []));
        });

        $this->cerrarForm();
        session()->flash('paso-tipos-gestion-ok', 'Tipo de gestión guardado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function eliminar(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $proyectoId = (int) $this->proyecto->id;

        $existe = DB::table('tipos_gestion')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->exists();

        if (! $existe) {
            return;
        }

        $tieneGestiones = DB::table('gestiones')
            ->where('tipo_gestion_id', $id)
            ->exists();

        if ($tieneGestiones) {
            session()->flash('paso-tipos-gestion-error', 'No se puede eliminar: hay gestiones registradas con este tipo.');

            return;
        }

        DB::table('tipos_gestion')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->delete();

        session()->flash('paso-tipos-gestion-ok', 'Tipo de gestión eliminado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function toggleActivo(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $actual = DB::table('tipos_gestion')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->value('activo');

        if ($actual === null) {
            return;
        }

        DB::table('tipos_gestion')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->update([
                'activo' => ! (bool) $actual,
                'actualizada_en' => Carbon::now(),
            ]);

        $this->dispatch('configuracion-paso-completado');
    }

    public function render(): View
    {
        $proyectoId = (int) $this->proyecto->id;
        $busqueda = trim($this->busqueda);

        $query = DB::table('tipos_gestion')
            ->where('proyecto_id', $proyectoId);

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('codigo', 'like', $like)
                    ->orWhere('nombre', 'like', $like);
            });
        }

        $tipos = $query
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'orden', 'activo']);

        return view('livewire.tenancy.configurador-pasos.paso-tipos-gestion', [
            'canales' => $this->canalesDelProyecto(),
            'tipos' => $tipos,
            'canalesPorTipo' => DB::table('canal_tipo_gestion as ctg')
                ->join('canales as c', 'c.id', '=', 'ctg.canal_id')->where('ctg.proyecto_id', $proyectoId)
                ->orderBy('c.nombre')->get(['ctg.tipo_gestion_id', 'c.nombre'])->groupBy('tipo_gestion_id'),
        ]);
    }
}
