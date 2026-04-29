<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Http\Livewire;

use App\Modules\Cobranza\Domain\ValueObjects\DatosPromesaPago;
use App\Modules\Cobranza\Domain\ValueObjects\FechaPromesa;
use App\Modules\Cobranza\Domain\ValueObjects\MontoPromesa;
use App\Modules\Cx\Domain\ValueObjects\AccionComprometida;
use App\Modules\Cx\Domain\ValueObjects\DatosResolucionTicket;
use App\Modules\Cx\Domain\ValueObjects\FechaLimiteSla;
use App\Modules\Venta\Domain\ValueObjects\DatosCierreVenta;
use App\Modules\Venta\Domain\ValueObjects\FechaCierreEstimada;
use App\Modules\Venta\Domain\ValueObjects\MontoCierre;
use App\Modules\Servicio\Domain\ValueObjects\DatosAccionServicio;
use App\Modules\Servicio\Domain\ValueObjects\DescripcionAccion;
use App\Modules\Servicio\Domain\ValueObjects\FechaProgramada;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

/**
 * Formulario de Nueva Gestión embebido en la Vista de Trabajo.
 * Campos abstractos comunes (canal, tipo, resultado, notas, duración) + slot cobranza
 * (monto + fecha + tipo de pago) cuando `tipo_caso = cobranza` y el resultado exige compromiso.
 */
final class NuevaGestion extends Component
{
    public int $casoId = 0;

    public int $personaId = 0;

    public string $tipoCaso = '';

    public ?int $canalId = null;

    public ?int $tipoGestionId = null;

    public ?int $resultadoId = null;

    public ?int $contactoId = null;

    public ?int $motivoNoContactoId = null;

    public ?int $causaId = null;

    public string $notas = '';

    public ?int $duracionSegundos = null;

    public ?string $promesaMonto = null;

    public ?string $promesaFecha = null;

    public ?int $promesaTipoPagoId = null;

    public ?string $resolucionAccion = null;

    public ?string $resolucionFechaLimite = null;

    public ?int $resolucionNivelEscalamientoId = null;

    public ?string $cierreMonto = null;

    public ?string $cierreFechaEstimada = null;

    public ?int $cierreEtapaEmbudoId = null;

    public ?string $accionDescripcion = null;

    public ?string $accionFechaProgramada = null;

    public ?int $accionTipoAccionId = null;

    public ?string $accionTecnicoAsignado = null;

    public function mount(int $casoId, int $personaId, string $tipoCaso): void
    {
        $this->casoId    = $casoId;
        $this->personaId = $personaId;
        $this->tipoCaso  = $tipoCaso;
    }

    public function guardar(RegistrarGestion $useCase): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $reglas = [
            'canalId'       => ['required', 'integer'],
            'tipoGestionId' => ['required', 'integer'],
            'resultadoId'   => ['required', 'integer'],
            'notas'         => ['nullable', 'string', 'max:2000'],
        ];

        $resultado = $this->resultadoSeleccionado($proyectoId);
        if ($resultado === null) {
            $this->addError('resultadoId', 'Selecciona un resultado válido.');
            return;
        }

        if ((bool) $resultado->requiere_causa) {
            $reglas['causaId'] = ['required', 'integer'];
        }
        $esCobranzaConPromesa = $this->tipoCaso === 'cobranza'    && (bool) $resultado->requiere_compromiso;
        $esCxConResolucion    = $this->tipoCaso === 'ticket_cx'   && (bool) $resultado->requiere_compromiso;
        $esVentaConCierre     = $this->tipoCaso === 'lead_venta'  && (bool) $resultado->requiere_compromiso;
        $esServicioConAccion  = $this->tipoCaso === 'servicio'    && (bool) $resultado->requiere_compromiso;
        if ($esCobranzaConPromesa) {
            $reglas['promesaMonto'] = ['required', 'regex:/^\d+(\.\d{1,2})?$/'];
            $reglas['promesaFecha'] = ['required', 'date'];
        }
        if ($esCxConResolucion) {
            $reglas['resolucionAccion']      = ['required', 'string', 'max:500'];
            $reglas['resolucionFechaLimite'] = ['required', 'date'];
        }
        if ($esVentaConCierre) {
            $reglas['cierreMonto']         = ['required', 'regex:/^\d+(\.\d{1,2})?$/'];
            $reglas['cierreFechaEstimada'] = ['required', 'date'];
        }
        if ($esServicioConAccion) {
            $reglas['accionDescripcion']     = ['required', 'string', 'max:500'];
            $reglas['accionFechaProgramada'] = ['required', 'date'];
        }

