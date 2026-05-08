<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\AvanceConfiguracion;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\CalculadorAvanceConfiguracion;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Paso 9 del wizard F36 — pantalla de resumen + acción final.
 *
 * Toda la información se calcula en mount() para evitar queries durante el render.
 * No persiste un timestamp de "configuración completada" porque la columna
 * `proyectos.configuracion_completada_en` no existe en schema; el estado se
 * detecta en runtime via CalculadorAvanceConfiguracion.
 */
final class PasoResumen extends Component
{
    public ProyectoModel $proyecto;

    /**
     * Modo del padre: 'wizard' (acción finalizar visible) o 'edicion'
     * (acción finalizar oculta). Se hereda del configurador padre vía prop.
     */
    public string $modo = 'wizard';

    /** @var array<string, int> conteos por código de paso/catálogo. */
    public array $conteos = [];

    /** @var list<string> códigos de pasos obligatorios pendientes. */
    public array $pasosPendientes = [];

    public bool $estaCompleto = false;

    /** @var list<array{codigo: string, etiqueta: string, conteo: int}> */
    public array $catalogosTipo = [];

    public function mount(ProyectoModel $proyecto, CalculadorAvanceConfiguracion $calculador, ?string $modo = null): void
    {
        $this->authorize('proyectos.configurar', (int) $proyecto->id);
        $this->proyecto = $proyecto;
        if ($modo !== null && in_array($modo, ['wizard', 'edicion'], true)) {
            $this->modo = $modo;
        }

        $proyectoId = (int) $proyecto->id;

        $this->conteos = [
            'datos_proyecto' => 1, // el proyecto siempre existe en este punto
            'carteras' => $this->contar('carteras', $proyectoId, true),
            'estados_caso' => $this->contar('estados_caso', $proyectoId),
            'tipos_gestion' => $this->contar('tipos_gestion', $proyectoId),
            'resultados' => $this->contar('resultados', $proyectoId),
            'motivos_no_contacto' => $this->contar('motivos_no_contacto', $proyectoId),
            'campos_personalizados' => (int) DB::table('campos_personalizados')
                ->where('proyecto_id', $proyectoId)
                ->count(),
        ];

        $tipo = TipoOperacion::tryFrom((string) $proyecto->tipo_operacion);
        if ($tipo !== null) {
            foreach (PasoConfiguracion::subPasosCatalogosPorTipo($tipo) as $tabla) {
                $this->catalogosTipo[] = [
                    'codigo' => $tabla,
                    'etiqueta' => self::etiquetaCatalogoTipo($tabla),
                    'conteo' => (int) DB::table($tabla)->where('proyecto_id', $proyectoId)->count(),
                ];
            }
        }
        $this->conteos['catalogos_tipo'] = array_sum(array_column($this->catalogosTipo, 'conteo'));

        $avance = $calculador->calcular($proyectoId);
        $this->estaCompleto = $avance->estaCompleto();
        $this->pasosPendientes = $this->calcularPendientes($avance);
    }

    public function finalizar(): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        // En modo edición la acción no aplica — el proyecto ya estaba
        // configurado. La UI oculta el botón; este guard cubre invocaciones
        // manipuladas (defensa F23).
        if ($this->modo !== 'wizard') {
            return;
        }

        /** @var CalculadorAvanceConfiguracion $calculador */
        $calculador = app(CalculadorAvanceConfiguracion::class);
        $avance = $calculador->calcular((int) $this->proyecto->id);

        if (! $avance->estaCompleto()) {
            session()->flash(
                'paso-resumen-error',
                'Faltan pasos obligatorios por completar antes de cerrar el configurador.',
            );

            return;
        }

        // La columna `proyectos.configuracion_completada_en` NO existe en schema
        // (verificado en migraciones). Se omite el UPDATE específico de esa
        // columna; la "completitud" se sigue detectando en runtime via
        // CalculadorAvanceConfiguracion. Toco únicamente actualizada_en.
        DB::table('proyectos')
            ->where('id', (int) $this->proyecto->id)
            ->update(['actualizada_en' => now()]);

        // No se dispara evento de dominio: `ProyectoConfigurado` no existe en
        // app/Modules/Tenancy/Domain/Events. Queda como TODO si se requiere
        // notificación posterior.

        session()->flash('paso-resumen-ok', 'Proyecto configurado.');

        $this->redirectRoute('proyectos.bandeja', [
            'proyecto_id' => (int) $this->proyecto->id,
        ], navigate: true);
    }

    public function volverAlInicio(): void
    {
        $this->dispatch('configuracion-ir-a-paso', paso: PasoConfiguracion::DATOS_PROYECTO->value);
    }

    public function render(): View
    {
        return view('livewire.tenancy.configurador-pasos.paso-resumen', [
            'pasos' => PasoConfiguracion::cases(),
            'etiquetasPasos' => $this->etiquetasPasos(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function etiquetasPasos(): array
    {
        $mapa = [];
        foreach (PasoConfiguracion::cases() as $paso) {
            $mapa[$paso->value] = $paso->etiqueta();
        }

        return $mapa;
    }

    private function contar(string $tabla, int $proyectoId, bool $excluirEliminados = false): int
    {
        $query = DB::table($tabla)->where('proyecto_id', $proyectoId);
        if ($excluirEliminados) {
            $query->whereNull('eliminada_en');
        }

        return (int) $query->count();
    }

    /**
     * @return list<string>
     */
    private function calcularPendientes(AvanceConfiguracion $avance): array
    {
        $pendientes = [];
        foreach (PasoConfiguracion::cases() as $paso) {
            if (! $paso->esObligatorio()) {
                continue;
            }
            if (! $avance->estaCompletado($paso)) {
                $pendientes[] = $paso->etiqueta();
            }
        }

        return $pendientes;
    }

    private static function etiquetaCatalogoTipo(string $tabla): string
    {
        return match ($tabla) {
            'tramos_mora' => 'Tramos de mora',
            'tipos_pago' => 'Tipos de pago',
            'categorias_ticket' => 'Categorías de ticket',
            'prioridades_ticket' => 'Prioridades de ticket',
            'niveles_sla' => 'Niveles de SLA',
            'niveles_escalamiento' => 'Niveles de escalamiento',
            'productos_venta' => 'Productos',
            'etapas_embudo' => 'Etapas del embudo',
            'tipos_accion_servicio' => 'Tipos de acción de servicio',
            'estados_tecnicos' => 'Estados técnicos',
            default => $tabla,
        };
    }
}
