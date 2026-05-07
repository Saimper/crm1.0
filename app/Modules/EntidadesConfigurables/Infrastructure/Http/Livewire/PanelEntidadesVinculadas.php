<?php

declare(strict_types=1);

namespace App\Modules\EntidadesConfigurables\Infrastructure\Http\Livewire;

use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use App\Modules\EntidadesConfigurables\Application\Services\ServicioEntidades;
use App\Modules\EntidadesConfigurables\Domain\ValueObjects\RelacionEntidad;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Throwable;

/**
 * Panel embebido en Vista de Trabajo: muestra entidades configurables
 * con relacion_con coincidente (caso/persona) y permite CRUD inline
 * de sus registros vinculados al caso/persona activos.
 *
 * Reutiliza ServicioEntidades para query/mutaciones (no duplica lógica).
 *
 * Permisos: entidades.ver / .crear / .editar / .eliminar (sin permisos nuevos).
 */
final class PanelEntidadesVinculadas extends Component
{
    public int $proyectoId = 0;

    /** @var 'caso'|'persona' */
    public string $vinculo = 'caso';

    public int $vinculoId = 0;

    public ?int $carteraId = null;

    public ?int $entidadActivaId = null;

    public bool $formVisible = false;

    public ?int $registroEditandoId = null;

    public string $titulo = '';

    /** @var array<string, mixed> */
    public array $valores = [];

    public function mount(int $proyectoId, string $vinculo, int $vinculoId, ?int $carteraId = null): void
    {
        abort_unless($vinculo === 'caso' || $vinculo === 'persona', 422);
        abort_unless(auth()->user()?->tienePermiso('entidades.ver', $proyectoId) === true, 403);

        $this->proyectoId = $proyectoId;
        $this->vinculo = $vinculo;
        $this->vinculoId = $vinculoId;
        $this->carteraId = $carteraId;
    }

    // ----- Form -----

    public function abrirFormCrear(int $entidadId): void
    {
        abort_unless(auth()->user()?->tienePermiso('entidades.crear', $this->proyectoId) === true, 403);

        $this->entidadActivaId = $entidadId;
        $this->registroEditandoId = null;
        $this->titulo = '';
        $this->valores = [];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $entidadId, int $registroId): void
    {
        abort_unless(auth()->user()?->tienePermiso('entidades.editar', $this->proyectoId) === true, 403);

        $row = DB::table('entidades_registros')
            ->where('proyecto_id', $this->proyectoId)
            ->where('entidad_configurable_id', $entidadId)
            ->where('id', $registroId)
            ->whereNull('eliminado_en')
            ->first();
        if ($row === null) {
            return;
        }

        $this->entidadActivaId = $entidadId;
        $this->registroEditandoId = $registroId;
        $this->titulo = (string) ($row->titulo ?? '');
        $this->valores = $this->cargarValores($entidadId, $registroId);
        $this->formVisible = true;
    }

    public function cerrarForm(): void
    {
        $this->formVisible = false;
        $this->entidadActivaId = null;
        $this->registroEditandoId = null;
        $this->resetErrorBag();
    }

    public function guardar(ServicioEntidades $servicio): void
    {
        $this->validate([
            'titulo' => ['required', 'string', 'max:255'],
        ]);

        if ($this->entidadActivaId === null) {
            return;
        }

        try {
            if ($this->registroEditandoId === null) {
                abort_unless(auth()->user()?->tienePermiso('entidades.crear', $this->proyectoId) === true, 403);
                $servicio->crearRegistro(
                    proyectoId: $this->proyectoId,
                    entidadId: $this->entidadActivaId,
                    titulo: $this->titulo,
                    valoresPorCodigo: $this->valores,
                    casoId: $this->vinculo === 'caso' ? $this->vinculoId : null,
                    personaId: $this->vinculo === 'persona' ? $this->vinculoId : null,
                    usuarioId: (int) auth()->id(),
                );
            } else {
                abort_unless(auth()->user()?->tienePermiso('entidades.editar', $this->proyectoId) === true, 403);
                $servicio->actualizarRegistro(
                    proyectoId: $this->proyectoId,
                    entidadId: $this->entidadActivaId,
                    registroId: $this->registroEditandoId,
                    titulo: $this->titulo,
                    valoresPorCodigo: $this->valores,
                );
            }
        } catch (Throwable $e) {
            $this->addError('titulo', $e->getMessage());

            return;
        }

        $this->cerrarForm();
        session()->flash('entidades-registros-ok', 'Registro guardado.');
    }

