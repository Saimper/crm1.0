<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Infrastructure\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Edita un compromiso solo si está en estado=pendiente. Datos descriptivos
 * (monto, fecha, acción) y campos de catálogo (tipo_pago, etapa, nivel SLA,
 * tipo de acción).
 *
 * Limitaciones intencionales (Domain del núcleo intacto):
 *   - Solo compromisos en estado=pendiente. Cumplido/roto/cancelado son
 *     inmutables (transiciones via UseCases existentes).
 *   - No se cambia tipo_compromiso (CTI inmutable).
 *   - No se cambia gestion_origen_id ni usuario_id (auditoría histórica).
 *
 * Permiso: compromisos.crear (SUPERVISOR + GESTOR + ADMIN_GLOBAL por defecto).
 */
final class EditarCompromiso extends Component
{
    public string $compromisoPublicId = '';

    public ?int $compromisoId = null;

    public string $tipoCompromiso = '';

    public string $estado = '';

    public string $personaPublicId = '';

    public string $casoPublicId = '';

    public string $fechaVencimiento = '';

    // Promesa pago
    public string $monto = '0';

    public string $moneda = 'USD';

    public string $tipoPagoId = '';

    // Resolución ticket
    public string $accionComprometida = '';

    public string $fechaLimiteSla = '';

    public string $nivelEscalamientoId = '';

    // Cierre venta
    public string $montoCierre = '0';

    public string $etapaEmbudoId = '';

    // Acción servicio
    public string $descripcionAccion = '';

    public string $fechaProgramada = '';

    public string $tipoAccionServicioId = '';

    public string $tecnicoAsignado = '';

    public function mount(string $compromiso): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $row = DB::table('compromisos as c')
            ->join('casos as cs', 'cs.id', '=', 'c.caso_id')
            ->join('personas as p', 'p.id', '=', 'cs.persona_id')
            ->where('c.proyecto_id', $proyectoId)
            ->where('c.public_id', $compromiso)
            ->whereNull('c.eliminada_en')
            ->select([
                'c.id', 'c.tipo_compromiso', 'c.estado', 'c.fecha_vencimiento',
                'cs.public_id as caso_public_id',
                'p.public_id as persona_public_id',
            ])
            ->first();
        abort_unless($row !== null, 404, 'Compromiso no encontrado en el proyecto.');

        if ((string) $row->estado !== 'pendiente') {
            abort(409, 'Solo compromisos en estado pendiente son editables.');
        }

        $this->compromisoPublicId = $compromiso;
        $this->compromisoId = (int) $row->id;
        $this->tipoCompromiso = (string) $row->tipo_compromiso;
        $this->estado = (string) $row->estado;
        $this->casoPublicId = (string) $row->caso_public_id;
        $this->personaPublicId = (string) $row->persona_public_id;
        $this->fechaVencimiento = Carbon::parse($row->fecha_vencimiento)->format('Y-m-d');

