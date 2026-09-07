<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos;

use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use App\Support\Codigo\GeneradorCodigo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Paso 6 del wizard — motivos de no contacto y causas de gestión.
 *
 * Las causas van aquí porque son la otra mitad de la misma pregunta: por qué
 * salió así. Y porque hasta ahora no tenían pantalla en ninguna parte, ni
 * siquiera para el ADMIN_GLOBAL: `causas_gestion` sólo se leía. En el proyecto
 * de cobranza cuatro de los nueve resultados tienen `requiere_causa`, la tabla
 * estaba vacía, y esas cuatro gestiones eran imposibles de guardar.
 *
 * Schema sin `descripcion` ni `eliminada_en` en las dos tablas. Borrado físico
 * tras comprobar que ninguna gestión las referencia.
 */
final class PasoMotivosNoContacto extends Component
{
    public ProyectoModel $proyecto;

    public string $busqueda = '';

    public bool $formVisible = false;

    public ?int $editandoId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'codigo' => '',
        'nombre' => '',
        'orden' => 0,
        'activo' => true,
    ];

    /** Nombre de la causa que se está creando desde la barra. */
    public string $causaNueva = '';

    public function mount(ProyectoModel $proyecto): void
    {
        $this->authorize('proyectos.configurar', (int) $proyecto->id);
        $this->proyecto = $proyecto;
    }

    // -----------------------------------------------------------------
    // Causas de gestión
    // -----------------------------------------------------------------

    public function crearCausa(): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $nombre = trim($this->causaNueva);

        if ($nombre === '') {
            return;
        }

        $proyectoId = (int) $this->proyecto->id;
        $codigo = GeneradorCodigo::derivar($nombre, 50);

        if (DB::table('causas_gestion')->where('proyecto_id', $proyectoId)->where('codigo', $codigo)->exists()) {
            $this->addError('causaNueva', 'Ya hay una causa con ese nombre.');

            return;
        }

        $siguiente = (int) DB::table('causas_gestion')->where('proyecto_id', $proyectoId)->max('orden');

        DB::table('causas_gestion')->insert([
            'proyecto_id' => $proyectoId,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'activo' => true,
            'orden' => $siguiente + 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $this->causaNueva = '';
        $this->resetErrorBag('causaNueva');
        $this->dispatch('configuracion-paso-completado');
    }

    public function alternarCausa(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $actual = DB::table('causas_gestion')
            ->where('id', $id)->where('proyecto_id', (int) $this->proyecto->id)
            ->value('activo');

        if ($actual === null) {
            return;
        }

        DB::table('causas_gestion')
            ->where('id', $id)->where('proyecto_id', (int) $this->proyecto->id)
            ->update(['activo' => ! (bool) $actual, 'actualizada_en' => Carbon::now()]);
    }

    /** Una causa ya usada en una gestión no se borra: la gestión perdería el porqué. */
    public function eliminarCausa(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $proyectoId = (int) $this->proyecto->id;

        if (DB::table('gestiones')->where('causa_id', $id)->exists()) {
            session()->flash(
                'paso-motivos-no-contacto-error',
                'No se puede eliminar: hay gestiones registradas con esa causa.',
            );

            return;
        }

        DB::table('causas_gestion')->where('id', $id)->where('proyecto_id', $proyectoId)->delete();
    }

    public function abrirFormCrear(): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);
        $this->editandoId = null;
        $this->form = ['codigo' => '', 'nombre' => '', 'orden' => 0, 'activo' => true];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $row = DB::table('motivos_no_contacto')
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

    public function guardar(): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $this->validate([
            'form.codigo' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]{2,50}$/'],
            'form.nombre' => ['required', 'string', 'max:150'],
            'form.orden' => ['required', 'integer', 'min:0'],
            'form.activo' => ['required', 'boolean'],
        ], [], [
            'form.codigo' => 'código',
            'form.nombre' => 'nombre',
            'form.orden' => 'orden',
            'form.activo' => 'estado',
        ]);

        $proyectoId = (int) $this->proyecto->id;
        $codigo = strtoupper(trim((string) $this->form['codigo']));

        $duplicado = DB::table('motivos_no_contacto')
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', $codigo)
            ->when($this->editandoId !== null, fn ($q) => $q->where('id', '!=', $this->editandoId))
            ->exists();

        if ($duplicado) {
            $this->addError('form.codigo', 'Ya existe otro motivo con ese código en el proyecto.');

            return;
        }

        $payload = [
            'codigo' => $codigo,
            'nombre' => trim((string) $this->form['nombre']),
            'orden' => (int) $this->form['orden'],
            'activo' => (bool) $this->form['activo'],
            'actualizada_en' => Carbon::now(),
        ];

        if ($this->editandoId === null) {
            $payload['proyecto_id'] = $proyectoId;
            $payload['creada_en'] = Carbon::now();
            DB::table('motivos_no_contacto')->insert($payload);
        } else {
            DB::table('motivos_no_contacto')
                ->where('id', $this->editandoId)
                ->where('proyecto_id', $proyectoId)
                ->update($payload);
        }

        $this->cerrarForm();
        session()->flash('paso-motivos-no-contacto-ok', 'Motivo guardado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function eliminar(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $proyectoId = (int) $this->proyecto->id;

        $existe = DB::table('motivos_no_contacto')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->exists();

        if (! $existe) {
            return;
        }

        $tieneGestiones = DB::table('gestiones')
            ->where('motivo_no_contacto_id', $id)
            ->exists();

        if ($tieneGestiones) {
            session()->flash('paso-motivos-no-contacto-error', 'No se puede eliminar: hay gestiones registradas con este motivo.');

            return;
        }

        DB::table('motivos_no_contacto')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->delete();

        session()->flash('paso-motivos-no-contacto-ok', 'Motivo eliminado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function toggleActivo(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $actual = DB::table('motivos_no_contacto')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->value('activo');

        if ($actual === null) {
            return;
        }

        DB::table('motivos_no_contacto')
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

        $query = DB::table('motivos_no_contacto')
            ->where('proyecto_id', $proyectoId);

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('codigo', 'like', $like)
                    ->orWhere('nombre', 'like', $like);
            });
        }

        $motivos = $query
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'orden', 'activo']);

        return view('livewire.tenancy.configurador-pasos.paso-motivos-no-contacto', [
            'causas' => DB::table('causas_gestion')
                ->where('proyecto_id', $proyectoId)
                ->orderBy('orden')->orderBy('id')
                ->get(['id', 'codigo', 'nombre', 'activo']),
            'motivos' => $motivos,
        ]);
    }
}
