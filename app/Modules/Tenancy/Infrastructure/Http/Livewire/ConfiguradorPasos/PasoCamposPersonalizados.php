<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Paso 8 (opcional) del wizard F36 — CRUD de campos personalizados scoped al proyecto activo.
 *
 * Diseño: prop :proyecto. NO reusa AdminCamposPersonalizados porque ese
 * Livewire (1) selecciona internamente el primer proyecto del sistema y muestra
 * todos los proyectos en pantalla — no admite scoping vía prop, (2) usa permiso
 * `campos.definir` y no `proyectos.configurar` del wizard.
 *
 * Cubre los mismos 2 ámbitos que el repo soporta hoy: `caso` (× cartera) y
 * `gestion` (× tipo_gestion). El ámbito `compromiso` está pendiente a nivel del
 * proyecto entero (CLAUDE.md §15) y NO entra en F36.
 *
 * Reusa enum TipoCampo del Domain de CamposPersonalizados (sin duplicar).
 * Reglas avanzadas F30 (fecha_minima/maxima, auto_fill, etc.) se omiten — el
 * wizard expone el subset básico; el admin global (`/admin/campos-personalizados`)
 * sigue siendo el lugar para reglas avanzadas.
 */
final class PasoCamposPersonalizados extends Component
{
    public ProyectoModel $proyecto;

    public string $busqueda = '';

    public bool $formVisible = false;

    public ?int $editandoId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(ProyectoModel $proyecto): void
    {
        $this->authorize('proyectos.configurar', (int) $proyecto->id);
        $this->proyecto = $proyecto;
        $this->reiniciarForm();
    }

