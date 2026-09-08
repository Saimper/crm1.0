<?php

declare(strict_types=1);

namespace App\Modules\Campanas\Infrastructure\Http\Livewire;

use App\Modules\Campanas\Application\DTOs\RegistrarCampanaInput;
use App\Modules\Campanas\Application\UseCases\RegistrarCampana;
use App\Modules\Campanas\Domain\Exceptions\CodigoCampanaDuplicadoEnProyecto;
use App\Modules\Campanas\Domain\Exceptions\RangoFechasCampanaInvalido;
use App\Modules\Campanas\Domain\ValueObjects\CodigoCampana;
use App\Modules\Campanas\Domain\ValueObjects\EstadoCampana;
use App\Support\Livewire\AutorizaEnProyectoActivo;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Campañas del proyecto.
 *
 * El módulo Campanas tenía dominio, caso de uso, repositorio y eventos desde el
 * principio, pero ni una pantalla ni una ruta: `RegistrarCampana` no lo llamaba
 * nadie. Y como `asignaciones.campana_id` es NOT NULL, sin campañas no se puede
 * asignar NADA — la bandeja de todo gestor queda vacía para siempre y nadie
 * llega a ver sus compromisos. Esta pantalla es el eslabón que faltaba.
 *
 * La ruta pide `campanas.ver`, que el AUDITOR tiene: es el mínimo para entrar a
 * MIRAR, no para escribir. Cada método que escribe exige el suyo aquí dentro,
 * porque el commit de Livewire es un POST a /livewire/update que no vuelve a
 * pasar por el middleware de la ruta.
 */
final class CampanasProyecto extends Component
{
    use AutorizaEnProyectoActivo;

    /** El proyecto lo fija el middleware, no el cliente. */
    #[Locked]
    public int $proyectoId;

    public bool $formVisible = false;

    #[Locked]
    public ?int $editandoId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'codigo' => '',
        'nombre' => '',
        'descripcion' => '',
        'fecha_inicio' => '',
        'fecha_fin' => '',
    ];

    public function mount(): void
    {
        $this->proyectoId = $this->proyectoActivoId();
    }

    public function abrirFormCrear(): void
    {
        $this->autorizarEn('campanas.crear');
        $this->editandoId = null;
        $this->form = [
            'codigo' => '',
            'nombre' => '',
            'descripcion' => '',
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => '',
        ];
        $this->resetErrorBag();
        $this->formVisible = true;
    }

    public function cerrarForm(): void
    {
        $this->formVisible = false;
        $this->editandoId = null;
        $this->resetErrorBag();
    }

    public function guardar(RegistrarCampana $useCase): void
    {
        $this->autorizarEn('campanas.crear');

        $this->validate([
            'form.codigo' => ['required', 'string', 'max:80'],
            'form.nombre' => ['required', 'string', 'max:200'],
            'form.descripcion' => ['nullable', 'string', 'max:1000'],
            'form.fecha_inicio' => ['required', 'date'],
            'form.fecha_fin' => ['nullable', 'date', 'after_or_equal:form.fecha_inicio'],
        ], [], [
            'form.codigo' => 'código',
            'form.nombre' => 'nombre',
            'form.fecha_inicio' => 'fecha de inicio',
            'form.fecha_fin' => 'fecha de fin',
        ]);

        try {
            $useCase->execute(new RegistrarCampanaInput(
                publicId: (string) Str::ulid(),
                // El mismo proyecto contra el que se acaba de autorizar, no el
                // que quedó en la propiedad al montar: autorizar en uno y
                // escribir en otro sería no haber comprobado nada.
                proyectoId: $this->proyectoActivoId(),
                codigo: new CodigoCampana((string) $this->form['codigo']),
                nombre: (string) $this->form['nombre'],
                descripcion: $this->textoOpcional('descripcion'),
                fechaInicio: new DateTimeImmutable((string) $this->form['fecha_inicio']),
                fechaFin: $this->form['fecha_fin'] === ''
                    ? null
                    : new DateTimeImmutable((string) $this->form['fecha_fin']),
                creadaPorId: (int) auth()->id(),
                creadaEn: new DateTimeImmutable,
            ));
        } catch (CodigoCampanaDuplicadoEnProyecto|RangoFechasCampanaInvalido $e) {
            $this->addError('form.codigo', $e->getMessage());

            return;
        } catch (Throwable $e) {
            $this->addError('form.codigo', $e->getMessage());

            return;
        }

        $this->cerrarForm();
        session()->flash('campanas-ok', __('campanas.creada'));
    }

    public function cambiarEstado(int $campanaId, string $estado): void
    {
        // Cambiar el estado de una campaña arranca o para el reparto de trabajo:
        // es gestión, no lectura. `campanas.gestionar` no lo tiene el AUDITOR.
        $this->autorizarEn('campanas.gestionar');

        // Y que la campaña sea de este proyecto: el permiso es por proyecto, así
        // que tenerlo en el propio no autoriza a tocar la campaña del ajeno. El
        // id llega del cliente, no de la fila que se pintó.
        $this->exigirDelProyecto('campanas', $campanaId);

        if (EstadoCampana::tryFrom($estado) === null) {
            return;
        }

        DB::table('campanas')
            ->where('id', $campanaId)
            ->where('proyecto_id', $this->proyectoActivoId())
            ->update(['estado' => $estado]);

        session()->flash('campanas-ok', __('campanas.estado_actualizado'));
    }

    public function render(): View
    {
        $campanas = DB::table('campanas as c')
            ->where('c.proyecto_id', $this->proyectoId)
            ->whereNull('c.eliminada_en')
            // asignaciones no lleva borrado lógico: se cuentan todas.
            ->leftJoin('asignaciones as a', 'a.campana_id', '=', 'c.id')
            ->select([
                'c.id', 'c.codigo', 'c.nombre', 'c.estado',
                'c.fecha_inicio', 'c.fecha_fin',
                DB::raw('count(a.id) as total_asignaciones'),
            ])
            ->groupBy('c.id', 'c.codigo', 'c.nombre', 'c.estado', 'c.fecha_inicio', 'c.fecha_fin')
            ->orderByDesc('c.fecha_inicio')
            ->get();

        // Casos que todavía no están en ninguna campaña: es la cifra que dice
        // cuánto trabajo hay sin repartir.
        $casosSinAsignar = (int) DB::table('casos as k')
            ->where('k.proyecto_id', $this->proyectoId)
            ->whereNull('k.eliminada_en')
            ->whereNotExists(function ($q): void {
                $q->select(DB::raw(1))
                    ->from('asignaciones as a')
                    ->whereColumn('a.caso_id', 'k.id');
            })
            ->count();

        return view('campanas::livewire.campanas-proyecto', [
            'campanas' => $campanas,
            'casosSinAsignar' => $casosSinAsignar,
            'estados' => EstadoCampana::cases(),
        ]);
    }

    private function textoOpcional(string $clave): ?string
    {
        $valor = trim((string) ($this->form[$clave] ?? ''));

        return $valor === '' ? null : $valor;
    }
}