    public function eliminar(int $registroId, ServicioEntidades $servicio): void
    {
        abort_unless(auth()->user()?->tienePermiso('entidades.eliminar', $this->proyectoId) === true, 403);
        $servicio->eliminarRegistro($this->proyectoId, $registroId);
        session()->flash('entidades-registros-ok', 'Registro eliminado.');
    }

    // ----- Data -----

    /**
     * @return array<int, array{
     *     entidad: object,
     *     campos: Collection<int, mixed>,
     *     registros: Collection<int, mixed>
     * }>
     */
    private function cargarBloques(): array
    {
        $entidades = app(ServicioEntidades::class)
            ->entidadesDelProyecto($this->proyectoId, $this->carteraId)
            ->filter(fn ($e) => (string) $e->relacion_con === $this->vinculo)
            ->values();

        $bloques = [];
        $servicioCampos = app(ServicioCamposPersonalizados::class);
        foreach ($entidades as $entidad) {
            $registros = app(ServicioEntidades::class)->registros(
                proyectoId: $this->proyectoId,
                entidadId: (int) $entidad->id,
                casoId: $this->vinculo === 'caso' ? $this->vinculoId : null,
                personaId: $this->vinculo === 'persona' ? $this->vinculoId : null,
            );
            $campos = $servicioCampos->campos(
                proyectoId: $this->proyectoId,
                ambito: AmbitoCampo::ENTIDAD_CONFIGURABLE,
                ambitoId: (int) $entidad->id,
            );

            $bloques[] = [
                'entidad' => $entidad,
                'campos' => $campos,
                'registros' => $registros,
            ];
        }

        return $bloques;
    }

    /** @return array<string, mixed> */
    private function cargarValores(int $entidadId, int $registroId): array
    {
        $filas = DB::table('valores_campo_personalizado as v')
            ->join('campos_personalizados as c', 'c.id', '=', 'v.campo_personalizado_id')
            ->where('v.entidad_id', $registroId)
            ->where('c.ambito', 'entidad_configurable')
            ->where('c.ambito_id', $entidadId)
            ->select(['c.codigo', 'c.tipo', 'v.*'])
            ->get();

        $valores = [];
        foreach ($filas as $f) {
            $valores[(string) $f->codigo] = match ((string) $f->tipo) {
                'texto_corto' => $f->valor_texto_corto,
                'texto_largo' => $f->valor_texto_largo,
                'numero_entero' => $f->valor_numero_entero === null ? null : (int) $f->valor_numero_entero,
                'numero_decimal' => $f->valor_numero_decimal,
                'fecha' => $f->valor_fecha,
                'fecha_hora' => $f->valor_fecha_hora,
                'booleano' => $f->valor_booleano === null ? null : (bool) $f->valor_booleano,
                'seleccion_unica' => $f->valor_opcion_id === null ? null : (int) $f->valor_opcion_id,
                'seleccion_multiple' => $this->decodificarOpcionesMultiples($f->valor_opciones_ids),
                'moneda' => $f->valor_moneda_monto === null ? null : [
                    'monto' => $f->valor_moneda_monto,
                    'moneda' => $f->valor_moneda_codigo,
                ],
                default => null,
            };
        }

        return $valores;
    }

    /**
     * @return array<int, int>|null
     */
    private function decodificarOpcionesMultiples(mixed $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        if (is_array($raw)) {
            return array_map('intval', $raw);
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? array_map('intval', $decoded) : null;
    }

    public function render(): View
    {
        $bloques = $this->cargarBloques();
        $relacionEnum = RelacionEntidad::from($this->vinculo);
        $camposForm = collect();
        if ($this->formVisible && $this->entidadActivaId !== null) {
            $camposForm = app(ServicioCamposPersonalizados::class)->campos(
                proyectoId: $this->proyectoId,
                ambito: AmbitoCampo::ENTIDAD_CONFIGURABLE,
                ambitoId: $this->entidadActivaId,
            );
        }

        return view('entidades::livewire.panel-entidades-vinculadas', [
            'bloques' => $bloques,
            'relacion' => $relacionEnum,
            'camposForm' => $camposForm,
        ]);
    }
}
