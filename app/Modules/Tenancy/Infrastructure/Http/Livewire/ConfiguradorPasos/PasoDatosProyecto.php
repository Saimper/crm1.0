<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos;

use App\Modules\Tenancy\Domain\ValueObjects\CodigoProyecto;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Paso 1 del wizard F36 — edita los datos básicos del proyecto.
 *
 * Mutación directa (DB::table) siguiendo el patrón de ediciones operativas F34B.
 * El tipo_operacion es inmutable post-creación (CLAUDE.md §1.2.3); cualquier
 * intento manipulado se descarta silenciosamente con log warning.
 */
final class PasoDatosProyecto extends Component
{
    public ProyectoModel $proyecto;

    public string $nombre = '';

    public string $codigo = '';

    public string $tipoOperacion = '';

    public string $descripcion = '';

    public bool $activo = true;

    public function mount(ProyectoModel $proyecto): void
    {
        $this->autorizar();

        $this->proyecto = $proyecto;
        $this->cargarDesdeProyecto();
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'codigo' => ['required', 'string', 'max:80'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'activo' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre',
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'activo' => 'estado',
        ];
    }

    public function guardar(): void
    {
        $this->persistir(avanzar: true);
    }

    public function guardarSinAvance(): void
    {
        $this->persistir(avanzar: false);
    }

    public function render(): View
    {
        return view('livewire.tenancy.configurador-pasos.paso-datos-proyecto', [
            'tiposOperacion' => TipoOperacion::cases(),
        ]);
    }

    private function persistir(bool $avanzar): void
    {
        $this->autorizar();
        $this->validate();

        try {
            $codigoVO = new CodigoProyecto($this->codigo);
        } catch (InvalidArgumentException $e) {
            $this->addError('codigo', $e->getMessage());

            return;
        }

        $codigoNormalizado = $codigoVO->asString();

        $duplicado = DB::table('proyectos')
            ->where('mandante_id', (int) $this->proyecto->mandante_id)
            ->where('codigo', $codigoNormalizado)
            ->where('id', '!=', (int) $this->proyecto->id)
            ->whereNull('eliminada_en')
            ->exists();

        if ($duplicado) {
            $this->addError('codigo', 'Ya existe otro proyecto con ese código en el mismo mandante.');

            return;
        }

        $tipoActual = (string) $this->proyecto->tipo_operacion;
        if ($this->tipoOperacion !== $tipoActual) {
            Log::warning('Intento de mutar tipo_operacion descartado.', [
                'proyecto_id' => (int) $this->proyecto->id,
                'tipo_actual' => $tipoActual,
                'tipo_intento' => $this->tipoOperacion,
                'usuario_id' => auth()->id(),
            ]);
            $this->tipoOperacion = $tipoActual;
        }

        DB::table('proyectos')
            ->where('id', (int) $this->proyecto->id)
            ->update([
                'nombre' => trim($this->nombre),
                'codigo' => $codigoNormalizado,
                'descripcion' => $this->descripcionOpcional(),
                'activo' => $this->activo,
                // tipo_operacion omitido a propósito: invariante CLAUDE.md §1.2.3.
                'actualizada_en' => Carbon::now(),
            ]);

        $this->proyecto->refresh();
        $this->cargarDesdeProyecto();

        session()->flash('paso-datos-proyecto-ok', 'Datos del proyecto actualizados.');

        if ($avanzar) {
            $this->dispatch('configuracion-paso-completado');
        }
    }

    private function cargarDesdeProyecto(): void
    {
        $this->nombre = (string) $this->proyecto->nombre;
        $this->codigo = (string) $this->proyecto->codigo;
        $this->tipoOperacion = (string) $this->proyecto->tipo_operacion;
        $this->descripcion = (string) ($this->proyecto->descripcion ?? '');
        $this->activo = (bool) $this->proyecto->activo;
    }

    private function descripcionOpcional(): ?string
    {
        $valor = trim($this->descripcion);

        return $valor === '' ? null : $valor;
    }

    /**
     * Defensa en profundidad (patrón F23). Aunque la ruta padre tenga
     * `can:proyectos.configurar`, re-valida en cada acción para bloquear
     * invocaciones directas de Livewire desde contextos no protegidos.
     */
    private function autorizar(): void
    {
        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }
        if ($user->esAdminGlobal()) {
            return;
        }
        if (! $user->tienePermiso('proyectos.configurar', (int) $this->proyecto->id)) {
            abort(403, 'No autorizado para configurar el proyecto.');
        }
    }
}
