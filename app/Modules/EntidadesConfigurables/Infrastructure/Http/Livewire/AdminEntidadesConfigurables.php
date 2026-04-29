<?php

declare(strict_types=1);

namespace App\Modules\EntidadesConfigurables\Infrastructure\Http\Livewire;

use App\Modules\EntidadesConfigurables\Application\Services\ServicioEntidades;
use App\Modules\EntidadesConfigurables\Domain\ValueObjects\RelacionEntidad;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Throwable;

/**
 * Administra definiciones de entidades configurables + sus campos (que viven en `campos_personalizados`
 * con ámbito `entidad_configurable`). Permiso exclusivo: `entidades.definir` (ADMIN_GLOBAL).
 *
 * Defensa en profundidad: re-valida el permiso en cada acción vía `autorizar()`, no solo en el
 * middleware HTTP (mismo patrón que F23 para campos).
 */
final class AdminEntidadesConfigurables extends Component
{
    public ?int $proyectoSeleccionadoId = null;

    public bool $formVisible = false;
    public ?int $entidadEditandoId = null;

    public string $formCodigo = '';
    public string $formNombre = '';
    public string $formDescripcion = '';
    public string $formIcono = '';
    public string $formRelacion = 'ninguna';
    public ?int $formCarteraId = null;
    public bool $formActivo = true;

    /** Entidad cuyo panel de campos se está mostrando. */
    public ?int $entidadConCamposAbiertosId = null;

    public bool $formCampoVisible = false;
    public ?int $campoEditandoId = null;
    public string $formCampoCodigo = '';
    public string $formCampoEtiqueta = '';
    public string $formCampoTipo = 'texto_corto';
    public bool $formCampoObligatorio = false;
    public int $formCampoOrden = 100;

    public function mount(): void
    {
        $this->autorizar();
        $primero = DB::table('proyectos')->orderBy('codigo')->value('id');
        $this->proyectoSeleccionadoId = $primero === null ? null : (int) $primero;
    }

