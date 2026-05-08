<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Paso 7 del wizard F36 — contenedor con sub-tabs por catálogo tipo-específico.
 * Los sub-tabs aplicables provienen de PasoConfiguracion::subPasosCatalogosPorTipo()
 * según el tipo_operacion del proyecto (whitelist server-side).
 */
final class PasoCatalogosTipo extends Component
{
    public ProyectoModel $proyecto;

    public string $tabActiva = '';

    /**
     * Mapa código de catálogo → metadata de UI (etiqueta + alias Livewire).
     *
     * @var array<string, array{etiqueta: string, alias: string}>
     */
    private const CATALOGOS = [
        'tramos_mora' => [
            'etiqueta' => 'Tramos de mora',
            'alias' => 'tenancy.configurador-pasos.catalogos-tipo.catalogo-tramos-mora',
        ],
        'tipos_pago' => [
            'etiqueta' => 'Tipos de pago',
            'alias' => 'tenancy.configurador-pasos.catalogos-tipo.catalogo-tipos-pago',
        ],
        'categorias_ticket' => [
            'etiqueta' => 'Categorías de ticket',
            'alias' => 'tenancy.configurador-pasos.catalogos-tipo.catalogo-categorias-ticket',
        ],
        'prioridades_ticket' => [
            'etiqueta' => 'Prioridades de ticket',
            'alias' => 'tenancy.configurador-pasos.catalogos-tipo.catalogo-prioridades-ticket',
        ],
        'niveles_sla' => [
            'etiqueta' => 'Niveles de SLA',
            'alias' => 'tenancy.configurador-pasos.catalogos-tipo.catalogo-niveles-sla',
        ],
        'niveles_escalamiento' => [
            'etiqueta' => 'Niveles de escalamiento',
            'alias' => 'tenancy.configurador-pasos.catalogos-tipo.catalogo-niveles-escalamiento',
        ],
        'productos_venta' => [
            'etiqueta' => 'Productos',
            'alias' => 'tenancy.configurador-pasos.catalogos-tipo.catalogo-productos-venta',
        ],
        'etapas_embudo' => [
            'etiqueta' => 'Etapas del embudo',
            'alias' => 'tenancy.configurador-pasos.catalogos-tipo.catalogo-etapas-embudo',
        ],
        'tipos_accion_servicio' => [
            'etiqueta' => 'Tipos de acción de servicio',
            'alias' => 'tenancy.configurador-pasos.catalogos-tipo.catalogo-tipos-accion-servicio',
        ],
        'estados_tecnicos' => [
            'etiqueta' => 'Estados técnicos',
            'alias' => 'tenancy.configurador-pasos.catalogos-tipo.catalogo-estados-tecnicos',
        ],
    ];

    public function mount(ProyectoModel $proyecto): void
    {
        $this->authorize('proyectos.configurar', (int) $proyecto->id);
        $this->proyecto = $proyecto;

        $aplicables = $this->subTabsAplicables();
        $this->tabActiva = $this->primerPendiente($aplicables) ?? ($aplicables[0] ?? '');
    }

    public function cambiarTab(string $codigo): void
    {
        $this->authorize('proyectos.configurar', (int) $this->proyecto->id);

        if (! in_array($codigo, $this->subTabsAplicables(), true)) {
            throw new InvalidArgumentException("Sub-tab no aplicable al tipo de proyecto: {$codigo}.");
        }

        $this->tabActiva = $codigo;
    }

    #[On('configuracion-paso-completado')]
    public function refrescar(): void
    {
        // Forzar re-render del header de tabs para que los checks reflejen el estado actual.
    }

    public function render(): View
    {
        $aplicables = $this->subTabsAplicables();
        $estados = [];
        foreach ($aplicables as $codigo) {
            $estados[$codigo] = DB::table($codigo)
                ->where('proyecto_id', (int) $this->proyecto->id)
                ->exists();
        }

        $tabActiva = $this->tabActiva;
        if (! in_array($tabActiva, $aplicables, true)) {
            $tabActiva = $aplicables[0] ?? '';
        }

        $metadata = [];
        foreach ($aplicables as $codigo) {
            $metadata[$codigo] = self::CATALOGOS[$codigo];
        }

        return view('livewire.tenancy.configurador-pasos.paso-catalogos-tipo', [
            'aplicables' => $aplicables,
            'estados' => $estados,
            'metadata' => $metadata,
            'tabActiva' => $tabActiva,
            'aliasActivo' => $tabActiva === '' ? null : self::CATALOGOS[$tabActiva]['alias'],
        ]);
    }

    /**
     * @return list<string>
     */
    private function subTabsAplicables(): array
    {
        $tipo = TipoOperacion::from((string) $this->proyecto->tipo_operacion);

        return PasoConfiguracion::subPasosCatalogosPorTipo($tipo);
    }

    /**
     * @param  list<string>  $aplicables
     */
    private function primerPendiente(array $aplicables): ?string
    {
        $proyectoId = (int) $this->proyecto->id;
        foreach ($aplicables as $codigo) {
            $tieneFila = DB::table($codigo)->where('proyecto_id', $proyectoId)->exists();
            if (! $tieneFila) {
                return $codigo;
            }
        }

        return null;
    }
}
