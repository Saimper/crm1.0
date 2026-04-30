<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Infrastructure\Http\Livewire;

use App\Modules\Reportes\Application\DTOs\EntradaDefinicionReporte;
use App\Modules\Reportes\Application\Hidratacion\HidratadorDefinicionReporte;
use App\Modules\Reportes\Application\Servicios\ServicioCamposPersonalizadosReporte;
use App\Modules\Reportes\Application\UseCases\ActualizarDefinicionReporte;
use App\Modules\Reportes\Application\UseCases\CrearDefinicionReporte;
use App\Modules\Reportes\Application\UseCases\EjecutarReporte;
use App\Modules\Reportes\Domain\Constructor\Catalogo\CatalogoCamposReporte;
use App\Modules\Reportes\Domain\Constructor\Contracts\RepositorioDefinicionReporte;
use App\Modules\Reportes\Domain\Constructor\Enums\EntidadRaiz;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class ConstructorReporte extends Component
{
    public ?int $definicionId = null;

    public string $entidadRaiz = 'casos';

    public string $codigo = '';

    public string $nombre = '';

    public string $descripcion = '';

    /** @var array<int, array{campo: string, etiqueta: string, agregacion: ?string}> */
    public array $columnas = [];

    /** @var array<int, array{campo: string, operador: string, valor: mixed}> */
    public array $filtros = [];

    /** @var list<string> */
    public array $agrupaciones = [];

    /** @var array<int, array{campo: string, direccion: string}> */
    public array $orden = [];

    public string $busquedaCampo = '';

    public ?string $errorGuardar = null;

    /** @var list<array<string,mixed>>|null */
    public ?array $previewFilas = null;

    /** @var list<array{clave:string,etiqueta:string}>|null */
    public ?array $previewCabeceras = null;

    public function mount(?int $definicionId = null): void
    {
        abort_unless(auth()->user()?->tienePermiso('reportes.constructor.gestionar') === true, 403);

        if ($definicionId !== null) {
            $this->cargarExistente($definicionId);
        }
    }

    private function cargarExistente(int $id): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $repo = app(RepositorioDefinicionReporte::class);
        $data = $repo->buscar($id, $proyectoId);
        if ($data === null) {
            abort(404);
        }
        $this->definicionId = $id;
        $this->codigo = $data['codigo'];
        $this->nombre = $data['nombre'];
        $this->descripcion = $data['descripcion'] ?? '';
        $this->entidadRaiz = $data['entidad_raiz'];
        $this->columnas = $data['columnas'];
        $this->filtros = $data['filtros'];
        $this->agrupaciones = $data['agrupaciones'];
        $this->orden = $data['orden'];
    }

    public function updatedEntidadRaiz(): void
    {
        // Cambiar entidad invalida selecciones — limpiar para evitar campos inválidos.
        $this->columnas = [];
        $this->filtros = [];
        $this->agrupaciones = [];
        $this->orden = [];
        $this->previewFilas = null;
        $this->previewCabeceras = null;
    }

    /**
     * @return array<string, array{etiqueta:string, tipo:string}>
     */
    #[Computed]
    public function camposDisponibles(): array
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $entidad = EntidadRaiz::from($this->entidadRaiz);
        $cp = app(ServicioCamposPersonalizadosReporte::class);
        $cat = new CatalogoCamposReporte($entidad, $cp->obtenerCampos($entidad, $proyectoId));

        $out = [];
        foreach ($cat->todos() as $clave => $campo) {
            if ($this->busquedaCampo !== '' &&
                stripos($clave, $this->busquedaCampo) === false &&
                stripos($campo->etiqueta, $this->busquedaCampo) === false) {
                continue;
            }
            $out[$clave] = ['etiqueta' => $campo->etiqueta, 'tipo' => $campo->tipo->value];
        }

        return $out;
    }

    public function agregarColumna(string $clave): void
    {
        $cat = $this->catalogo();
        if (! $cat->tiene($clave)) {
            return;
        }
        foreach ($this->columnas as $c) {
            if ($c['campo'] === $clave) {
                return;
            }
        }
        $campo = $cat->obtener($clave);
        $this->columnas[] = ['campo' => $clave, 'etiqueta' => $campo->etiqueta, 'agregacion' => null];
    }

    public function quitarColumna(int $idx): void
    {
        unset($this->columnas[$idx]);
        $this->columnas = array_values($this->columnas);
    }

    public function setAgregacion(int $idx, string $agregacion): void
    {
        if (! isset($this->columnas[$idx])) {
            return;
        }
        $this->columnas[$idx]['agregacion'] = $agregacion === '' ? null : $agregacion;
    }

    public function agregarFiltro(string $clave): void
    {
        $cat = $this->catalogo();
        if (! $cat->tiene($clave)) {
            return;
        }
        $this->filtros[] = ['campo' => $clave, 'operador' => 'igual', 'valor' => ''];
    }

    public function quitarFiltro(int $idx): void
    {
        unset($this->filtros[$idx]);
        $this->filtros = array_values($this->filtros);
    }

    public function agregarAgrupacion(string $clave): void
    {
        if (in_array($clave, $this->agrupaciones, true)) {
            return;
        }
        $cat = $this->catalogo();
        if (! $cat->tiene($clave)) {
            return;
        }
        $this->agrupaciones[] = $clave;
    }

    public function quitarAgrupacion(int $idx): void
    {
        unset($this->agrupaciones[$idx]);
        $this->agrupaciones = array_values($this->agrupaciones);
    }

    public function agregarOrden(string $clave, string $direccion = 'asc'): void
    {
        $cat = $this->catalogo();
        if (! $cat->tiene($clave)) {
            return;
        }
        $this->orden[] = ['campo' => $clave, 'direccion' => $direccion === 'desc' ? 'desc' : 'asc'];
    }

    public function quitarOrden(int $idx): void
    {
        unset($this->orden[$idx]);
        $this->orden = array_values($this->orden);
    }

    public function preview(): void
    {
        $this->errorGuardar = null;
        try {
            $entrada = $this->construirEntrada();
            $def = HidratadorDefinicionReporte::desdeArray($entrada->paraHidratacion());
            $resultado = app(EjecutarReporte::class)->execute($def, 50);
            $filas = [];
            foreach ($resultado->filas as $fila) {
                $filas[] = $fila;
            }
            $this->previewCabeceras = $resultado->cabeceras;
            $this->previewFilas = $filas;
        } catch (\Throwable $e) {
            $this->errorGuardar = $e->getMessage();
            $this->previewFilas = null;
            $this->previewCabeceras = null;
        }
    }

    public function guardar(): void
    {
        $this->errorGuardar = null;
        try {
            $entrada = $this->construirEntrada();
            if ($this->definicionId === null) {
                $id = app(CrearDefinicionReporte::class)->execute($entrada, (int) auth()->id());
                $this->definicionId = $id;
            } else {
                app(ActualizarDefinicionReporte::class)->execute($this->definicionId, $entrada);
            }
            session()->flash('mensaje', 'Definición guardada.');
            $this->redirectRoute('proyectos.reportes.custom', [
                'proyecto_id' => app('tenancy.proyecto_activo')->id,
            ]);
        } catch (\Throwable $e) {
            $this->errorGuardar = $e->getMessage();
        }
    }

    private function construirEntrada(): EntradaDefinicionReporte
    {
        return new EntradaDefinicionReporte(
            proyectoId: (int) app('tenancy.proyecto_activo')->id,
            codigo: trim($this->codigo),
            nombre: trim($this->nombre),
            entidadRaiz: $this->entidadRaiz,
            columnas: array_values($this->columnas),
            filtros: array_values($this->filtros),
            agrupaciones: array_values($this->agrupaciones),
            orden: array_values($this->orden),
            descripcion: $this->descripcion === '' ? null : $this->descripcion,
        );
    }

    private function catalogo(): CatalogoCamposReporte
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $entidad = EntidadRaiz::from($this->entidadRaiz);
        $cp = app(ServicioCamposPersonalizadosReporte::class);

        return new CatalogoCamposReporte($entidad, $cp->obtenerCampos($entidad, $proyectoId));
    }

    public function render(): View
    {
        return view('reportes::livewire.constructor-reporte');
    }
}
