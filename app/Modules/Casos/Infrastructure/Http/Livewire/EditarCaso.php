<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Edita datos descriptivos de un caso del proyecto activo.
 *
 * Limitaciones intencionales (Domain del núcleo intacto §15.6):
 *   - tipo_caso INMUTABLE (CTI no se cambia).
 *   - estado_caso_id INMUTABLE (transiciones se hacen vía gestiones, no aquí).
 *   - identificadores únicos del CTI (numero_prestamo, codigo_ticket, codigo_lead,
 *     codigo_servicio) INMUTABLES en F34B (el unique compuesto y la integridad
 *     histórica los hacen sensibles).
 *
 * Edita:
 *   - Caso base: prioridad, cartera_id, fecha_ingreso.
 *   - CTI por tipo: campos descriptivos (saldos, asunto, valor, dirección, etc.).
 *
 * Permiso: casos.editar (SUPERVISOR + GESTOR + ADMIN_GLOBAL por defecto).
 */
final class EditarCaso extends Component
{
    public string $casoPublicId = '';

    public ?int $casoId = null;

    public string $tipoCaso = '';

    public string $personaPublicId = '';

    // Caso base
    public string $carteraId = '';

    public int $prioridad = 0;

    public string $fechaIngreso = '';

    // Cobranza
    public string $moneda = 'USD';

    public string $saldoCapital = '0';

    public string $saldoInteres = '0';

    public string $saldoTotal = '0';

    public string $cuotaMensual = '0';

    public int $cuotasPagadas = 0;

    public int $diasMora = 0;

    public string $fechaVencimiento = '';

    // CX
    public string $asunto = '';

    public string $descripcion = '';

    public string $categoriaTicketId = '';

    public string $prioridadTicketId = '';

    public string $nivelSlaId = '';

    public string $nivelEscalamientoId = '';

    public string $fechaLimiteSla = '';

    // Venta
    public string $valorEstimado = '0';

    public string $productoVentaId = '';

    public string $etapaEmbudoId = '';

    public string $origenLead = '';

    public string $fechaEstimadaCierre = '';

    // Servicio
    public string $tipoAccionServicioId = '';

    public string $estadoTecnicoId = '';

    public string $direccionServicio = '';

    public string $tecnicoAsignado = '';

    public string $fechaProgramada = '';

    public function mount(string $caso): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $row = DB::table('casos as c')
            ->join('personas as p', 'p.id', '=', 'c.persona_id')
            ->where('c.proyecto_id', $proyectoId)
            ->where('c.public_id', $caso)
            ->whereNull('c.eliminada_en')
            ->select([
                'c.id', 'c.tipo_caso', 'c.cartera_id', 'c.prioridad', 'c.fecha_ingreso',
                'p.public_id as persona_public_id',
            ])
            ->first();
        abort_unless($row !== null, 404, 'Caso no encontrado en el proyecto activo.');

        $this->casoPublicId = $caso;
        $this->casoId = (int) $row->id;
        $this->tipoCaso = (string) $row->tipo_caso;
        $this->personaPublicId = (string) $row->persona_public_id;
        $this->carteraId = (string) $row->cartera_id;
        $this->prioridad = (int) $row->prioridad;
        $this->fechaIngreso = Carbon::parse($row->fecha_ingreso)->format('Y-m-d');

