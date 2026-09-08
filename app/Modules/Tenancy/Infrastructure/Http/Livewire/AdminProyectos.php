<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire;

use App\Modules\Tenancy\Application\DTOs\RegistrarProyectoInput;
use App\Modules\Tenancy\Application\UseCases\RegistrarProyecto;
use App\Modules\Tenancy\Domain\Exceptions\CodigoProyectoDuplicadoEnMandante;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoProyecto;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use App\Support\Codigo\GeneradorCodigo;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * CRUD de proyectos para ADMIN_GLOBAL. Crea con el UseCase (respeta invariantes + dispara ProyectoCreado).
 * Edita y cambia estado vía modelo. El tipo_operacion NO es editable tras creación (§1.2 CLAUDE.md — un proyecto es de un solo tipo).
 */
final class AdminProyectos extends Component
{
    public bool $formVisible = false;

    /*
     * Sin #[Locked] a propósito, y conviene dejarlo escrito.
     *
     * Bloquear la propiedad impediría que el cliente la fije, que es la defensa
     * más fuerte. Pero entonces los tests de fuga no podrían forjar el id para
     * demostrar que el guard rechaza una fila ajena: Livewire lanza
     * CannotUpdateLockedPropertyException en el propio set() y la comprobación
     * de guardar() se vuelve inalcanzable desde una prueba.
     *
     * Quien carga el peso aquí es guardContraProyectoAjeno(), que mira el dueño
     * ACTUAL de la fila y está cubierto por tests. Añadir #[Locked] encima es
     * defensa en profundidad y vale la pena, pero exige reescribir esos tests
     * para que afirmen el bloqueo en vez del rechazo — un cambio deliberado, no
     * algo que colar en este commit.
     */
    public ?int $editandoId = null;

    public string $busqueda = '';

    public string $filtroTipo = '';

    /** @var array<string, mixed> */
    public array $form = [
        'mandante_id' => null,
        'codigo' => '',
        'nombre' => '',
        'descripcion' => '',
        'permite_autoasignacion' => false,
        'tipo_operacion' => 'cobranza',
        'fecha_inicio' => null,
        'fecha_fin' => null,
    ];

