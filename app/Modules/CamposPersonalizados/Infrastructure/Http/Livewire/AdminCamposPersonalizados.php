<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * CRUD administrativo de campos personalizados. Solo ADMIN_GLOBAL (protegido por middleware de ruta).
 * Cubre ámbitos `caso` (× cartera) y `gestion` (× tipo_gestion). `compromiso` pendiente (enum de tipos).
 */
final class AdminCamposPersonalizados extends Component
{
    public ?int $proyectoSeleccionadoId = null;

    public bool $formVisible = false;

    public ?int $campoEditandoId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->autorizar();

        $primero = DB::table('proyectos')->orderBy('codigo')->value('id');
        $this->proyectoSeleccionadoId = $primero === null ? null : (int) $primero;
        $this->reiniciarForm();
    }

    /**
     * Defensa en profundidad: `campos.definir` es exclusivo de ADMIN_GLOBAL.
     * Aunque la ruta tenga `admin.global`, re-valida en cada acción para bloquear
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
        if (! $user->tienePermiso('campos.definir')) {
            abort(403, 'No autorizado para definir campos personalizados.');
        }
    }

    public function abrirFormCrear(): void
    {
        $this->autorizar();
        $this->campoEditandoId = null;
        $this->reiniciarForm();
        $this->formVisible = true;
    }

    public function abrirFormEditar(int $campoId): void
    {
        $this->autorizar();
        $row = DB::table('campos_personalizados')->where('id', $campoId)->first();
        if ($row === null) {
            return;
        }

        $reglas = is_string($row->reglas) ? (array) json_decode($row->reglas, true) : [];
        $this->form = [
            'proyecto_id' => (int) $row->proyecto_id,
            'ambito' => (string) $row->ambito,
            'ambito_id' => (int) $row->ambito_id,
            'codigo' => (string) $row->codigo,
            'etiqueta' => (string) $row->etiqueta,
            'tipo' => (string) $row->tipo,
            'obligatorio' => (bool) $row->obligatorio,
            'activo' => (bool) $row->activo,
            'orden' => (int) $row->orden,
            'longitud_max' => isset($reglas['longitud_max']) ? (int) $reglas['longitud_max'] : null,
        ];
        $this->proyectoSeleccionadoId = (int) $row->proyecto_id;
        $this->campoEditandoId = $campoId;
        $this->formVisible = true;
    }

    public function updatedFormProyectoId(mixed $value): void
    {
        $this->proyectoSeleccionadoId = $value === null || $value === '' ? null : (int) $value;
        $this->form['ambito_id'] = null;
    }

    public function cerrarForm(): void
    {
        $this->formVisible = false;
        $this->campoEditandoId = null;
        $this->reiniciarForm();
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $this->autorizar();

        $this->validate([
            'form.proyecto_id' => ['required', 'integer', 'exists:proyectos,id'],
            'form.ambito' => ['required', 'in:caso,gestion'],
            'form.ambito_id' => ['required', 'integer'],
            'form.codigo' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'form.etiqueta' => ['required', 'string', 'max:200'],
            'form.tipo' => ['required', 'in:texto_corto,texto_largo,numero_entero,numero_decimal,fecha,fecha_hora,booleano,moneda'],
            'form.obligatorio' => ['boolean'],
            'form.activo' => ['boolean'],
            'form.orden' => ['integer', 'min:0'],
            'form.longitud_max' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ], [], [
            'form.proyecto_id' => 'proyecto',
            'form.ambito' => 'ámbito',
            'form.ambito_id' => 'ámbito_id',
            'form.codigo' => 'código',
            'form.etiqueta' => 'etiqueta',
            'form.tipo' => 'tipo',
            'form.longitud_max' => 'longitud máxima',
        ]);

        if (! $this->validarAmbitoId()) {
            return;
        }

        $reglas = [];
        if (! empty($this->form['longitud_max'])) {
            $reglas['longitud_max'] = (int) $this->form['longitud_max'];
        }

        $payload = [
            'proyecto_id' => (int) $this->form['proyecto_id'],
            'ambito' => (string) $this->form['ambito'],
            'ambito_id' => (int) $this->form['ambito_id'],
            'codigo' => (string) $this->form['codigo'],
            'etiqueta' => (string) $this->form['etiqueta'],
            'tipo' => (string) $this->form['tipo'],
            'obligatorio' => (bool) $this->form['obligatorio'],
            'activo' => (bool) ($this->form['activo'] ?? true),
            'orden' => (int) ($this->form['orden'] ?? 100),
            'reglas' => $reglas === [] ? null : json_encode($reglas),
        ];

        if ($this->campoEditandoId === null) {
            $duplicado = DB::table('campos_personalizados')
                ->where('proyecto_id', $payload['proyecto_id'])
                ->where('ambito', $payload['ambito'])
                ->where('ambito_id', $payload['ambito_id'])
                ->where('codigo', $payload['codigo'])
                ->exists();
            if ($duplicado) {
                $this->addError('form.codigo', 'Ya existe un campo con ese código en el ámbito indicado.');

                return;
            }
            DB::table('campos_personalizados')->insert($payload);
        } else {
            DB::table('campos_personalizados')->where('id', $this->campoEditandoId)->update($payload);
        }

        $this->cerrarForm();
        session()->flash('admin-campos-ok', 'Campo guardado.');
    }

    public function desactivar(int $campoId): void
    {
        $this->autorizar();
        DB::table('campos_personalizados')->where('id', $campoId)->update(['activo' => false]);
        session()->flash('admin-campos-ok', 'Campo desactivado.');
    }

    public function activar(int $campoId): void
    {
        $this->autorizar();
        DB::table('campos_personalizados')->where('id', $campoId)->update(['activo' => true]);
        session()->flash('admin-campos-ok', 'Campo activado.');
    }

    public function render(): View
    {
        $proyectos = DB::table('proyectos')
            ->select(['id', 'codigo', 'nombre', 'tipo_operacion'])
            ->orderBy('codigo')
            ->get();

        $camposTodos = DB::table('campos_personalizados as c')
            ->leftJoin('carteras as ca', function ($join): void {
                $join->on('ca.id', '=', 'c.ambito_id')->where('c.ambito', 'caso');
            })
            ->leftJoin('tipos_gestion as tg', function ($join): void {
                $join->on('tg.id', '=', 'c.ambito_id')->where('c.ambito', 'gestion');
            })
            ->select([
                'c.id', 'c.proyecto_id', 'c.ambito', 'c.ambito_id', 'c.codigo', 'c.etiqueta',
                'c.tipo', 'c.obligatorio', 'c.activo', 'c.orden',
                'ca.nombre as cartera_nombre',
                'tg.nombre as tipo_gestion_nombre',
            ])
            ->orderBy('c.ambito')
            ->orderBy('c.orden')
            ->get()
            ->groupBy('proyecto_id');

        $carteras = collect();
        $tiposGestion = collect();
        if ($this->proyectoSeleccionadoId !== null) {
            $carteras = DB::table('carteras')
                ->where('proyecto_id', $this->proyectoSeleccionadoId)
                ->where('activo', true)
                ->whereNull('eliminada_en')
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']);

            $tiposGestion = DB::table('tipos_gestion')
                ->where('proyecto_id', $this->proyectoSeleccionadoId)
                ->where('activo', true)
                ->orderBy('orden')
                ->get(['id', 'codigo', 'nombre']);
        }

        return view('campos_personalizados::admin.lista', [
            'proyectos' => $proyectos,
            'camposPorProyecto' => $camposTodos,
            'carteras' => $carteras,
            'tiposGestion' => $tiposGestion,
            'tiposCampo' => $this->tiposCampoDisponibles(),
        ]);
    }

    /** @return Collection<int, array{valor:string, etiqueta:string}> */
    private function tiposCampoDisponibles(): Collection
    {
        return collect([
            ['valor' => TipoCampo::TEXTO_CORTO->value,    'etiqueta' => 'Texto corto'],
            ['valor' => TipoCampo::TEXTO_LARGO->value,    'etiqueta' => 'Texto largo'],
            ['valor' => TipoCampo::NUMERO_ENTERO->value,  'etiqueta' => 'Número entero'],
            ['valor' => TipoCampo::NUMERO_DECIMAL->value, 'etiqueta' => 'Número decimal'],
            ['valor' => TipoCampo::FECHA->value,          'etiqueta' => 'Fecha'],
            ['valor' => TipoCampo::FECHA_HORA->value,     'etiqueta' => 'Fecha y hora'],
            ['valor' => TipoCampo::BOOLEANO->value,       'etiqueta' => 'Sí / No'],
            ['valor' => TipoCampo::MONEDA->value,         'etiqueta' => 'Moneda (monto)'],
        ]);
    }

    private function validarAmbitoId(): bool
    {
        $proyectoId = (int) $this->form['proyecto_id'];
        $ambito = (string) $this->form['ambito'];
        $ambitoId = (int) $this->form['ambito_id'];

        $tabla = match ($ambito) {
            'caso' => 'carteras',
            'gestion' => 'tipos_gestion',
            default => null,
        };
        if ($tabla === null) {
            $this->addError('form.ambito', 'Ámbito inválido.');

            return false;
        }

        $existe = DB::table($tabla)
            ->where('id', $ambitoId)
            ->where('proyecto_id', $proyectoId)
            ->exists();
        if (! $existe) {
            $this->addError('form.ambito_id', 'El ámbito seleccionado no pertenece al proyecto elegido.');

            return false;
        }

        return true;
    }

    private function reiniciarForm(): void
    {
        $this->form = [
            'proyecto_id' => $this->proyectoSeleccionadoId,
            'ambito' => 'caso',
            'ambito_id' => null,
            'codigo' => '',
            'etiqueta' => '',
            'tipo' => 'texto_corto',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 100,
            'longitud_max' => null,
        ];
    }
}
