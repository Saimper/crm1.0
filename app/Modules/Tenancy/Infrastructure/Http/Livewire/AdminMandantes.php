<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire;

use App\Modules\Integracion\Application\UseCases\EmitirSanctumTokenDesdeJwt;
use App\Modules\Tenancy\Application\DTOs\RegistrarMandanteInput;
use App\Modules\Tenancy\Application\UseCases\AdministrarDisponibilidad;
use App\Modules\Tenancy\Application\UseCases\RegistrarMandante;
use App\Modules\Tenancy\Domain\Exceptions\CodigoMandanteDuplicado;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoMandante;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\MandanteModel;
use App\Support\Codigo\GeneradorCodigo;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * CRUD de mandantes para ADMIN_GLOBAL. Usa el UseCase RegistrarMandante para respetar
 * invariantes de dominio al crear; edición y cambio de estado operan directo sobre el modelo.
 *
 * El `admin.global` de la ruta protege la PÁGINA, no el commit: cada acción de
 * Livewire es un POST aparte a /livewire/update que reentra en el componente sin
 * volver a pasar por el middleware. De ahí `soloAdminGlobal()` al principio de
 * cada método que lee o escribe un mandante.
 *
 * Aquí no aplica el trait AutorizaEnProyectoActivo: /admin/mandantes cuelga de
 * `admin.global` y no tiene proyecto activo, así que no hay contra qué evaluar un
 * permiso por proyecto. El mandante es además la raíz del árbol de tenancy: no
 * pertenece a ningún proyecto, luego tampoco hay pertenencia que comprobar. La
 * guarda correcta es la del rol global, al estilo de AdminUsuarios::soloAdminGlobal().
 */
final class AdminMandantes extends Component
{
    public bool $formVisible = false;

    /**
     * Bloqueada: la fija `abrirFormEditar()` en el servidor y decide a qué fila
     * apunta el UPDATE de `guardar()`. Sin `#[Locked]`, el cliente podía cambiarla
     * entre abrir el formulario y guardar, y escribir sobre otro mandante.
     */
    #[Locked]
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
        $this->soloAdminGlobal();

        $this->editandoId = null;
        $this->form = ['codigo' => '', 'nombre' => '', 'documento' => ''];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $id): void
    {
        // No escribe, pero vuelca código, nombre y documento del mandante en una
        // propiedad pública: es una lectura de datos de otro cliente y se cierra igual.
        $this->soloAdminGlobal();

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
        $this->soloAdminGlobal();

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
                $q = MandanteModel::withTrashed()->where('codigo', $candidato);
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

    /**
     * Dar de baja a un cliente le cierra la puerta, y también las que ya tenía
     * abiertas.
     *
     * Las dos entradas del SSO respetan la baja desde siempre —el handshake y
     * la firma HMAC filtran por `activo`— pero los tokens ya emitidos duran
     * ocho horas y seguían funcionando: un cliente desactivado a las nueve de
     * la mañana leía fichas de personas hasta las cinco de la tarde. Por eso el
     * mandante va en el nombre del token: para poder encontrarlos y borrarlos.
     */
    public function desactivar(int $id): void
    {
        $this->soloAdminGlobal();

        DB::transaction(function () use ($id): void {
            MandanteModel::query()->where('id', $id)->update(['activo' => false]);

            DB::table('personal_access_tokens')
                ->where('name', EmitirSanctumTokenDesdeJwt::nombreDeToken($id))
                ->delete();
        });

        session()->flash('admin-mandantes-ok', 'Mandante desactivado.');
    }

    public function activar(int $id): void
    {
        $this->soloAdminGlobal();

        MandanteModel::query()->where('id', $id)->update(['activo' => true]);
        session()->flash('admin-mandantes-ok', 'Mandante activado.');
    }

    public function eliminar(int $id, AdministrarDisponibilidad $disponibilidad): void
    {
        $this->soloAdminGlobal();
        DB::transaction(function () use ($id, $disponibilidad): void {
            $disponibilidad->eliminarMandante($id);
            DB::table('personal_access_tokens')
                ->where('name', EmitirSanctumTokenDesdeJwt::nombreDeToken($id))->delete();
        });
        $this->cerrarForm();
        session()->flash('admin-mandantes-ok', 'Mandante eliminado. Su historial se conserva.');
    }

    public function render(): View
    {
        // El listado es el inventario completo de clientes del BPO. Cada commit
        // de Livewire lo vuelve a pintar, así que la guarda va también aquí.
        $this->soloAdminGlobal();

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

    private function soloAdminGlobal(): void
    {
        $u = auth()->user();
        if ($u === null || ! $u->esAdminGlobal()) {
            abort(403, 'Solo ADMIN_GLOBAL puede administrar mandantes.');
        }
    }

    private function documentoOpcional(): ?string
    {
        $v = trim((string) ($this->form['documento'] ?? ''));

        return $v === '' ? null : $v;
    }
}