        $this->cargarCti();
    }

    private function cargarCti(): void
    {
        match ($this->tipoCompromiso) {
            'promesa_pago' => $this->cargarPromesa(),
            'resolucion_ticket' => $this->cargarResolucion(),
            'cierre_venta' => $this->cargarCierre(),
            'accion_servicio' => $this->cargarAccion(),
            default => null,
        };
    }

    private function cargarPromesa(): void
    {
        $row = DB::table('compromisos_promesa_pago')->where('compromiso_id', $this->compromisoId)->first();
        if ($row === null) {
            return;
        }
        $this->monto = (string) $row->monto;
        $this->moneda = (string) $row->moneda;
        $this->tipoPagoId = $row->tipo_pago_id !== null ? (string) $row->tipo_pago_id : '';
    }

    private function cargarResolucion(): void
    {
        $row = DB::table('compromisos_resolucion_ticket')->where('compromiso_id', $this->compromisoId)->first();
        if ($row === null) {
            return;
        }
        $this->accionComprometida = (string) $row->accion_comprometida;
        if (property_exists($row, 'fecha_limite_sla') && $row->fecha_limite_sla !== null) {
            $this->fechaLimiteSla = Carbon::parse($row->fecha_limite_sla)->format('Y-m-d\TH:i');
        }
        if (property_exists($row, 'nivel_escalamiento_id') && $row->nivel_escalamiento_id !== null) {
            $this->nivelEscalamientoId = (string) $row->nivel_escalamiento_id;
        }
    }

    private function cargarCierre(): void
    {
        $row = DB::table('compromisos_cierre_venta')->where('compromiso_id', $this->compromisoId)->first();
        if ($row === null) {
            return;
        }
        $this->montoCierre = (string) $row->monto_cierre;
        $this->moneda = (string) $row->moneda;
        $this->etapaEmbudoId = $row->etapa_embudo_id !== null ? (string) $row->etapa_embudo_id : '';
    }

    private function cargarAccion(): void
    {
        $row = DB::table('compromisos_accion_servicio')->where('compromiso_id', $this->compromisoId)->first();
        if ($row === null) {
            return;
        }
        $this->descripcionAccion = (string) $row->descripcion_accion;
        if (property_exists($row, 'fecha_programada') && $row->fecha_programada !== null) {
            $this->fechaProgramada = Carbon::parse($row->fecha_programada)->format('Y-m-d\TH:i');
        }
        $this->tipoAccionServicioId = $row->tipo_accion_servicio_id !== null ? (string) $row->tipo_accion_servicio_id : '';
        $this->tecnicoAsignado = (string) ($row->tecnico_asignado ?? '');
    }

    public function guardar(): void
    {
        $proyecto = app('tenancy.proyecto_activo');
        if (auth()->user()?->tienePermiso('compromisos.crear', (int) $proyecto->id) !== true) {
            abort(403, 'No tienes permiso para editar compromisos en este proyecto.');
        }

        if ($this->compromisoId === null) {
            return;
        }

        // Defensa: doble check de estado en BD (race condition).
        $estadoActual = (string) DB::table('compromisos')->where('id', $this->compromisoId)->value('estado');
        if ($estadoActual !== 'pendiente') {
            abort(409, 'El compromiso ya no está pendiente.');
        }

        $reglas = ['fechaVencimiento' => ['required', 'date']];
        $reglas += match ($this->tipoCompromiso) {
            'promesa_pago' => [
                'monto' => ['required', 'string'],
                'moneda' => ['required', 'string', 'size:3'],
            ],
            'resolucion_ticket' => [
                'accionComprometida' => ['required', 'string', 'max:500'],
            ],
            'cierre_venta' => [
                'montoCierre' => ['required', 'string'],
                'moneda' => ['required', 'string', 'size:3'],
            ],
            'accion_servicio' => [
                'descripcionAccion' => ['required', 'string', 'max:500'],
                'tecnicoAsignado' => ['nullable', 'string', 'max:150'],
            ],
            default => [],
        };

        $this->validate($reglas);

        $proyectoId = (int) $proyecto->id;
        $ahora = Carbon::now();

        DB::transaction(function () use ($proyectoId, $ahora): void {
            DB::table('compromisos')
                ->where('id', $this->compromisoId)
                ->where('proyecto_id', $proyectoId)
                ->update([
                    'fecha_vencimiento' => $this->fechaVencimiento,
                    'actualizada_en' => $ahora,
                ]);

            match ($this->tipoCompromiso) {
                'promesa_pago' => $this->guardarPromesa($ahora),
                'resolucion_ticket' => $this->guardarResolucion($ahora),
                'cierre_venta' => $this->guardarCierre($ahora),
                'accion_servicio' => $this->guardarAccion($ahora),
                default => null,
            };
        });

        session()->flash('compromiso_editado', 'Compromiso actualizado.');

        $this->redirectRoute('proyectos.trabajo', [
            'proyecto_id' => $proyectoId,
            'persona' => $this->personaPublicId,
            'caso' => $this->casoPublicId,
        ], navigate: true);
    }

    private function guardarPromesa(Carbon $ahora): void
    {
        DB::table('compromisos_promesa_pago')->where('compromiso_id', $this->compromisoId)->update([
            'monto' => $this->monto,
            'moneda' => $this->moneda,
            'tipo_pago_id' => $this->tipoPagoId !== '' ? (int) $this->tipoPagoId : null,
            'actualizada_en' => $ahora,
        ]);
    }

    private function guardarResolucion(Carbon $ahora): void
    {
        $payload = [
            'accion_comprometida' => $this->accionComprometida,
            'actualizada_en' => $ahora,
        ];
        if ($this->fechaLimiteSla !== '') {
            $payload['fecha_limite_sla'] = $this->fechaLimiteSla;
        }
        if ($this->nivelEscalamientoId !== '') {
            $payload['nivel_escalamiento_id'] = (int) $this->nivelEscalamientoId;
        }

        DB::table('compromisos_resolucion_ticket')->where('compromiso_id', $this->compromisoId)->update($payload);
    }

    private function guardarCierre(Carbon $ahora): void
    {
        $payload = [
            'monto_cierre' => $this->montoCierre,
            'moneda' => $this->moneda,
            'actualizada_en' => $ahora,
        ];
        if ($this->etapaEmbudoId !== '') {
            $payload['etapa_embudo_id'] = (int) $this->etapaEmbudoId;
        }

        DB::table('compromisos_cierre_venta')->where('compromiso_id', $this->compromisoId)->update($payload);
    }

    private function guardarAccion(Carbon $ahora): void
    {
        $payload = [
            'descripcion_accion' => $this->descripcionAccion,
            'tecnico_asignado' => $this->tecnicoAsignado !== '' ? $this->tecnicoAsignado : null,
            'actualizada_en' => $ahora,
        ];
        if ($this->fechaProgramada !== '') {
            $payload['fecha_programada'] = $this->fechaProgramada;
        }
        if ($this->tipoAccionServicioId !== '') {
            $payload['tipo_accion_servicio_id'] = (int) $this->tipoAccionServicioId;
        }

        DB::table('compromisos_accion_servicio')->where('compromiso_id', $this->compromisoId)->update($payload);
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $catalogosTipo = match ($this->tipoCompromiso) {
            'promesa_pago' => [
                'tipos_pago' => DB::table('tipos_pago')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            ],
            'resolucion_ticket' => [
                'niveles_esc' => DB::table('niveles_escalamiento')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            ],
            'cierre_venta' => [
                'etapas' => DB::table('etapas_embudo')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('orden')->get(['id', 'nombre']),
            ],
            'accion_servicio' => [
                'tipos_accion' => DB::table('tipos_accion_servicio')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            ],
            default => [],
        };

        return view('compromisos::livewire.editar-compromiso', [
            'catalogosTipo' => $catalogosTipo,
        ]);
    }
}