    public function abrirFormCrear(): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);
        $this->editandoId = null;
        $this->reiniciarForm();
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $row = DB::table('campos_personalizados')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->first();

        if ($row === null) {
            return;
        }

        $reglas = is_string($row->reglas) ? (array) json_decode($row->reglas, true) : [];

        $this->editandoId = $id;
        $this->form = [
            'ambito' => (string) $row->ambito,
            'ambito_id' => (int) $row->ambito_id,
            'codigo' => (string) $row->codigo,
            'etiqueta' => (string) $row->etiqueta,
            'descripcion' => (string) ($row->descripcion ?? ''),
            'tipo' => (string) $row->tipo,
            'obligatorio' => (bool) $row->obligatorio,
            'activo' => (bool) $row->activo,
            'orden' => (int) $row->orden,
            'longitud_max' => isset($reglas['longitud_max']) ? (int) $reglas['longitud_max'] : null,
        ];
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function updatedFormAmbito(string $value): void
    {
        // Reset ambito_id al cambiar de ámbito (su lista cambia).
        $this->form['ambito_id'] = null;
    }

    public function cerrarForm(): void
    {
        $this->formVisible = false;
        $this->editandoId = null;
        $this->reiniciarForm();
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $tiposPermitidos = collect(TipoCampo::cases())
            ->map(fn (TipoCampo $t): string => $t->value)
            ->all();

        $this->validate([
            'form.ambito' => ['required', 'in:caso,gestion'],
            'form.ambito_id' => ['required', 'integer'],
            'form.codigo' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]{2,80}$/'],
            'form.etiqueta' => ['required', 'string', 'max:200'],
            'form.descripcion' => ['nullable', 'string', 'max:500'],
            'form.tipo' => ['required', 'in:'.implode(',', $tiposPermitidos)],
            'form.obligatorio' => ['required', 'boolean'],
            'form.activo' => ['required', 'boolean'],
            'form.orden' => ['required', 'integer', 'min:0'],
            'form.longitud_max' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ], [], [
            'form.ambito' => 'ámbito',
            'form.ambito_id' => 'sub-ámbito',
            'form.codigo' => 'código',
            'form.etiqueta' => 'etiqueta',
            'form.descripcion' => 'descripción',
            'form.tipo' => 'tipo',
            'form.obligatorio' => 'obligatorio',
            'form.activo' => 'estado',
            'form.orden' => 'orden',
            'form.longitud_max' => 'longitud máxima',
        ]);

        if (! $this->validarAmbitoIdPertenece()) {
            return;
        }

        $proyectoId = (int) $this->proyecto->id;
        $ambito = (string) $this->form['ambito'];
        $ambitoId = (int) $this->form['ambito_id'];
        $codigo = strtolower(trim((string) $this->form['codigo']));

        $duplicado = DB::table('campos_personalizados')
            ->where('proyecto_id', $proyectoId)
            ->where('ambito', $ambito)
            ->where('ambito_id', $ambitoId)
            ->where('codigo', $codigo)
            ->when($this->editandoId !== null, fn ($q) => $q->where('id', '!=', $this->editandoId))
            ->exists();

        if ($duplicado) {
            $this->addError('form.codigo', 'Ya existe otro campo con ese código en el mismo ámbito.');

            return;
        }

        $reglas = [];
        $longitudMax = $this->form['longitud_max'] ?? null;
        if ($longitudMax !== null && $longitudMax !== '') {
            $reglas['longitud_max'] = (int) $longitudMax;
        }

        $payload = [
            'ambito' => $ambito,
            'ambito_id' => $ambitoId,
            'codigo' => $codigo,
            'etiqueta' => trim((string) $this->form['etiqueta']),
            'descripcion' => $this->descripcionOpcional(),
            'tipo' => (string) $this->form['tipo'],
            'obligatorio' => (bool) $this->form['obligatorio'],
            'activo' => (bool) $this->form['activo'],
            'orden' => (int) $this->form['orden'],
            'reglas' => $reglas === [] ? null : json_encode($reglas),
            'actualizada_en' => Carbon::now(),
        ];

        if ($this->editandoId === null) {
            $payload['proyecto_id'] = $proyectoId;
            $payload['creada_en'] = Carbon::now();
            DB::table('campos_personalizados')->insert($payload);
        } else {
            DB::table('campos_personalizados')
                ->where('id', $this->editandoId)
                ->where('proyecto_id', $proyectoId)
                ->update($payload);
        }

        $this->cerrarForm();
        session()->flash('paso-campos-personalizados-ok', 'Campo guardado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function eliminar(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $proyectoId = (int) $this->proyecto->id;

        $existe = DB::table('campos_personalizados')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->exists();

        if (! $existe) {
            return;
        }

        $tieneValores = DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $id)
            ->exists();

        if ($tieneValores) {
            session()->flash(
                'paso-campos-personalizados-error',
                'No se puede eliminar: hay valores capturados para este campo.',
            );

            return;
        }

        DB::table('campos_personalizados')
            ->where('id', $id)
            ->where('proyecto_id', $proyectoId)
            ->delete();

        $this->cerrarForm();
        session()->flash('paso-campos-personalizados-ok', 'Campo eliminado.');
        $this->dispatch('configuracion-paso-completado');
    }

    public function toggleActivo(int $id): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        $actual = DB::table('campos_personalizados')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->value('activo');

        if ($actual === null) {
            return;
        }

        DB::table('campos_personalizados')
            ->where('id', $id)
            ->where('proyecto_id', (int) $this->proyecto->id)
            ->update([
                'activo' => ! (bool) $actual,
                'actualizada_en' => Carbon::now(),
            ]);

        $this->dispatch('configuracion-paso-completado');
    }

    public function render(): View
    {
        $proyectoId = (int) $this->proyecto->id;
        $busqueda = trim($this->busqueda);

        $query = DB::table('campos_personalizados as c')
            ->leftJoin('carteras as ca', function ($join): void {
                $join->on('ca.id', '=', 'c.ambito_id')->where('c.ambito', 'caso');
            })
            ->leftJoin('tipos_gestion as tg', function ($join): void {
                $join->on('tg.id', '=', 'c.ambito_id')->where('c.ambito', 'gestion');
            })
            ->where('c.proyecto_id', $proyectoId);

        if ($busqueda !== '') {
            $like = '%'.$busqueda.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('c.codigo', 'like', $like)
                    ->orWhere('c.etiqueta', 'like', $like);
            });
        }

        $campos = $query
            ->orderBy('c.ambito')
            ->orderBy('c.orden')
            ->orderBy('c.codigo')
            ->get([
                'c.id', 'c.ambito', 'c.ambito_id', 'c.codigo', 'c.etiqueta',
                'c.tipo', 'c.obligatorio', 'c.activo', 'c.orden',
                'ca.nombre as cartera_nombre',
                'tg.nombre as tipo_gestion_nombre',
            ]);

        $carteras = DB::table('carteras')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->whereNull('eliminada_en')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $tiposGestion = DB::table('tipos_gestion')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        return view('livewire.tenancy.configurador-pasos.paso-campos-personalizados', [
            'campos' => $campos,
            'carteras' => $carteras,
            'tiposGestion' => $tiposGestion,
            'tiposCampo' => $this->tiposCampoDisponibles(),
        ]);
    }

    /**
     * @return list<array{valor: string, etiqueta: string}>
     */
    private function tiposCampoDisponibles(): array
    {
        return [
            ['valor' => TipoCampo::TEXTO_CORTO->value, 'etiqueta' => 'Texto corto'],
            ['valor' => TipoCampo::TEXTO_LARGO->value, 'etiqueta' => 'Texto largo'],
            ['valor' => TipoCampo::NUMERO_ENTERO->value, 'etiqueta' => 'Número entero'],
            ['valor' => TipoCampo::NUMERO_DECIMAL->value, 'etiqueta' => 'Número decimal'],
            ['valor' => TipoCampo::FECHA->value, 'etiqueta' => 'Fecha'],
            ['valor' => TipoCampo::FECHA_HORA->value, 'etiqueta' => 'Fecha y hora'],
            ['valor' => TipoCampo::BOOLEANO->value, 'etiqueta' => 'Sí / No'],
            ['valor' => TipoCampo::MONEDA->value, 'etiqueta' => 'Moneda'],
        ];
    }

    private function validarAmbitoIdPertenece(): bool
    {
        $proyectoId = (int) $this->proyecto->id;
        $ambito = (string) $this->form['ambito'];
        $ambitoId = (int) $this->form['ambito_id'];

        $tabla = match ($ambito) {
            'caso' => 'carteras',
            'gestion' => 'tipos_gestion',
            default => null,
        };
        if ($tabla === null) {
            $this->addError('form.ambito', 'Ámbito no soportado en el wizard.');

            return false;
        }

        $existe = DB::table($tabla)
            ->where('id', $ambitoId)
            ->where('proyecto_id', $proyectoId)
            ->exists();

        if (! $existe) {
            $this->addError('form.ambito_id', 'El sub-ámbito seleccionado no pertenece al proyecto.');

            return false;
        }

        return true;
    }

    private function reiniciarForm(): void
    {
        $this->form = [
            'ambito' => 'caso',
            'ambito_id' => null,
            'codigo' => '',
            'etiqueta' => '',
            'descripcion' => '',
            'tipo' => 'texto_corto',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 100,
            'longitud_max' => null,
        ];
    }

    private function descripcionOpcional(): ?string
    {
        $valor = trim((string) ($this->form['descripcion'] ?? ''));

        return $valor === '' ? null : $valor;
    }
}