    private function autorizar(): void
    {
        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }
        if ($user->esAdminGlobal()) {
            return;
        }
        if (! $user->tienePermiso('entidades.definir')) {
            abort(403, 'No autorizado para definir entidades configurables.');
        }
    }

    // ---------- Entidades ----------

    public function abrirFormCrear(): void
    {
        $this->autorizar();
        $this->entidadEditandoId = null;
        $this->formCodigo = '';
        $this->formNombre = '';
        $this->formDescripcion = '';
        $this->formIcono = '';
        $this->formRelacion = 'ninguna';
        $this->formCarteraId = null;
        $this->formActivo = true;
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $entidadId): void
    {
        $this->autorizar();
        $row = DB::table('entidades_configurables')->where('id', $entidadId)->first();
        if ($row === null) {
            return;
        }
        $this->entidadEditandoId = (int) $row->id;
        $this->proyectoSeleccionadoId = (int) $row->proyecto_id;
        $this->formCodigo = (string) $row->codigo;
        $this->formNombre = (string) $row->nombre;
        $this->formDescripcion = (string) ($row->descripcion ?? '');
        $this->formIcono = (string) ($row->icono ?? '');
        $this->formRelacion = (string) $row->relacion_con;
        $this->formCarteraId = $row->cartera_id === null ? null : (int) $row->cartera_id;
        $this->formActivo = (bool) $row->activo;
        $this->formVisible = true;
    }

    public function cerrarForm(): void
    {
        $this->formVisible = false;
        $this->entidadEditandoId = null;
        $this->resetErrorBag();
    }

    public function guardarEntidad(ServicioEntidades $servicio): void
    {
        $this->autorizar();

        $this->validate([
            'proyectoSeleccionadoId' => ['required', 'integer', 'exists:proyectos,id'],
            'formCodigo'             => ['required', 'string', 'max:80', 'regex:/^[A-Z0-9_]+$/'],
            'formNombre'             => ['required', 'string', 'max:150'],
            'formDescripcion'        => ['nullable', 'string', 'max:500'],
            'formIcono'              => ['nullable', 'string', 'max:50'],
            'formRelacion'           => ['required', 'in:ninguna,caso,persona'],
            'formCarteraId'          => ['nullable', 'integer', 'exists:carteras,id'],
        ]);

        try {
            if ($this->entidadEditandoId === null) {
                $servicio->crearEntidad(
                    proyectoId: (int) $this->proyectoSeleccionadoId,
                    codigo: $this->formCodigo,
                    nombre: $this->formNombre,
                    relacion: RelacionEntidad::from($this->formRelacion),
                    carteraId: $this->formCarteraId,
                    descripcion: $this->formDescripcion !== '' ? $this->formDescripcion : null,
                    icono: $this->formIcono !== '' ? $this->formIcono : null,
                );
            } else {
                $servicio->actualizarEntidad(
                    entidadId: $this->entidadEditandoId,
                    nombre: $this->formNombre,
                    descripcion: $this->formDescripcion !== '' ? $this->formDescripcion : null,
                    icono: $this->formIcono !== '' ? $this->formIcono : null,
                    activo: $this->formActivo,
                );
            }
        } catch (Throwable $e) {
            $this->addError('formCodigo', $e->getMessage());
            return;
        }

        session()->flash('entidades-ok', 'Entidad guardada.');
        $this->cerrarForm();
    }

    public function eliminarEntidad(int $entidadId, ServicioEntidades $servicio): void
    {
        $this->autorizar();
        $servicio->eliminarEntidad($entidadId);
        session()->flash('entidades-ok', 'Entidad desactivada.');
    }

    // ---------- Campos de la entidad ----------

    public function abrirCamposDe(int $entidadId): void
    {
        $this->autorizar();
        $this->entidadConCamposAbiertosId = $entidadId;
        $this->formCampoVisible = false;
        $this->campoEditandoId = null;
    }

    public function cerrarCampos(): void
    {
        $this->entidadConCamposAbiertosId = null;
        $this->formCampoVisible = false;
    }

    public function abrirFormCampoCrear(): void
    {
        $this->autorizar();
        $this->campoEditandoId = null;
        $this->formCampoCodigo = '';
        $this->formCampoEtiqueta = '';
        $this->formCampoTipo = 'texto_corto';
        $this->formCampoObligatorio = false;
        $this->formCampoOrden = 100;
        $this->formCampoVisible = true;
    }

    public function abrirFormCampoEditar(int $campoId): void
    {
        $this->autorizar();
        $row = DB::table('campos_personalizados')->where('id', $campoId)->first();
        if ($row === null) {
            return;
        }
        $this->campoEditandoId = (int) $row->id;
        $this->formCampoCodigo = (string) $row->codigo;
        $this->formCampoEtiqueta = (string) $row->etiqueta;
        $this->formCampoTipo = (string) $row->tipo;
        $this->formCampoObligatorio = (bool) $row->obligatorio;
        $this->formCampoOrden = (int) $row->orden;
        $this->formCampoVisible = true;
    }

    public function cerrarFormCampo(): void
    {
        $this->formCampoVisible = false;
        $this->campoEditandoId = null;
    }

    public function guardarCampo(): void
    {
        $this->autorizar();
        if ($this->entidadConCamposAbiertosId === null) {
            return;
        }

        $this->validate([
            'formCampoCodigo'       => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'formCampoEtiqueta'     => ['required', 'string', 'max:200'],
            'formCampoTipo'         => ['required', 'in:texto_corto,texto_largo,numero_entero,numero_decimal,fecha,fecha_hora,booleano,moneda'],
            'formCampoObligatorio'  => ['boolean'],
            'formCampoOrden'        => ['integer', 'min:0'],
        ]);

        $entidad = DB::table('entidades_configurables')->where('id', $this->entidadConCamposAbiertosId)->first();
        if ($entidad === null) {
            $this->addError('formCampoCodigo', 'Entidad no encontrada.');
            return;
        }

        $payload = [
            'proyecto_id' => (int) $entidad->proyecto_id,
            'ambito'      => 'entidad_configurable',
            'ambito_id'   => (int) $entidad->id,
            'codigo'      => $this->formCampoCodigo,
            'etiqueta'    => $this->formCampoEtiqueta,
            'tipo'        => $this->formCampoTipo,
            'obligatorio' => $this->formCampoObligatorio,
            'activo'      => true,
            'orden'       => $this->formCampoOrden,
        ];

        if ($this->campoEditandoId === null) {
            $duplicado = DB::table('campos_personalizados')
                ->where('proyecto_id', $payload['proyecto_id'])
                ->where('ambito', 'entidad_configurable')
                ->where('ambito_id', $payload['ambito_id'])
                ->where('codigo', $payload['codigo'])
                ->exists();
            if ($duplicado) {
                $this->addError('formCampoCodigo', 'Ya existe un campo con ese código en esta entidad.');
                return;
            }
            DB::table('campos_personalizados')->insert($payload);
        } else {
            DB::table('campos_personalizados')
                ->where('id', $this->campoEditandoId)
                ->update($payload);
        }

        $this->cerrarFormCampo();
        session()->flash('entidades-ok', 'Campo guardado.');
    }

    public function desactivarCampo(int $campoId): void
    {
        $this->autorizar();
        DB::table('campos_personalizados')->where('id', $campoId)->update(['activo' => false]);
    }

    public function activarCampo(int $campoId): void
    {
        $this->autorizar();
        DB::table('campos_personalizados')->where('id', $campoId)->update(['activo' => true]);
    }

    public function render(): View
    {
        $proyectos = DB::table('proyectos')->orderBy('codigo')->get(['id', 'codigo', 'nombre']);

        $entidades = $this->proyectoSeleccionadoId === null
            ? collect()
            : DB::table('entidades_configurables as e')
                ->leftJoin('carteras as ca', 'ca.id', '=', 'e.cartera_id')
                ->where('e.proyecto_id', $this->proyectoSeleccionadoId)
                ->whereNull('e.eliminada_en')
                ->select(['e.*', 'ca.nombre as cartera_nombre'])
                ->orderBy('e.nombre')
                ->get();

        $campos = collect();
        if ($this->entidadConCamposAbiertosId !== null) {
            $campos = DB::table('campos_personalizados')
                ->where('ambito', 'entidad_configurable')
                ->where('ambito_id', $this->entidadConCamposAbiertosId)
                ->orderBy('orden')->orderBy('codigo')
                ->get();
        }

        $carterasDelProyecto = $this->proyectoSeleccionadoId === null
            ? collect()
            : DB::table('carteras')
                ->where('proyecto_id', $this->proyectoSeleccionadoId)
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']);

        return view('entidades::admin.admin-entidades-configurables', [
            'proyectos'           => $proyectos,
            'entidades'           => $entidades,
            'campos'              => $campos,
            'carterasDelProyecto' => $carterasDelProyecto,
        ]);
    }
}
