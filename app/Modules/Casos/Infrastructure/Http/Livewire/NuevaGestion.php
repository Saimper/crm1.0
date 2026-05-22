<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Http\Livewire;

use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use App\Modules\Cobranza\Domain\ValueObjects\DatosPromesaPago;
use App\Modules\Cobranza\Domain\ValueObjects\FechaPromesa;
use App\Modules\Cobranza\Domain\ValueObjects\MontoPromesa;
use App\Modules\Cx\Domain\ValueObjects\AccionComprometida;
use App\Modules\Cx\Domain\ValueObjects\DatosResolucionTicket;
use App\Modules\Cx\Domain\ValueObjects\FechaLimiteSla;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use App\Modules\Servicio\Domain\ValueObjects\DatosAccionServicio;
use App\Modules\Servicio\Domain\ValueObjects\DescripcionAccion;
use App\Modules\Servicio\Domain\ValueObjects\FechaProgramada;
use App\Modules\Venta\Domain\ValueObjects\DatosCierreVenta;
use App\Modules\Venta\Domain\ValueObjects\FechaCierreEstimada;
use App\Modules\Venta\Domain\ValueObjects\MontoCierre;
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

    /**
     * Valores de campos personalizados ámbito `gestion × tipo_gestion`.
     * Se llenan inline al cambiar el tipo de gestión y se persisten en la
     * misma transacción que la gestión recién creada.
     *
     * @var array<string, mixed>
     */
    public array $valoresCamposGestion = [];

    /**
     * Valores de campos personalizados ámbito `caso × cartera`.
     * Se cargan al montar el componente y se persisten al guardar la gestión.
     *
     * @var array<string, mixed>
     */
    public array $valoresCamposCaso = [];

    public function mount(int $casoId, int $personaId, string $tipoCaso): void
    {
        $this->casoId = $casoId;
        $this->personaId = $personaId;
        $this->tipoCaso = $tipoCaso;
    }

    public function updatedTipoGestionId(mixed $value): void
    {
        // Reset de valores capturados — los campos cambian con el tipo seleccionado.
        $this->valoresCamposGestion = [];
    }

    public function guardar(RegistrarGestion $useCase, ServicioCamposPersonalizados $servicioCampos): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $reglas = [
            'canalId' => ['required', 'integer'],
            'tipoGestionId' => ['required', 'integer'],
            'resultadoId' => ['required', 'integer'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];

        $resultado = $this->resultadoSeleccionado($proyectoId);
        if ($resultado === null) {
            $this->addError('resultadoId', 'Selecciona un resultado válido.');

            return;
        }

        if ((bool) $resultado->requiere_causa) {
            $reglas['causaId'] = ['required', 'integer'];
        }
        $esCobranzaConPromesa = $this->tipoCaso === 'cobranza' && (bool) $resultado->requiere_compromiso;
        $esCxConResolucion = $this->tipoCaso === 'ticket_cx' && (bool) $resultado->requiere_compromiso;
        $esVentaConCierre = $this->tipoCaso === 'lead_venta' && (bool) $resultado->requiere_compromiso;
        $esServicioConAccion = $this->tipoCaso === 'servicio' && (bool) $resultado->requiere_compromiso;
        if ($esCobranzaConPromesa) {
            $reglas['promesaMonto'] = ['required', 'regex:/^\d+(\.\d{1,2})?$/'];
            $reglas['promesaFecha'] = ['required', 'date'];
        }
        if ($esCxConResolucion) {
            $reglas['resolucionAccion'] = ['required', 'string', 'max:500'];
            $reglas['resolucionFechaLimite'] = ['required', 'date'];
        }
        if ($esVentaConCierre) {
            $reglas['cierreMonto'] = ['required', 'regex:/^\d+(\.\d{1,2})?$/'];
            $reglas['cierreFechaEstimada'] = ['required', 'date'];
        }
        if ($esServicioConAccion) {
            $reglas['accionDescripcion'] = ['required', 'string', 'max:500'];
            $reglas['accionFechaProgramada'] = ['required', 'date'];
        }

        $this->validate($reglas);

        try {
            $datosCompromiso = null;
            if ($esCobranzaConPromesa) {
                $datosCompromiso = new DatosPromesaPago(
                    monto: new MontoPromesa((string) $this->promesaMonto, 'USD'),
                    fechaVencimiento: new FechaPromesa(new DateTimeImmutable((string) $this->promesaFecha)),
                    tipoPagoId: $this->promesaTipoPagoId,
                );
            } elseif ($esCxConResolucion) {
                $datosCompromiso = new DatosResolucionTicket(
                    accion: new AccionComprometida((string) $this->resolucionAccion),
                    fechaLimite: new FechaLimiteSla(new DateTimeImmutable((string) $this->resolucionFechaLimite)),
                    nivelEscalamientoId: $this->resolucionNivelEscalamientoId,
                );
            } elseif ($esVentaConCierre) {
                $datosCompromiso = new DatosCierreVenta(
                    monto: new MontoCierre((string) $this->cierreMonto, 'USD'),
                    fechaEstimada: new FechaCierreEstimada(new DateTimeImmutable((string) $this->cierreFechaEstimada)),
                    etapaEmbudoId: $this->cierreEtapaEmbudoId,
                );
            } elseif ($esServicioConAccion) {
                $datosCompromiso = new DatosAccionServicio(
                    descripcion: new DescripcionAccion((string) $this->accionDescripcion),
                    fechaProgramada: new FechaProgramada(new DateTimeImmutable((string) $this->accionFechaProgramada)),
                    tipoAccionServicioId: $this->accionTipoAccionId,
                    tecnicoAsignado: $this->accionTecnicoAsignado !== '' ? $this->accionTecnicoAsignado : null,
                );
            }

            $output = $useCase->execute(new RegistrarGestionInput(
                publicId: (string) Str::ulid(),
                proyectoId: $proyectoId,
                casoId: $this->casoId,
                personaId: $this->personaId,
                contactoId: $this->contactoId,
                canalId: (int) $this->canalId,
                tipoGestionId: (int) $this->tipoGestionId,
                resultadoId: (int) $this->resultadoId,
                motivoNoContactoId: $this->motivoNoContactoId,
                causaId: $this->causaId,
                usuarioId: (int) auth()->id(),
                notas: $this->notas !== '' ? $this->notas : null,
                duracion: $this->duracionSegundos ? new DuracionSegundos((int) $this->duracionSegundos) : null,
                creadaEn: new DateTimeImmutable,
                datosCompromiso: $datosCompromiso,
            ));

            // Persistir valores de campos personalizados ámbito `gestion × tipo_gestion`
            // si el tipo seleccionado tiene definiciones. La validación dentro de
            // `guardarValores` lanza si algún `obligatorio` viene vacío o el formato no calza.
            $servicioCampos->guardarValores(
                proyectoId: $proyectoId,
                ambito: AmbitoCampo::GESTION,
                ambitoId: (int) $this->tipoGestionId,
                entidadId: $output->id,
                valoresPorCodigo: $this->valoresCamposGestion,
            );

            // Persistir valores de campos personalizados ámbito `caso × cartera`
            // cuando el usuario modificó algún valor durante la gestión.
            $carteraId = (int) DB::table('casos')->where('id', $this->casoId)->value('cartera_id');
            $servicioCampos->guardarValores(
                proyectoId: $proyectoId,
                ambito: AmbitoCampo::CASO,
                ambitoId: $carteraId,
                entidadId: $this->casoId,
                valoresPorCodigo: $this->valoresCamposCaso,
            );
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
            'valoresCamposGestion',
        ]);

        $this->dispatch('gestion-registrada');
        session()->flash('nueva-gestion-ok', 'Gestión registrada.');
    }

    public function render(ServicioCamposPersonalizados $servicioCampos): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $resultadoActual = $this->resultadoSeleccionado($proyectoId);

        $camposGestion = $this->tipoGestionId !== null
            ? $servicioCampos->campos($proyectoId, AmbitoCampo::GESTION, (int) $this->tipoGestionId)
            : collect();

        $carteraId = (int) DB::table('casos')->where('id', $this->casoId)->value('cartera_id');
        $camposCaso = $servicioCampos->campos($proyectoId, AmbitoCampo::CASO, $carteraId);
        if ($this->valoresCamposCaso === [] && $camposCaso->isNotEmpty()) {
            $this->valoresCamposCaso = $this->cargarValoresCamposCaso($proyectoId, $carteraId);
        }

        return view('casos::livewire.nueva-gestion', [
            'canales' => $this->canales(),
            'tiposGestion' => $this->tiposGestion($proyectoId),
            'resultados' => $this->resultados($proyectoId),
            'motivos' => $this->motivos($proyectoId),
            'causas' => $this->causas($proyectoId),
            'tiposPago' => $this->tipoCaso === 'cobranza' ? $this->tiposPago($proyectoId) : collect(),
            'nivelesEscalamiento' => $this->tipoCaso === 'ticket_cx' ? $this->nivelesEscalamiento($proyectoId) : collect(),
            'etapasEmbudo' => $this->tipoCaso === 'lead_venta' ? $this->etapasEmbudo($proyectoId) : collect(),
            'tiposAccionServicio' => $this->tipoCaso === 'servicio' ? $this->tiposAccionServicio($proyectoId) : collect(),
            'contactos' => $this->contactos($proyectoId),
            'requiereCausa' => $resultadoActual ? (bool) $resultadoActual->requiere_causa : false,
            'requiereCompromiso' => $resultadoActual ? (bool) $resultadoActual->requiere_compromiso : false,
            'esContactoEfectivo' => $resultadoActual ? (bool) $resultadoActual->es_contacto_efectivo : false,
            'camposGestion' => $camposGestion,
            'camposCaso' => $camposCaso,
        ]);
    }

    private function cargarValoresCamposCaso(int $proyectoId, int $carteraId): array
    {
        $filas = DB::table('valores_campo_personalizado as v')
            ->join('campos_personalizados as c', 'c.id', '=', 'v.campo_personalizado_id')
            ->where('v.entidad_id', $this->casoId)
            ->where('c.proyecto_id', $proyectoId)
            ->where('c.ambito', AmbitoCampo::CASO->value)
            ->where('c.ambito_id', $carteraId)
            ->select(['c.codigo', 'c.tipo', 'v.*'])
            ->get();

        $valores = [];
        foreach ($filas as $f) {
            $valores[(string) $f->codigo] = $this->leerValorCampo($f, (string) $f->tipo);
        }

        return $valores;
    }

    private function leerValorCampo(object $fila, string $tipo): mixed
    {
        return match ($tipo) {
            'texto_corto' => $fila->valor_texto_corto,
            'texto_largo' => $fila->valor_texto_largo,
            'numero_entero' => $fila->valor_numero_entero === null ? null : (int) $fila->valor_numero_entero,
            'numero_decimal' => $fila->valor_numero_decimal,
            'fecha' => $fila->valor_fecha,
            'fecha_hora' => $fila->valor_fecha_hora,
            'booleano' => $fila->valor_booleano === null ? null : (bool) $fila->valor_booleano,
            'moneda' => $fila->valor_moneda_monto,
            default => null,
        };
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
