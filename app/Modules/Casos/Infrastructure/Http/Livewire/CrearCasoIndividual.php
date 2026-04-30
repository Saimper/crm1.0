<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Http\Livewire;

use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use App\Modules\Cobranza\Domain\Exceptions\NumeroPrestamoYaRegistrado;
use App\Modules\Cx\Application\DTOs\RegistrarCasoTicketCxInput;
use App\Modules\Cx\Application\UseCases\RegistrarCasoTicketCx;
use App\Modules\Servicio\Application\DTOs\RegistrarCasoServicioInput;
use App\Modules\Servicio\Application\UseCases\RegistrarCasoServicio;
use App\Modules\Venta\Application\DTOs\RegistrarCasoLeadVentaInput;
use App\Modules\Venta\Application\UseCases\RegistrarCasoLeadVenta;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Crea un caso individual para una persona específica del proyecto activo.
 * Detecta tipo_operacion del proyecto y delega al UseCase correspondiente
 * (RegistrarCasoCobranza/TicketCx/LeadVenta/Servicio).
 *
 * Permiso: casos.crear (solo SUPERVISOR + ADMIN_GLOBAL por defecto).
 */
final class CrearCasoIndividual extends Component
{
    #[Url(as: 'persona', except: '')]
    public string $personaPublicId = '';

    public ?int $personaId = null;

    public string $tipoOperacion = '';

    public string $carteraId = '';

    public string $estadoCasoId = '';

    public int $prioridad = 0;

    public string $fechaIngreso = '';

    // Cobranza
    public string $numeroPrestamo = '';

    public string $moneda = 'USD';

    public string $montoOriginal = '0';

    public string $saldoCapital = '0';

    public string $saldoInteres = '0';

    public string $saldoTotal = '0';

    public string $cuotaMensual = '0';

    public int $cuotasTotales = 0;

    public int $cuotasPagadas = 0;

    public int $diasMora = 0;

    public string $fechaDesembolso = '';

    public string $fechaVencimiento = '';

    // CX
    public string $codigoTicket = '';

    public string $asunto = '';

    public string $descripcion = '';

    public string $categoriaTicketId = '';

    public string $prioridadTicketId = '';

    public string $nivelSlaId = '';

    public string $fechaReporte = '';

    // Venta
    public string $codigoLead = '';

    public string $valorEstimadoMonto = '0';

    public string $productoVentaId = '';

    public string $etapaEmbudoId = '';

    public string $origenLead = '';

    public string $fechaPrimerContacto = '';

    // Servicio
    public string $codigoServicio = '';

    public string $fechaSolicitud = '';

    public string $fechaProgramada = '';

    public string $direccionServicio = '';

    public string $tecnicoAsignado = '';

    public string $tipoAccionServicioId = '';

    public function mount(): void
    {
        $proyecto = app('tenancy.proyecto_activo');
        $this->tipoOperacion = (string) $proyecto->tipo_operacion;
        $this->fechaIngreso = (new DateTimeImmutable)->format('Y-m-d');
        $this->fechaReporte = (new DateTimeImmutable)->format('Y-m-d\TH:i');
        $this->fechaPrimerContacto = (new DateTimeImmutable)->format('Y-m-d');
        $this->fechaSolicitud = (new DateTimeImmutable)->format('Y-m-d');

        if ($this->personaPublicId !== '') {
            $persona = DB::table('personas')
                ->where('proyecto_id', (int) $proyecto->id)
                ->where('public_id', $this->personaPublicId)
                ->whereNull('eliminada_en')
                ->first();
            if ($persona !== null) {
                $this->personaId = (int) $persona->id;
            }
        }
    }