        $this->validate($reglas);

        try {
            $datosCompromiso = null;
            if ($esCobranzaConPromesa) {
                $datosCompromiso = new DatosPromesaPago(
                    monto:            new MontoPromesa((string) $this->promesaMonto, 'USD'),
                    fechaVencimiento: new FechaPromesa(new DateTimeImmutable((string) $this->promesaFecha)),
                    tipoPagoId:       $this->promesaTipoPagoId,
                );
            } elseif ($esCxConResolucion) {
                $datosCompromiso = new DatosResolucionTicket(
                    accion:              new AccionComprometida((string) $this->resolucionAccion),
                    fechaLimite:         new FechaLimiteSla(new DateTimeImmutable((string) $this->resolucionFechaLimite)),
                    nivelEscalamientoId: $this->resolucionNivelEscalamientoId,
                );
            } elseif ($esVentaConCierre) {
                $datosCompromiso = new DatosCierreVenta(
                    monto:         new MontoCierre((string) $this->cierreMonto, 'USD'),
                    fechaEstimada: new FechaCierreEstimada(new DateTimeImmutable((string) $this->cierreFechaEstimada)),
                    etapaEmbudoId: $this->cierreEtapaEmbudoId,
                );
            } elseif ($esServicioConAccion) {
                $datosCompromiso = new DatosAccionServicio(
                    descripcion:          new DescripcionAccion((string) $this->accionDescripcion),
                    fechaProgramada:      new FechaProgramada(new DateTimeImmutable((string) $this->accionFechaProgramada)),
                    tipoAccionServicioId: $this->accionTipoAccionId,
                    tecnicoAsignado:      $this->accionTecnicoAsignado !== '' ? $this->accionTecnicoAsignado : null,
                );
            }

            $useCase->execute(new RegistrarGestionInput(
                publicId:          (string) Str::ulid(),
                proyectoId:        $proyectoId,
                casoId:            $this->casoId,
                personaId:         $this->personaId,
                contactoId:        $this->contactoId,
                canalId:           (int) $this->canalId,
                tipoGestionId:     (int) $this->tipoGestionId,
                resultadoId:       (int) $this->resultadoId,
                motivoNoContactoId: $this->motivoNoContactoId,
                causaId:           $this->causaId,
                usuarioId:         (int) auth()->id(),
                notas:             $this->notas !== '' ? $this->notas : null,
                duracion:          $this->duracionSegundos ? new DuracionSegundos((int) $this->duracionSegundos) : null,
                creadaEn:          new DateTimeImmutable(),
                datosCompromiso:   $datosCompromiso,
            ));
        } catch (Throwable $e) {
            $this->addError('general', $e->getMessage());
            return;
        }

        $this->reset([
            'canalId', 'tipoGestionId', 'resultadoId', 'contactoId',
            'motivoNoContactoId', 'causaId', 'notas', 'duracionSegundos',
            'promesaMonto', 'promesaFecha', 'promesaTipoPagoId',
            'resolucionAccion', 'resolucionFechaLimite', 'resolucionNivelEscalamientoId',
            'cierreMonto', 'cierreFechaEstimada', 'cierreEtapaEmbudoId',
            'accionDescripcion', 'accionFechaProgramada', 'accionTipoAccionId', 'accionTecnicoAsignado',
        ]);

