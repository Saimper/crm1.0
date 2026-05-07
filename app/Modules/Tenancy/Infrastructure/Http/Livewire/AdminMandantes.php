<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire;

use App\Modules\Tenancy\Application\DTOs\RegistrarMandanteInput;
use App\Modules\Tenancy\Application\UseCases\RegistrarMandante;
use App\Modules\Tenancy\Domain\Exceptions\CodigoMandanteDuplicado;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoMandante;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\MandanteModel;
use App\Support\Codigo\GeneradorCodigo;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

/**
 * CRUD de mandantes para ADMIN_GLOBAL. Usa el UseCase RegistrarMandante para respetar
 * invariantes de dominio al crear; edición y cambio de estado operan directo sobre el modelo.
 */
final class AdminMandantes extends Component
{
    public bool $formVisible = false;

    public ?int $editandoId = null;

    public string $busqueda = '';

    /** @var array<string, mixed> */
    public array $form = [
        'codigo' => '',
        'nombre' => '',
        'documento' => '',
    ];

    public function abrirFormCrear(): void
    {
        $this->editandoId = null;
        $this->form = ['codigo' => '', 'nombre' => '', 'documento' => ''];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $id): void
    {
        $row = MandanteModel::query()->find($id);
        if ($row === null) {
            return;
        }

        $this->editandoId = $id;
        $this->form = [
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'documento' => (string) ($row->documento ?? ''),
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

    public function guardar(RegistrarMandante $useCase): void
    {
        $this->validate([
            'form.codigo' => GeneradorCodigo::reglaValidacion(50),
            'form.nombre' => ['required', 'string', 'max:200'],
            'form.documento' => ['nullable', 'string', 'max:80'],
        ], [], [
            'form.codigo' => 'código',
            'form.nombre' => 'nombre',
            'form.documento' => 'documento',
        ]);

        $codigoInput = trim((string) ($this->form['codigo'] ?? ''));
        $codigoBase = $codigoInput === ''
            ? GeneradorCodigo::derivar((string) ($this->form['nombre'] ?? ''), 50)
            : GeneradorCodigo::normalizar($codigoInput, 50);

        $codigoFinal = GeneradorCodigo::resolverConflicto(
            $codigoBase,
            function (string $candidato): bool {
                $q = MandanteModel::query()->where('codigo', $candidato);
                if ($this->editandoId !== null) {
                    $q->where('id', '!=', $this->editandoId);
                }

                return $q->exists();
            },
            50,
        );
        $this->form['codigo'] = $codigoFinal;

        if ($this->editandoId === null) {
            try {
                $useCase->execute(new RegistrarMandanteInput(
                    publicId: (string) Str::ulid(),
                    codigo: new CodigoMandante($codigoFinal),
                    nombre: (string) $this->form['nombre'],
                    documento: $this->documentoOpcional(),
                    creadaEn: new DateTimeImmutable,
                ));
            } catch (CodigoMandanteDuplicado $e) {
                $this->addError('form.codigo', $e->getMessage());

                return;
            } catch (Throwable $e) {
                $this->addError('form.codigo', $e->getMessage());

                return;
            }
        } else {
            MandanteModel::query()->where('id', $this->editandoId)->update([
                'codigo' => $codigoFinal,
                'nombre' => (string) $this->form['nombre'],
                'documento' => $this->documentoOpcional(),
            ]);
        }

        $this->cerrarForm();
        session()->flash('admin-mandantes-ok', 'Mandante guardado.');
    }

    public function desactivar(int $id): void
    {
        MandanteModel::query()->where('id', $id)->update(['activo' => false]);
        session()->flash('admin-mandantes-ok', 'Mandante desactivado.');
    }

    public function activar(int $id): void
    {
        MandanteModel::query()->where('id', $id)->update(['activo' => true]);
        session()->flash('admin-mandantes-ok', 'Mandante activado.');
    }

    public function render(): View
    {
        $busqueda = trim($this->busqueda);
        $query = DB::table('mandantes as m')
            ->leftJoin('proyectos as p', function ($join): void {
                $join->on('p.mandante_id', '=', 'm.id')->whereNull('p.eliminada_en');
            })
            ->whereNull('m.eliminada_en');

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('m.codigo', 'like', $like)
                    ->orWhere('m.nombre', 'like', $like)
                    ->orWhere('m.documento', 'like', $like);
            });
        }

        $mandantes = $query
            ->select([
                'm.id', 'm.public_id', 'm.codigo', 'm.nombre', 'm.documento', 'm.activo', 'm.creada_en',
                DB::raw('count(p.id) as total_proyectos'),
            ])
            ->groupBy('m.id', 'm.public_id', 'm.codigo', 'm.nombre', 'm.documento', 'm.activo', 'm.creada_en')
            ->orderBy('m.codigo')
            ->get();

        return view('tenancy::admin.mandantes', [
            'mandantes' => $mandantes,
        ]);
    }

    private function documentoOpcional(): ?string
    {
        $v = trim((string) ($this->form['documento'] ?? ''));

        return $v === '' ? null : $v;
    }
}