    public function guardar(
        RegistrarCasoCobranza $ucCobranza,
        RegistrarCasoTicketCx $ucCx,
        RegistrarCasoLeadVenta $ucVenta,
        RegistrarCasoServicio $ucServicio,
    ): void {
        if ($this->personaId === null) {
            $this->addError('personaPublicId', 'Persona no encontrada en el proyecto.');

            return;
        }

        $reglasComunes = [
            'carteraId' => ['required', 'integer'],
            'estadoCasoId' => ['required', 'integer'],
            'prioridad' => ['integer', 'min:0', 'max:1000'],
            'fechaIngreso' => ['required', 'date'],
        ];

        $reglas = match ($this->tipoOperacion) {
            'cobranza' => $reglasComunes + [
                'numeroPrestamo' => ['required', 'string', 'max:80'],
                'moneda' => ['required', 'string', 'size:3'],
                'montoOriginal' => ['required', 'string'],
                'saldoCapital' => ['required', 'string'],
                'saldoInteres' => ['required', 'string'],
                'saldoTotal' => ['required', 'string'],
                'cuotaMensual' => ['required', 'string'],
                'cuotasTotales' => ['integer', 'min:0'],
                'cuotasPagadas' => ['integer', 'min:0'],
                'diasMora' => ['integer', 'min:0'],
                'fechaDesembolso' => ['required', 'date'],
                'fechaVencimiento' => ['required', 'date'],
            ],
            'cx' => $reglasComunes + [
                'codigoTicket' => ['required', 'string', 'max:80'],
                'asunto' => ['required', 'string', 'max:255'],
                'descripcion' => ['nullable', 'string', 'max:5000'],
                'fechaReporte' => ['required', 'date'],
            ],
            'venta' => $reglasComunes + [
                'codigoLead' => ['required', 'string', 'max:80'],
                'valorEstimadoMonto' => ['required', 'string'],
                'moneda' => ['required', 'string', 'size:3'],
                'fechaPrimerContacto' => ['required', 'date'],
            ],
            'servicio' => $reglasComunes + [
                'codigoServicio' => ['required', 'string', 'max:80'],
                'fechaSolicitud' => ['required', 'date'],
                'direccionServicio' => ['nullable', 'string', 'max:500'],
                'tecnicoAsignado' => ['nullable', 'string', 'max:200'],
            ],
            default => $reglasComunes,
        };

        $this->validate($reglas);

        $proyecto = app('tenancy.proyecto_activo');
        $proyectoId = (int) $proyecto->id;
        $publicIdNuevoCaso = '';

        try {
            $publicIdNuevoCaso = match ($this->tipoOperacion) {
                'cobranza' => $ucCobranza->execute(new RegistrarCasoCobranzaInput(
                    proyectoId: $proyectoId,
                    carteraId: (int) $this->carteraId,
                    personaId: $this->personaId,
                    estadoCasoId: (int) $this->estadoCasoId,
                    fechaIngreso: new DateTimeImmutable($this->fechaIngreso),
                    prioridad: $this->prioridad,
                    numeroPrestamo: trim($this->numeroPrestamo),
                    moneda: $this->moneda,
                    montoOriginal: $this->montoOriginal,
                    saldoCapital: $this->saldoCapital,
                    saldoInteres: $this->saldoInteres,
                    saldoTotal: $this->saldoTotal,
                    cuotaMensual: $this->cuotaMensual,
                    cuotasTotales: $this->cuotasTotales,
                    cuotasPagadas: $this->cuotasPagadas,
                    diasMora: $this->diasMora,
                    fechaDesembolso: new DateTimeImmutable($this->fechaDesembolso),
                    fechaVencimiento: new DateTimeImmutable($this->fechaVencimiento),
                ))->publicId,
                'cx' => $ucCx->execute(new RegistrarCasoTicketCxInput(
                    proyectoId: $proyectoId,
                    carteraId: (int) $this->carteraId,
                    personaId: $this->personaId,
                    estadoCasoId: (int) $this->estadoCasoId,
                    fechaIngreso: new DateTimeImmutable($this->fechaIngreso),
                    prioridad: $this->prioridad,
                    codigoTicket: trim($this->codigoTicket),
                    asunto: trim($this->asunto),
                    descripcion: $this->descripcion !== '' ? $this->descripcion : null,
                    categoriaTicketId: $this->categoriaTicketId !== '' ? (int) $this->categoriaTicketId : null,
                    prioridadTicketId: $this->prioridadTicketId !== '' ? (int) $this->prioridadTicketId : null,
                    nivelSlaId: $this->nivelSlaId !== '' ? (int) $this->nivelSlaId : null,
                    nivelEscalamientoId: null,
                    fechaReporte: new DateTimeImmutable($this->fechaReporte),
                    fechaLimiteSla: null,
                ))->publicId,
                'venta' => $ucVenta->execute(new RegistrarCasoLeadVentaInput(
                    proyectoId: $proyectoId,
                    carteraId: (int) $this->carteraId,
                    personaId: $this->personaId,
                    estadoCasoId: (int) $this->estadoCasoId,
                    fechaIngreso: new DateTimeImmutable($this->fechaIngreso),
                    prioridad: $this->prioridad,
                    codigoLead: trim($this->codigoLead),
                    productoVentaId: $this->productoVentaId !== '' ? (int) $this->productoVentaId : null,
                    etapaEmbudoId: $this->etapaEmbudoId !== '' ? (int) $this->etapaEmbudoId : null,
                    valorEstimadoMonto: $this->valorEstimadoMonto,
                    moneda: $this->moneda,
                    origenLead: $this->origenLead !== '' ? $this->origenLead : null,
                    fechaPrimerContacto: new DateTimeImmutable($this->fechaPrimerContacto),
                    fechaEstimadaCierre: null,
                ))->publicId,
                'servicio' => $ucServicio->execute(new RegistrarCasoServicioInput(
                    proyectoId: $proyectoId,
                    carteraId: (int) $this->carteraId,
                    personaId: $this->personaId,
                    estadoCasoId: (int) $this->estadoCasoId,
                    fechaIngreso: new DateTimeImmutable($this->fechaIngreso),
                    prioridad: $this->prioridad,
                    codigoServicio: trim($this->codigoServicio),
                    tipoAccionServicioId: $this->tipoAccionServicioId !== '' ? (int) $this->tipoAccionServicioId : null,
                    estadoTecnicoId: null,
                    direccionServicio: $this->direccionServicio !== '' ? $this->direccionServicio : null,
                    tecnicoAsignado: $this->tecnicoAsignado !== '' ? $this->tecnicoAsignado : null,
                    fechaSolicitud: new DateTimeImmutable($this->fechaSolicitud),
                    fechaProgramada: $this->fechaProgramada !== '' ? new DateTimeImmutable($this->fechaProgramada) : null,
                ))->publicId,
                default => throw new InvalidArgumentException('Tipo de operación no soportado: '.$this->tipoOperacion),
            };
        } catch (NumeroPrestamoYaRegistrado $e) {
            $this->addError('numeroPrestamo', $e->getMessage());

            return;
        } catch (InvalidArgumentException $e) {
            $this->addError('general', $e->getMessage());

            return;
        } catch (Throwable $e) {
            $this->addError('general', $e->getMessage());

            return;
        }

        session()->flash('caso_creado', $publicIdNuevoCaso);

        $this->redirectRoute('proyectos.trabajo', [
            'proyecto_id' => $proyectoId,
            'persona' => $this->personaPublicId,
            'caso' => $publicIdNuevoCaso,
        ], navigate: true);
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $persona = $this->personaId !== null
            ? DB::table('personas')->where('id', $this->personaId)->first()
            : null;

        $carteras = DB::table('carteras')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->where('activo', true)
            ->orderBy('nombre')
            ->select(['id', 'nombre'])
            ->get();

        $estados = DB::table('estados_caso')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->orderBy('orden')
            ->select(['id', 'nombre'])
            ->get();

        $catalogosTipo = $this->cargarCatalogosTipo($proyectoId);

        return view('casos::livewire.crear-caso-individual', [
            'persona' => $persona,
            'carteras' => $carteras,
            'estados' => $estados,
            'catalogosTipo' => $catalogosTipo,
        ]);
    }

    /**
     * @return array<string, Collection<int, object>>
     */
    private function cargarCatalogosTipo(int $proyectoId): array
    {
        return match ($this->tipoOperacion) {
            'cx' => [
                'categorias' => DB::table('categorias_ticket')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
                'prioridades' => DB::table('prioridades_ticket')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('orden')->get(['id', 'nombre']),
                'niveles_sla' => DB::table('niveles_sla')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            ],
            'venta' => [
                'productos' => DB::table('productos_venta')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
                'etapas' => DB::table('etapas_embudo')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('orden')->get(['id', 'nombre']),
            ],
            'servicio' => [
                'tipos_accion' => DB::table('tipos_accion_servicio')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            ],
            default => [],
        };
    }
}