        $this->dispatch('gestion-registrada');
        session()->flash('nueva-gestion-ok', 'Gestión registrada.');
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $resultadoActual = $this->resultadoSeleccionado($proyectoId);

        return view('casos::livewire.nueva-gestion', [
            'canales'          => $this->canales(),
            'tiposGestion'     => $this->tiposGestion($proyectoId),
            'resultados'       => $this->resultados($proyectoId),
            'motivos'          => $this->motivos($proyectoId),
            'causas'           => $this->causas($proyectoId),
            'tiposPago'        => $this->tipoCaso === 'cobranza'   ? $this->tiposPago($proyectoId)       : collect(),
            'nivelesEscalamiento' => $this->tipoCaso === 'ticket_cx' ? $this->nivelesEscalamiento($proyectoId) : collect(),
            'etapasEmbudo'     => $this->tipoCaso === 'lead_venta' ? $this->etapasEmbudo($proyectoId)    : collect(),
            'tiposAccionServicio' => $this->tipoCaso === 'servicio'   ? $this->tiposAccionServicio($proyectoId) : collect(),
            'contactos'        => $this->contactos($proyectoId),
            'requiereCausa'    => $resultadoActual ? (bool) $resultadoActual->requiere_causa : false,
            'requiereCompromiso' => $resultadoActual ? (bool) $resultadoActual->requiere_compromiso : false,
            'esContactoEfectivo' => $resultadoActual ? (bool) $resultadoActual->es_contacto_efectivo : false,
        ]);
    }

    private function resultadoSeleccionado(int $proyectoId): ?object
    {
        if ($this->resultadoId === null) {
            return null;
        }

        return DB::table('resultados')
            ->where('id', $this->resultadoId)
            ->where('proyecto_id', $proyectoId)
            ->first();
    }

    private function canales(): Collection
    {
        return DB::table('canales')->where('activo', true)->orderBy('orden')->get();
    }

    private function tiposGestion(int $proyectoId): Collection
    {
        return DB::table('tipos_gestion')
            ->where('proyecto_id', $proyectoId)->where('activo', true)
            ->orderBy('orden')->get();
    }

    private function resultados(int $proyectoId): Collection
    {
        return DB::table('resultados')
            ->where('proyecto_id', $proyectoId)->where('activo', true)
            ->orderBy('orden')->get();
    }

    private function motivos(int $proyectoId): Collection
    {
        return DB::table('motivos_no_contacto')
            ->where('proyecto_id', $proyectoId)->where('activo', true)
            ->orderBy('orden')->get();
    }

    private function causas(int $proyectoId): Collection
    {
        return DB::table('causas_gestion')
            ->where('proyecto_id', $proyectoId)->where('activo', true)
            ->orderBy('orden')->get();
    }

    private function tiposPago(int $proyectoId): Collection
    {
        return DB::table('tipos_pago')
            ->where('proyecto_id', $proyectoId)->where('activo', true)
            ->orderBy('orden')->get();
    }

    private function nivelesEscalamiento(int $proyectoId): Collection
    {
        return DB::table('niveles_escalamiento')
            ->where('proyecto_id', $proyectoId)->where('activo', true)
            ->orderBy('orden')->get();
    }

    private function etapasEmbudo(int $proyectoId): Collection
    {
        return DB::table('etapas_embudo')
            ->where('proyecto_id', $proyectoId)->where('activo', true)
            ->orderBy('orden')->get();
    }

    private function tiposAccionServicio(int $proyectoId): Collection
    {
        return DB::table('tipos_accion_servicio')
            ->where('proyecto_id', $proyectoId)->where('activo', true)
            ->orderBy('orden')->get();
    }

    private function contactos(int $proyectoId): Collection
    {
        return DB::table('contactos')
            ->where('proyecto_id', $proyectoId)
            ->where('persona_id', $this->personaId)
            ->where('activo', true)
            ->orderByDesc('es_principal')
            ->orderBy('tipo')
            ->get();
    }
}