    public function abrirFormCrear(): void
    {
        $this->editandoId = null;
        $mandantesPermitidos = $this->mandantesPermitidos();
        $this->form = [
            'mandante_id' => $mandantesPermitidos === null
                ? (int) (DB::table('mandantes')->where('activo', true)->value('id') ?? 0)
                : (int) ($mandantesPermitidos[0] ?? 0),
            'codigo' => '',
            'nombre' => '',
            'descripcion' => '',
            'permite_autoasignacion' => false,
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

        $this->guardContraMandanteAjeno((int) $row->mandante_id);

        $this->editandoId = $id;
        $this->form = [
            'mandante_id' => (int) $row->mandante_id,
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'descripcion' => (string) ($row->descripcion ?? ''),
            'permite_autoasignacion' => (bool) ($row->permite_autoasignacion ?? false),
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
            'form.codigo' => GeneradorCodigo::reglaValidacion(80),
            'form.nombre' => ['required', 'string', 'max:200'],
            'form.descripcion' => ['nullable', 'string', 'max:1000'],
            'form.tipo_operacion' => ['required', 'in:cobranza,cx,venta,servicio'],
            'form.fecha_inicio' => ['nullable', 'date'],
            'form.fecha_fin' => ['nullable', 'date', 'after_or_equal:form.fecha_inicio'],
            'form.permite_autoasignacion' => ['boolean'],
        ], [], [
            'form.mandante_id' => 'mandante',
            'form.codigo' => 'código',
            'form.nombre' => 'nombre',
            'form.tipo_operacion' => 'tipo de operación',
            'form.fecha_inicio' => 'fecha de inicio',
            'form.fecha_fin' => 'fecha de fin',
        ]);

        $mandanteId = (int) $this->form['mandante_id'];
        $this->guardContraMandanteAjeno($mandanteId);

        // Y el de ORIGEN. Validar solo el destino dejaba pasar el caso que
        // importa: apuntar al proyecto de otro cliente y traérselo al propio.
        if ($this->editandoId !== null) {
            $this->guardContraProyectoAjeno($this->editandoId);
        }

        $codigoInput = trim((string) ($this->form['codigo'] ?? ''));
        $codigoBase = $codigoInput === ''
            ? GeneradorCodigo::derivar((string) ($this->form['nombre'] ?? ''), 80)
            : GeneradorCodigo::normalizar($codigoInput, 80);

        $codigoFinal = GeneradorCodigo::resolverConflicto(
            $codigoBase,
            function (string $candidato) use ($mandanteId): bool {
                $q = ProyectoModel::query()
                    ->where('mandante_id', $mandanteId)
                    ->where('codigo', $candidato);
                if ($this->editandoId !== null) {
                    $q->where('id', '!=', $this->editandoId);
                }

                return $q->exists();
            },
            80,
        );
        $this->form['codigo'] = $codigoFinal;

        $fechaInicio = ! empty($this->form['fecha_inicio']) ? new DateTimeImmutable((string) $this->form['fecha_inicio']) : null;
        $fechaFin = ! empty($this->form['fecha_fin']) ? new DateTimeImmutable((string) $this->form['fecha_fin']) : null;

        if ($this->editandoId === null) {
            try {
                $useCase->execute(new RegistrarProyectoInput(
                    publicId: (string) Str::ulid(),
                    mandanteId: $mandanteId,
                    codigo: new CodigoProyecto($codigoFinal),
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
            // Edición: se permite cambiar nombre, descripción y vigencias. tipo_operacion queda
            // BLOQUEADO (invariante §1.2). El conflicto de código se resolvió arriba (sufijado).
            ProyectoModel::query()->where('id', $this->editandoId)->update([
                // mandante_id NO se actualiza: el dueño de un proyecto no es un
                // campo editable. Estaba aquí pese a que el comentario de arriba
                // decía lo contrario, y con él un admin podía mover el proyecto
                // de otro cliente al suyo — arrastrando sus casos, personas y
                // carteras, que cuelgan de proyecto_id y no de mandante_id.
                'codigo' => $codigoFinal,
                'nombre' => (string) $this->form['nombre'],
                'descripcion' => $this->textoOpcional('descripcion'),
                'permite_autoasignacion' => (bool) ($this->form['permite_autoasignacion'] ?? false),
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                // tipo_operacion NO se actualiza — invariante CLAUDE.md §1.2.3.
            ]);
        }

        $this->cerrarForm();
        session()->flash('admin-proyectos-ok', __('tenancy.flash_proyecto_guardado'));
    }

    public function desactivar(int $id): void
    {
        $row = ProyectoModel::query()->find($id);
        if ($row === null) {
            return;
        }
        $this->guardContraMandanteAjeno((int) $row->mandante_id);
        ProyectoModel::query()->where('id', $id)->update(['activo' => false]);
        session()->flash('admin-proyectos-ok', __('tenancy.flash_proyecto_desactivado'));
    }

    public function activar(int $id): void
    {
        $row = ProyectoModel::query()->find($id);
        if ($row === null) {
            return;
        }
        $this->guardContraMandanteAjeno((int) $row->mandante_id);
        ProyectoModel::query()->where('id', $id)->update(['activo' => true]);
        session()->flash('admin-proyectos-ok', __('tenancy.flash_proyecto_activado'));
    }

    /**
     * Retirar un proyecto de circulación, que no es lo mismo que apagarlo.
     *
     * `desactivar()` lo pausa: sigue en esta lista y se vuelve a encender con un
     * clic. Archivar lo saca de la vista — de la administración, del selector y
     * del middleware que resuelve el proyecto activo, los tres caminos por los
     * que se llega a un proyecto — y no hay botón de vuelta.
     *
     * Se marca `eliminada_en` y no se borra la fila (§4): del `proyecto_id`
     * cuelga la operación entera, y una gestión no se borra nunca (§13.11).
     */
    public function archivar(int $id): void
    {
        $row = ProyectoModel::query()->find($id);
        if ($row === null) {
            return;
        }
        $this->guardContraMandanteAjeno((int) $row->mandante_id);

        // Se apaga también el `activo`. Por un lado, quien algún día desarchive
        // tendrá que reactivarlo a mano en vez de encontrarse el proyecto
        // operando otra vez por sorpresa. Por otro, las consultas que sólo miran
        // esa bandera y no el borrado lógico tampoco lo dejarán entrar.
        ProyectoModel::query()->where('id', $id)->update([
            'activo' => false,
            'eliminada_en' => now(),
        ]);

        $this->cerrarForm();
        session()->flash('admin-proyectos-ok', __('tenancy.flash_proyecto_archivado'));
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

        // F39: scope por mandante para ADMIN_MANDANTE.
        $mandantesPermitidos = $this->mandantesPermitidos();
        if ($mandantesPermitidos !== null) {
            $query->whereIn('p.mandante_id', $mandantesPermitidos);
        }

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

        $mandantesQuery = DB::table('mandantes')
            ->whereNull('eliminada_en')
            ->orderBy('codigo');

        if ($mandantesPermitidos !== null) {
            $mandantesQuery->whereIn('id', $mandantesPermitidos);
        }

        $mandantes = $mandantesQuery->get(['id', 'codigo', 'nombre', 'activo']);

        // El cliente del proyecto en edición se resuelve aquí y no en la vista:
        // hacerlo en Blade obliga a un @php en línea que, con una expresión
        // anidada, rompe la compilación del resto de la plantilla — y además
        // §13.4 dice que la lógica no vive en la vista.
        $mandanteEnEdicion = $this->editandoId === null
            ? null
            : $mandantes->firstWhere('id', (int) ($this->form['mandante_id'] ?? 0));

        // Y el proyecto mismo, porque los botones de baja dependen de si está
        // encendido. La vista lo resolvía con un find() suyo: una consulta
        // dentro de la plantilla es una consulta que nadie puede acotar (§13.4).
        $proyectoEnEdicion = $this->editandoId === null
            ? null
            : ProyectoModel::query()->find($this->editandoId);

        // Dentro de un cliente, repetir su nombre en cada fila es ruido: ya lo
        // dice el contexto. La columna solo aparece cuando de verdad hay mezcla.
        $dentroDeUnCliente = app()->bound('tenancy.mandante_activo');

        return view('tenancy::admin.proyectos', [
            'dentroDeUnCliente' => $dentroDeUnCliente,
            'proyectos' => $proyectos,
            'mandantes' => $mandantes,
            'mandanteEnEdicion' => $mandanteEnEdicion,
            'proyectoEnEdicion' => $proyectoEnEdicion,
        ]);
    }

    private function textoOpcional(string $key): ?string
    {
        $v = trim((string) ($this->form[$key] ?? ''));

        return $v === '' ? null : $v;
    }

    /**
     * F39: lista de mandantes que el user puede tocar. Null = sin restricción
     * (ADMIN_GLOBAL). Array vacío = no autorizado.
     *
     * @return list<int>|null
     */
    private function mandantesPermitidos(): ?array
    {
        $usuario = auth()->user();
        if ($usuario === null || $usuario->esAdminGlobal()) {
            return null;
        }

        return $usuario->mandantesAdministrados();
    }

    /**
     * El proyecto que se está editando pertenece a un mandante que este usuario
     * administra. Se mira el dueño ACTUAL de la fila, no el del formulario.
     */
    private function guardContraProyectoAjeno(int $proyectoId): void
    {
        $mandantes = $this->mandantesPermitidos();

        if ($mandantes === null) {
            return; // ADMIN_GLOBAL.
        }

        $duenoActual = ProyectoModel::query()->where('id', $proyectoId)->value('mandante_id');

        if ($duenoActual === null || ! in_array((int) $duenoActual, $mandantes, true)) {
            abort(403, 'Ese proyecto no pertenece a tu alcance.');
        }
    }

    private function guardContraMandanteAjeno(int $mandanteId): void
    {
        $permitidos = $this->mandantesPermitidos();
        if ($permitidos === null) {
            return; // ADMIN_GLOBAL
        }

        if (! in_array($mandanteId, $permitidos, true)) {
            abort(403, 'No puedes operar proyectos de otro mandante.');
        }
    }
}