        $this->cargarDatosCti();
    }

    private function cargarDatosCti(): void
    {
        if ($this->casoId === null) {
            return;
        }

        match ($this->tipoCaso) {
            'cobranza' => $this->cargarCobranza(),
            'ticket_cx' => $this->cargarCx(),
            'lead_venta' => $this->cargarVenta(),
            'servicio' => $this->cargarServicio(),
            default => null,
        };
    }

    private function cargarCobranza(): void
    {
        $row = DB::table('casos_cobranza')->where('caso_id', $this->casoId)->first();
        if ($row === null) {
            return;
        }
        $this->moneda = (string) $row->moneda;
        $this->saldoCapital = (string) $row->saldo_capital;
        $this->saldoInteres = (string) $row->saldo_interes;
        $this->saldoTotal = (string) $row->saldo_total;
        $this->cuotaMensual = (string) $row->cuota_mensual;
        $this->cuotasPagadas = (int) $row->cuotas_pagadas;
        $this->diasMora = (int) $row->dias_mora;
        $this->fechaVencimiento = Carbon::parse($row->fecha_vencimiento)->format('Y-m-d');
    }

    private function cargarCx(): void
    {
        $row = DB::table('casos_ticket_cx')->where('caso_id', $this->casoId)->first();
        if ($row === null) {
            return;
        }
        $this->asunto = (string) $row->asunto;
        $this->descripcion = (string) ($row->descripcion ?? '');
        $this->categoriaTicketId = $row->categoria_ticket_id !== null ? (string) $row->categoria_ticket_id : '';
        $this->prioridadTicketId = $row->prioridad_ticket_id !== null ? (string) $row->prioridad_ticket_id : '';
        $this->nivelSlaId = $row->nivel_sla_id !== null ? (string) $row->nivel_sla_id : '';
        $this->nivelEscalamientoId = $row->nivel_escalamiento_id !== null ? (string) $row->nivel_escalamiento_id : '';
        $this->fechaLimiteSla = $row->fecha_limite_sla !== null
            ? Carbon::parse($row->fecha_limite_sla)->format('Y-m-d\TH:i')
            : '';
    }

    private function cargarVenta(): void
    {
        $row = DB::table('casos_lead_venta')->where('caso_id', $this->casoId)->first();
        if ($row === null) {
            return;
        }
        $this->valorEstimado = (string) $row->valor_estimado;
        $this->moneda = (string) $row->moneda;
        $this->productoVentaId = $row->producto_venta_id !== null ? (string) $row->producto_venta_id : '';
        $this->etapaEmbudoId = $row->etapa_embudo_id !== null ? (string) $row->etapa_embudo_id : '';
        $this->origenLead = (string) ($row->origen_lead ?? '');
        $this->fechaEstimadaCierre = $row->fecha_estimada_cierre !== null
            ? Carbon::parse($row->fecha_estimada_cierre)->format('Y-m-d')
            : '';
    }

    private function cargarServicio(): void
    {
        $row = DB::table('casos_servicio')->where('caso_id', $this->casoId)->first();
        if ($row === null) {
            return;
        }
        $this->tipoAccionServicioId = $row->tipo_accion_servicio_id !== null ? (string) $row->tipo_accion_servicio_id : '';
        $this->estadoTecnicoId = $row->estado_tecnico_id !== null ? (string) $row->estado_tecnico_id : '';
        $this->direccionServicio = (string) ($row->direccion_servicio ?? '');
        $this->tecnicoAsignado = (string) ($row->tecnico_asignado ?? '');
        $this->fechaProgramada = $row->fecha_programada !== null
            ? Carbon::parse($row->fecha_programada)->format('Y-m-d\TH:i')
            : '';
    }

    public function guardar(): void
    {
        $proyecto = app('tenancy.proyecto_activo');
        if (auth()->user()?->tienePermiso('casos.editar', (int) $proyecto->id) !== true) {
            abort(403, 'No tienes permiso para editar casos en este proyecto.');
        }

        if ($this->casoId === null) {
            return;
        }

        $reglasComunes = [
            'carteraId' => ['required', 'integer'],
            'prioridad' => ['integer', 'min:0', 'max:1000'],
            'fechaIngreso' => ['required', 'date'],
        ];

        $reglas = match ($this->tipoCaso) {
            'cobranza' => $reglasComunes + [
                'moneda' => ['required', 'string', 'size:3'],
                'saldoCapital' => ['required', 'string'],
                'saldoInteres' => ['required', 'string'],
                'saldoTotal' => ['required', 'string'],
                'cuotaMensual' => ['required', 'string'],
                'cuotasPagadas' => ['integer', 'min:0'],
                'diasMora' => ['integer', 'min:0'],
                'fechaVencimiento' => ['required', 'date'],
            ],
            'ticket_cx' => $reglasComunes + [
                'asunto' => ['required', 'string', 'max:255'],
                'descripcion' => ['nullable', 'string', 'max:5000'],
            ],
            'lead_venta' => $reglasComunes + [
                'valorEstimado' => ['required', 'string'],
                'moneda' => ['required', 'string', 'size:3'],
            ],
            'servicio' => $reglasComunes + [
                'direccionServicio' => ['nullable', 'string', 'max:500'],
                'tecnicoAsignado' => ['nullable', 'string', 'max:150'],
            ],
            default => $reglasComunes,
        };

        $this->validate($reglas);

        $proyectoId = (int) $proyecto->id;
        $ahora = Carbon::now();

        DB::transaction(function () use ($proyectoId, $ahora): void {
            DB::table('casos')
                ->where('id', $this->casoId)
                ->where('proyecto_id', $proyectoId)
                ->update([
                    'cartera_id' => (int) $this->carteraId,
                    'prioridad' => $this->prioridad,
                    'fecha_ingreso' => $this->fechaIngreso,
                    'actualizada_en' => $ahora,
                ]);

            match ($this->tipoCaso) {
                'cobranza' => $this->guardarCobranza($ahora),
                'ticket_cx' => $this->guardarCx($ahora),
                'lead_venta' => $this->guardarVenta($ahora),
                'servicio' => $this->guardarServicio($ahora),
                default => null,
            };
        });

        session()->flash('caso_editado', 'Caso actualizado.');

        $this->redirectRoute('proyectos.trabajo', [
            'proyecto_id' => $proyectoId,
            'persona' => $this->personaPublicId,
            'caso' => $this->casoPublicId,
        ], navigate: true);
    }

    private function guardarCobranza(Carbon $ahora): void
    {
        DB::table('casos_cobranza')->where('caso_id', $this->casoId)->update([
            'moneda' => $this->moneda,
            'saldo_capital' => $this->saldoCapital,
            'saldo_interes' => $this->saldoInteres,
            'saldo_total' => $this->saldoTotal,
            'cuota_mensual' => $this->cuotaMensual,
            'cuotas_pagadas' => $this->cuotasPagadas,
            'dias_mora' => $this->diasMora,
            'fecha_vencimiento' => $this->fechaVencimiento,
            'actualizada_en' => $ahora,
        ]);
    }

    private function guardarCx(Carbon $ahora): void
    {
        DB::table('casos_ticket_cx')->where('caso_id', $this->casoId)->update([
            'asunto' => $this->asunto,
            'descripcion' => $this->descripcion !== '' ? $this->descripcion : null,
            'categoria_ticket_id' => $this->categoriaTicketId !== '' ? (int) $this->categoriaTicketId : null,
            'prioridad_ticket_id' => $this->prioridadTicketId !== '' ? (int) $this->prioridadTicketId : null,
            'nivel_sla_id' => $this->nivelSlaId !== '' ? (int) $this->nivelSlaId : null,
            'nivel_escalamiento_id' => $this->nivelEscalamientoId !== '' ? (int) $this->nivelEscalamientoId : null,
            'fecha_limite_sla' => $this->fechaLimiteSla !== '' ? $this->fechaLimiteSla : null,
            'actualizada_en' => $ahora,
        ]);
    }

    private function guardarVenta(Carbon $ahora): void
    {
        DB::table('casos_lead_venta')->where('caso_id', $this->casoId)->update([
            'valor_estimado' => $this->valorEstimado,
            'moneda' => $this->moneda,
            'producto_venta_id' => $this->productoVentaId !== '' ? (int) $this->productoVentaId : null,
            'etapa_embudo_id' => $this->etapaEmbudoId !== '' ? (int) $this->etapaEmbudoId : null,
            'origen_lead' => $this->origenLead !== '' ? $this->origenLead : null,
            'fecha_estimada_cierre' => $this->fechaEstimadaCierre !== '' ? $this->fechaEstimadaCierre : null,
            'actualizada_en' => $ahora,
        ]);
    }

    private function guardarServicio(Carbon $ahora): void
    {
        DB::table('casos_servicio')->where('caso_id', $this->casoId)->update([
            'tipo_accion_servicio_id' => $this->tipoAccionServicioId !== '' ? (int) $this->tipoAccionServicioId : null,
            'estado_tecnico_id' => $this->estadoTecnicoId !== '' ? (int) $this->estadoTecnicoId : null,
            'direccion_servicio' => $this->direccionServicio !== '' ? $this->direccionServicio : null,
            'tecnico_asignado' => $this->tecnicoAsignado !== '' ? $this->tecnicoAsignado : null,
            'fecha_programada' => $this->fechaProgramada !== '' ? $this->fechaProgramada : null,
            'actualizada_en' => $ahora,
        ]);
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $carteras = DB::table('carteras')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $catalogosTipo = match ($this->tipoCaso) {
            'ticket_cx' => [
                'categorias' => DB::table('categorias_ticket')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
                'prioridades' => DB::table('prioridades_ticket')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('orden')->get(['id', 'nombre']),
                'niveles_sla' => DB::table('niveles_sla')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
                'niveles_esc' => DB::table('niveles_escalamiento')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            ],
            'lead_venta' => [
                'productos' => DB::table('productos_venta')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
                'etapas' => DB::table('etapas_embudo')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('orden')->get(['id', 'nombre']),
            ],
            'servicio' => [
                'tipos_accion' => DB::table('tipos_accion_servicio')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
                'estados_tec' => DB::table('estados_tecnicos')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            ],
            default => [],
        };

        return view('casos::livewire.editar-caso', [
            'carteras' => $carteras,
            'catalogosTipo' => $catalogosTipo,
        ]);
    }
}
