<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire;

use App\Modules\Tenancy\Application\DTOs\RegistrarCarteraInput;
use App\Modules\Tenancy\Application\UseCases\RegistrarCartera;
use App\Modules\Tenancy\Domain\Exceptions\CodigoCarteraDuplicadoEnProyecto;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoCartera;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\CarteraModel;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Component;
use Throwable;

/**
 * CRUD de carteras del proyecto activo. Solo SUPERVISOR + ADMIN_GLOBAL.
 * Permiso: catalogos.gestionar (cartera es dato operativo del proyecto, no es módulo).
 *
 * Crear → UseCase RegistrarCartera (respeta invariantes Domain).
 * Editar nombre/descripcion → update directo (mismo patrón que AdminMandantes).
 * Activar/desactivar → toggle directo. Borrado físico no se permite — soft delete (eliminada_en).
 */
final class AdminCarterasProyecto extends Component
{
    public bool $formVisible = false;

    public ?int $editandoId = null;

    public string $busqueda = '';

    /** @var array<string, mixed> */
    public array $form = [
        'codigo' => '',
        'nombre' => '',
        'descripcion' => '',
    ];

    public function abrirFormCrear(): void
    {
        $this->editandoId = null;
        $this->form = ['codigo' => '', 'nombre' => '', 'descripcion' => ''];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $id): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $row = CarteraModel::query()->where('proyecto_id', $proyectoId)->find($id);
        if ($row === null) {
            return;
        }

        $this->editandoId = $id;
        $this->form = [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'descripcion' => (string) ($row->descripcion ?? ''),
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

    public function guardar(RegistrarCartera $useCase): void
    {
        $this->validate([
            'form.codigo' => ['required', 'string', 'max:80', 'regex:/^[A-Z0-9_-]+$/'],
            'form.nombre' => ['required', 'string', 'max:200'],
            'form.descripcion' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'form.codigo' => 'código',
            'form.nombre' => 'nombre',
            'form.descripcion' => 'descripción',
        ]);

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        if ($this->editandoId === null) {
            try {
                $useCase->execute(new RegistrarCarteraInput(
                    publicId: (string) Str::ulid(),
                    proyectoId: $proyectoId,
                    codigo: new CodigoCartera((string) $this->form['codigo']),
                    nombre: (string) $this->form['nombre'],
                    descripcion: $this->descripcionOpcional(),
                    creadaEn: new DateTimeImmutable,
                ));
            } catch (CodigoCarteraDuplicadoEnProyecto $e) {
                $this->addError('form.codigo', $e->getMessage());

                return;
            } catch (InvalidArgumentException $e) {
                $this->addError('form.codigo', $e->getMessage());

                return;
            } catch (Throwable $e) {
                $this->addError('form.codigo', $e->getMessage());

                return;
            }
        } else {
            $codigoAnterior = (string) CarteraModel::query()
                ->where('id', $this->editandoId)
                ->where('proyecto_id', $proyectoId)
                ->value('codigo');
            $codigoNuevo = strtoupper(trim((string) $this->form['codigo']));

            if ($codigoAnterior !== $codigoNuevo) {
                $duplicado = CarteraModel::query()
                    ->where('proyecto_id', $proyectoId)
                    ->where('codigo', $codigoNuevo)
                    ->where('id', '!=', $this->editandoId)
                    ->exists();
                if ($duplicado) {
                    $this->addError('form.codigo', 'Ya existe una cartera con ese código en el proyecto.');

                    return;
                }
            }

            CarteraModel::query()
                ->where('id', $this->editandoId)
                ->where('proyecto_id', $proyectoId)
                ->update([
                    'codigo' => $codigoNuevo,
                    'nombre' => (string) $this->form['nombre'],
                    'descripcion' => $this->descripcionOpcional(),
                ]);
        }

        $this->cerrarForm();
        session()->flash('admin-carteras-ok', 'Cartera guardada.');
    }

    public function desactivar(int $id): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        CarteraModel::query()
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->update(['activo' => false]);
        session()->flash('admin-carteras-ok', 'Cartera desactivada.');
    }

    public function activar(int $id): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        CarteraModel::query()
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->update(['activo' => true]);
        session()->flash('admin-carteras-ok', 'Cartera activada.');
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
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
                'c.id', 'c.public_id', 'c.codigo', 'c.nombre', 'c.descripcion',
                'c.activo', 'c.creada_en',
                DB::raw('count(cs.id) as total_casos'),
            ])
            ->groupBy('c.id', 'c.public_id', 'c.codigo', 'c.nombre', 'c.descripcion', 'c.activo', 'c.creada_en')
            ->orderBy('c.codigo')
            ->get();

        return view('tenancy::admin.carteras-proyecto', [
            'carteras' => $carteras,
        ]);
    }

    private function descripcionOpcional(): ?string
    {
        $v = trim((string) ($this->form['descripcion'] ?? ''));

        return $v === '' ? null : $v;
    }
}
