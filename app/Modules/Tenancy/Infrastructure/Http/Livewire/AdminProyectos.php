<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire;

use App\Modules\Tenancy\Application\DTOs\RegistrarProyectoInput;
use App\Modules\Tenancy\Application\UseCases\RegistrarProyecto;
use App\Modules\Tenancy\Domain\Exceptions\CodigoProyectoDuplicadoEnMandante;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoProyecto;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

/**
 * CRUD de proyectos para ADMIN_GLOBAL. Crea con el UseCase (respeta invariantes + dispara ProyectoCreado).
 * Edita y cambia estado vía modelo. El tipo_operacion NO es editable tras creación (§1.2 CLAUDE.md — un proyecto es de un solo tipo).
 */
final class AdminProyectos extends Component
{
    public bool $formVisible = false;

    public ?int $editandoId = null;

    public string $busqueda = '';

    public string $filtroTipo = '';

    /** @var array<string, mixed> */
    public array $form = [
        'mandante_id' => null,
        'codigo' => '',
        'nombre' => '',
        'descripcion' => '',
        'tipo_operacion' => 'cobranza',
        'fecha_inicio' => null,
        'fecha_fin' => null,
    ];

    public function abrirFormCrear(): void
    {
        $this->editandoId = null;
        $this->form = [
            'mandante_id' => (int) (DB::table('mandantes')->where('activo', true)->value('id') ?? 0),
            'codigo' => '',
            'nombre' => '',
            'descripcion' => '',
            'tipo_operacion' => 'cobranza',
            'fecha_inicio' => null,
            'fecha_fin' => null,
        ];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $id): void
    {
        $row = ProyectoModel::query()->find($id);
        if ($row === null) {
            return;
        }

        $this->editandoId = $id;
        $this->form = [
            'mandante_id' => (int) $row->mandante_id,
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'descripcion' => (string) ($row->descripcion ?? ''),
            'tipo_operacion' => (string) $row->tipo_operacion,
            'fecha_inicio' => $row->fecha_inicio ? (string) $row->fecha_inicio : null,
            'fecha_fin' => $row->fecha_fin ? (string) $row->fecha_fin : null,
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

    public function guardar(RegistrarProyecto $useCase): void
    {
        $this->validate([
            'form.mandante_id' => ['required', 'integer', 'exists:mandantes,id'],
            'form.codigo' => ['required', 'string', 'max:80', 'regex:/^[A-Z0-9_]+$/'],
            'form.nombre' => ['required', 'string', 'max:200'],
            'form.descripcion' => ['nullable', 'string', 'max:1000'],
            'form.tipo_operacion' => ['required', 'in:cobranza,cx,venta,servicio'],
            'form.fecha_inicio' => ['nullable', 'date'],
            'form.fecha_fin' => ['nullable', 'date', 'after_or_equal:form.fecha_inicio'],
        ], [], [
            'form.mandante_id' => 'mandante',
            'form.codigo' => 'código',
            'form.nombre' => 'nombre',
            'form.tipo_operacion' => 'tipo de operación',
            'form.fecha_inicio' => 'fecha de inicio',
            'form.fecha_fin' => 'fecha de fin',
        ]);

        $fechaInicio = ! empty($this->form['fecha_inicio']) ? new DateTimeImmutable((string) $this->form['fecha_inicio']) : null;
        $fechaFin = ! empty($this->form['fecha_fin']) ? new DateTimeImmutable((string) $this->form['fecha_fin']) : null;

        if ($this->editandoId === null) {
            try {
                $useCase->execute(new RegistrarProyectoInput(
                    publicId: (string) Str::ulid(),
                    mandanteId: (int) $this->form['mandante_id'],
                    codigo: new CodigoProyecto((string) $this->form['codigo']),
                    nombre: (string) $this->form['nombre'],
                    descripcion: $this->textoOpcional('descripcion'),
                    tipoOperacion: TipoOperacion::from((string) $this->form['tipo_operacion']),
                    fechaInicio: $fechaInicio,
                    fechaFin: $fechaFin,
                    creadaEn: new DateTimeImmutable,
                ));
            } catch (CodigoProyectoDuplicadoEnMandante $e) {
                $this->addError('form.codigo', $e->getMessage());

                return;
            } catch (Throwable $e) {
                $this->addError('form.codigo', $e->getMessage());

                return;
            }
        } else {
            // Edición: se permite cambiar nombre, descripción y vigencias. Código y mandante requieren
            // validación explícita de unicidad; tipo_operacion queda BLOQUEADO (invariante §1.2).
            $codigoNuevo = (string) $this->form['codigo'];
            $mandanteNuevo = (int) $this->form['mandante_id'];
            $duplicado = ProyectoModel::query()
                ->where('mandante_id', $mandanteNuevo)
                ->where('codigo', $codigoNuevo)
                ->where('id', '!=', $this->editandoId)
                ->exists();
            if ($duplicado) {
                $this->addError('form.codigo', 'Ese mandante ya tiene un proyecto con ese código.');

                return;
            }

            ProyectoModel::query()->where('id', $this->editandoId)->update([
                'mandante_id' => $mandanteNuevo,
                'codigo' => $codigoNuevo,
                'nombre' => (string) $this->form['nombre'],
                'descripcion' => $this->textoOpcional('descripcion'),
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                // tipo_operacion NO se actualiza — invariante CLAUDE.md §1.2.3.
            ]);
        }

        $this->cerrarForm();
        session()->flash('admin-proyectos-ok', 'Proyecto guardado.');
    }

    public function desactivar(int $id): void
    {
        ProyectoModel::query()->where('id', $id)->update(['activo' => false]);
        session()->flash('admin-proyectos-ok', 'Proyecto desactivado.');
    }

    public function activar(int $id): void
    {
        ProyectoModel::query()->where('id', $id)->update(['activo' => true]);
        session()->flash('admin-proyectos-ok', 'Proyecto activado.');
    }

    public function render(): View
    {
        $busqueda = trim($this->busqueda);
        $query = DB::table('proyectos as p')
            ->leftJoin('mandantes as m', 'm.id', '=', 'p.mandante_id')
            ->leftJoin('carteras as ca', function ($join): void {
                $join->on('ca.proyecto_id', '=', 'p.id')->whereNull('ca.eliminada_en');
            })
            ->whereNull('p.eliminada_en');

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('p.codigo', 'like', $like)
                    ->orWhere('p.nombre', 'like', $like)
                    ->orWhere('m.codigo', 'like', $like)
                    ->orWhere('m.nombre', 'like', $like);
            });
        }

        if ($this->filtroTipo !== '') {
            $query->where('p.tipo_operacion', $this->filtroTipo);
        }

        $proyectos = $query
            ->select([
                'p.id', 'p.public_id', 'p.codigo', 'p.nombre', 'p.tipo_operacion',
                'p.activo', 'p.fecha_inicio', 'p.fecha_fin',
                'm.codigo as mandante_codigo', 'm.nombre as mandante_nombre',
                DB::raw('count(ca.id) as total_carteras'),
            ])
            ->groupBy(
                'p.id', 'p.public_id', 'p.codigo', 'p.nombre', 'p.tipo_operacion',
                'p.activo', 'p.fecha_inicio', 'p.fecha_fin',
                'm.codigo', 'm.nombre',
            )
            ->orderBy('m.codigo')
            ->orderBy('p.codigo')
            ->get();

        $mandantes = DB::table('mandantes')
            ->whereNull('eliminada_en')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'activo']);

        return view('tenancy::admin.proyectos', [
            'proyectos' => $proyectos,
            'mandantes' => $mandantes,
        ]);
    }

    private function textoOpcional(string $key): ?string
    {
        $v = trim((string) ($this->form[$key] ?? ''));

        return $v === '' ? null : $v;
    }
}
