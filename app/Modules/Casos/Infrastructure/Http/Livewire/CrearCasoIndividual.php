<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Http\Livewire;

use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use App\Modules\Cobranza\Domain\Exceptions\NumeroPrestamoYaRegistrado;
use App\Modules\Cx\Application\DTOs\RegistrarCasoTicketCxInput;
use App\Modules\Cx\Application\UseCases\RegistrarCasoTicketCx;
use App\Modules\Cx\Domain\Exceptions\CodigoTicketYaRegistrado;
use App\Modules\Servicio\Application\DTOs\RegistrarCasoServicioInput;
use App\Modules\Servicio\Application\UseCases\RegistrarCasoServicio;
use App\Modules\Servicio\Domain\Exceptions\CodigoServicioYaRegistrado;
use App\Modules\Venta\Application\DTOs\RegistrarCasoLeadVentaInput;
use App\Modules\Venta\Application\UseCases\RegistrarCasoLeadVenta;
use App\Modules\Venta\Domain\Exceptions\CodigoLeadYaRegistrado;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * F35-D: form Crear Caso minimal + Campos Personalizados.
 *
 * El admin del proyecto define qué campos pedir vía Campos Personalizados §7
 * (ámbito caso×cartera). El form deja solo lo invariante:
 *   - cartera (req)
 *   - persona (preset desde URL)
 *   - identificador único del CTI según tipo (numero_prestamo / codigo_ticket / ...)
 *   - prioridad (opcional)
 *   - render dinámico de Campos Personalizados de la cartera seleccionada
 *
 * Estado inicial del caso = primer estado activo del proyecto. Fecha ingreso = hoy.
 */
final class CrearCasoIndividual extends Component
{
    #[Url(as: 'persona', except: '')]
    public string $personaPublicId = '';

    public ?int $personaId = null;

    public string $tipoOperacion = '';

    public string $carteraId = '';

    public string $idUnico = '';

    public int $prioridad = 3;

    /** @var array<string, mixed> */
    public array $valoresCp = [];

    public function mount(): void
    {
        $proyecto = app('tenancy.proyecto_activo');
        $this->tipoOperacion = (string) $proyecto->tipo_operacion;

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

    public function updatedCarteraId(): void
    {
        // Cuando cambia la cartera, los campos personalizados aplicables cambian.
        $this->valoresCp = [];
    }

    public function guardar(
        RegistrarCasoCobranza $ucCobranza,
        RegistrarCasoTicketCx $ucCx,
        RegistrarCasoLeadVenta $ucVenta,
        RegistrarCasoServicio $ucServicio,
        ServicioCamposPersonalizados $servicioCp,
    ): void {
        if ($this->personaId === null) {
            $this->addError('personaPublicId', 'Persona no encontrada en el proyecto.');

            return;
        }

        $this->validate([
            'carteraId' => ['required', 'integer'],
            'idUnico' => ['required', 'string', 'max:80'],
            'prioridad' => ['integer', 'min:0', 'max:1000'],
        ], [], [
            'idUnico' => $this->etiquetaIdUnico(),
        ]);

        $proyecto = app('tenancy.proyecto_activo');
        $proyectoId = (int) $proyecto->id;

        $estadoCasoId = (int) DB::table('estados_caso')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('id')
            ->value('id');

        if ($estadoCasoId === 0) {
            $this->addError('general', 'El proyecto no tiene estados de caso configurados. Pide al administrador que configure al menos uno.');

            return;
        }

        $publicIdNuevoCaso = '';
        $casoIdNuevo = 0;
        $fechaIngreso = new DateTimeImmutable;

        try {
            DB::transaction(function () use (
                &$publicIdNuevoCaso,
                &$casoIdNuevo,
                $proyectoId,
                $estadoCasoId,
                $fechaIngreso,
                $ucCobranza,
                $ucCx,
                $ucVenta,
                $ucServicio,
                $servicioCp,
            ): void {
                $output = match ($this->tipoOperacion) {
                    'cobranza' => $ucCobranza->execute(new RegistrarCasoCobranzaInput(
                        proyectoId: $proyectoId,
                        carteraId: (int) $this->carteraId,
                        personaId: (int) $this->personaId,
                        estadoCasoId: $estadoCasoId,
                        fechaIngreso: $fechaIngreso,
                        prioridad: $this->prioridad,
                        numeroPrestamo: trim($this->idUnico),
                    )),
                    'cx' => $ucCx->execute(new RegistrarCasoTicketCxInput(
                        proyectoId: $proyectoId,
                        carteraId: (int) $this->carteraId,
                        personaId: (int) $this->personaId,
                        estadoCasoId: $estadoCasoId,
                        fechaIngreso: $fechaIngreso,
                        prioridad: $this->prioridad,
                        codigoTicket: trim($this->idUnico),
                    )),
                    'venta' => $ucVenta->execute(new RegistrarCasoLeadVentaInput(
                        proyectoId: $proyectoId,
                        carteraId: (int) $this->carteraId,
                        personaId: (int) $this->personaId,
                        estadoCasoId: $estadoCasoId,
                        fechaIngreso: $fechaIngreso,
                        prioridad: $this->prioridad,
                        codigoLead: trim($this->idUnico),
                    )),
                    'servicio' => $ucServicio->execute(new RegistrarCasoServicioInput(
                        proyectoId: $proyectoId,
                        carteraId: (int) $this->carteraId,
                        personaId: (int) $this->personaId,
                        estadoCasoId: $estadoCasoId,
                        fechaIngreso: $fechaIngreso,
                        prioridad: $this->prioridad,
                        codigoServicio: trim($this->idUnico),
                    )),
                    default => throw new InvalidArgumentException('Tipo de operación no soportado: '.$this->tipoOperacion),
                };

                $publicIdNuevoCaso = $output->publicId;
                $casoIdNuevo = $output->casoId;

                if ($this->valoresCp !== []) {
                    $servicioCp->guardarValores(
                        $proyectoId,
                        AmbitoCampo::CASO,
                        (int) $this->carteraId,
                        $casoIdNuevo,
                        $this->valoresCp,
                    );
                }
            });
        } catch (NumeroPrestamoYaRegistrado|CodigoTicketYaRegistrado|CodigoLeadYaRegistrado|CodigoServicioYaRegistrado $e) {
            $this->addError('idUnico', $e->getMessage());

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
            'proyecto_id' => $proyecto->id,
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

        $camposPersonalizados = collect();
        if ($this->carteraId !== '') {
            $servicio = app(ServicioCamposPersonalizados::class);
            $camposPersonalizados = $servicio->campos(
                $proyectoId,
                AmbitoCampo::CASO,
                (int) $this->carteraId,
            );
        }

        return view('casos::livewire.crear-caso-individual', [
            'persona' => $persona,
            'carteras' => $carteras,
            'camposPersonalizados' => $camposPersonalizados,
            'etiquetaIdUnico' => $this->etiquetaIdUnico(),
        ]);
    }

    private function etiquetaIdUnico(): string
    {
        return match ($this->tipoOperacion) {
            'cobranza' => 'Número de préstamo',
            'cx' => 'Código de ticket',
            'venta' => 'Código de lead',
            'servicio' => 'Código de servicio',
            default => 'Identificador del caso',
        };
    }
}
