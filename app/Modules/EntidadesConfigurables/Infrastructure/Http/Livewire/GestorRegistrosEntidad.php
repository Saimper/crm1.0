<?php

declare(strict_types=1);

namespace App\Modules\EntidadesConfigurables\Infrastructure\Http\Livewire;

use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use App\Modules\EntidadesConfigurables\Application\Services\ServicioEntidades;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Throwable;

/**
 * Listado y CRUD de registros de una entidad configurable.
 * Form dinámico: se genera a partir de los campos definidos para la entidad.
 *
 * Permisos:
 *   - entidades.ver      → listar
 *   - entidades.crear    → crear
 *   - entidades.editar   → editar
 *   - entidades.eliminar → eliminar
 *
 * Filtros opcionales: `casoId`, `personaId` si la entidad tiene relación.
 */
final class GestorRegistrosEntidad extends Component
{
    public int $proyectoId = 0;
    public int $entidadId = 0;
    public ?int $casoId = null;
    public ?int $personaId = null;

    public bool $formVisible = false;
    public ?int $registroEditandoId = null;

    public string $titulo = '';

    /** @var array<string, mixed> codigo_campo => valor */
    public array $valores = [];

    public function mount(int $proyectoId, int $entidadId, ?int $casoId = null, ?int $personaId = null): void
    {
        $this->autorizarVer();
        $this->proyectoId = $proyectoId;
        $this->entidadId = $entidadId;
        $this->casoId = $casoId;
        $this->personaId = $personaId;
    }

    // ----- Autorizaciones (defensa en profundidad por acción) -----

    private function autorizarVer(): void
    {
        abort_unless(auth()->user()?->tienePermiso('entidades.ver', $this->proyectoId ?: null) === true, 403);
    }

    private function autorizarCrear(): void
    {
        abort_unless(auth()->user()?->tienePermiso('entidades.crear', $this->proyectoId) === true, 403);
    }

    private function autorizarEditar(): void
    {
        abort_unless(auth()->user()?->tienePermiso('entidades.editar', $this->proyectoId) === true, 403);
    }

    private function autorizarEliminar(): void
    {
        abort_unless(auth()->user()?->tienePermiso('entidades.eliminar', $this->proyectoId) === true, 403);
    }

    // ----- Form -----

    public function abrirFormCrear(): void
    {
        $this->autorizarCrear();
        $this->registroEditandoId = null;
        $this->titulo = '';
        $this->valores = [];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $registroId): void
    {
        $this->autorizarEditar();

        $row = DB::table('entidades_registros')
            ->where('proyecto_id', $this->proyectoId)
            ->where('id', $registroId)
            ->whereNull('eliminado_en')
            ->first();
        if ($row === null) {
            return;
        }

        $this->registroEditandoId = (int) $row->id;
        $this->titulo = (string) ($row->titulo ?? '');
        $this->valores = $this->cargarValores($registroId);
        $this->formVisible = true;
    }

    public function cerrarForm(): void
    {
        $this->formVisible = false;
        $this->registroEditandoId = null;
        $this->resetErrorBag();
    }

    public function guardar(ServicioEntidades $servicio): void
    {
        $this->validate([
            'titulo' => ['required', 'string', 'max:255'],
        ]);

        try {
            if ($this->registroEditandoId === null) {
                $this->autorizarCrear();
                $servicio->crearRegistro(
                    proyectoId: $this->proyectoId,
                    entidadId: $this->entidadId,
                    titulo: $this->titulo,
                    valoresPorCodigo: $this->valores,
                    casoId: $this->casoId,
                    personaId: $this->personaId,
                    usuarioId: (int) auth()->id(),
                );
            } else {
                $this->autorizarEditar();
                $servicio->actualizarRegistro(
                    proyectoId: $this->proyectoId,
                    entidadId: $this->entidadId,
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
        $this->autorizarEliminar();
        $servicio->eliminarRegistro($this->proyectoId, $registroId);
        session()->flash('entidades-registros-ok', 'Registro eliminado.');
    }

    // ----- Data -----

    /** @return array<string, mixed> */
    private function cargarValores(int $registroId): array
    {
        $filas = DB::table('valores_campo_personalizado as v')
            ->join('campos_personalizados as c', 'c.id', '=', 'v.campo_personalizado_id')
            ->where('v.entidad_id', $registroId)
            ->where('c.ambito', 'entidad_configurable')
            ->where('c.ambito_id', $this->entidadId)
            ->select(['c.codigo', 'c.tipo', 'v.*'])
            ->get();

        $valores = [];
        foreach ($filas as $f) {
            $valores[(string) $f->codigo] = match ((string) $f->tipo) {
                'texto_corto'    => $f->valor_texto_corto,
                'texto_largo'    => $f->valor_texto_largo,
                'numero_entero'  => $f->valor_numero_entero === null ? null : (int) $f->valor_numero_entero,
                'numero_decimal' => $f->valor_numero_decimal,
                'fecha'          => $f->valor_fecha,
                'fecha_hora'     => $f->valor_fecha_hora,
                'booleano'       => $f->valor_booleano === null ? null : (bool) $f->valor_booleano,
                'moneda'         => $f->valor_moneda_monto,
                default          => null,
            };
        }
        return $valores;
    }

    public function render(): View
    {
        $entidad = DB::table('entidades_configurables')
            ->where('id', $this->entidadId)
            ->where('proyecto_id', $this->proyectoId)
            ->first();

        $campos = collect();
        if ($entidad !== null) {
            $campos = app(ServicioCamposPersonalizados::class)->campos(
                proyectoId: $this->proyectoId,
                ambito: AmbitoCampo::ENTIDAD_CONFIGURABLE,
                ambitoId: $this->entidadId,
            );
        }

        $registros = $entidad === null
            ? collect()
            : app(ServicioEntidades::class)->registros(
                proyectoId: $this->proyectoId,
                entidadId: $this->entidadId,
                casoId: $this->casoId,
                personaId: $this->personaId,
            );

        return view('entidades::operativo.gestor-registros-entidad', [
            'entidad'    => $entidad,
            'campos'     => $campos,
            'registros'  => $registros,
        ]);
    }
}
