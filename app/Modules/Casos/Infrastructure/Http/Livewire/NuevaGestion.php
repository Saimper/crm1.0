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
use App\Modules\Gestiones\Domain\Contracts\ConsultaTiposPorCanal;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use App\Modules\Integracion\Infrastructure\Http\Concerns\EmiteWritebackFicha;
use App\Modules\Servicio\Domain\ValueObjects\DatosAccionServicio;
use App\Modules\Servicio\Domain\ValueObjects\DescripcionAccion;
use App\Modules\Servicio\Domain\ValueObjects\FechaProgramada;
use App\Modules\Tenancy\Domain\Contracts\RegionalConfiguration;
use App\Modules\Venta\Domain\ValueObjects\DatosCierreVenta;
use App\Modules\Venta\Domain\ValueObjects\FechaCierreEstimada;
use App\Modules\Venta\Domain\ValueObjects\MontoCierre;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Formulario de Nueva Gestión embebido en la Vista de Trabajo.
 * Campos abstractos comunes (canal, tipo, resultado, notas, duración) + slot cobranza
 * (monto + fecha + tipo de pago) cuando `tipo_caso = cobranza` y el resultado exige compromiso.
 */
final class NuevaGestion extends Component
{
    use EmiteWritebackFicha;

    #[Locked]
    public int $casoId = 0;

    #[Locked]
    public int $personaId = 0;

    #[Locked]
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

    public function mount(int $casoId, int $personaId, string $tipoCaso): void
    {
        $this->casoId = $casoId;
        $this->personaId = $personaId;
        $this->tipoCaso = $tipoCaso;
    }

    /**
     * Al cambiar el tipo cambia la lista de resultados admitidos, así que el
     * resultado elegido puede dejar de ser válido. Se limpia en vez de
     * arrastrarlo, y con él todo lo que cuelga: motivo, causa y los campos
     * personalizados del ámbito gestión, que también cambian con el tipo.
     */
    public function updatedCanalId(): void
    {
        $this->tipoGestionId = null;
        $this->updatedTipoGestionId(null);
    }

    public function updatedResultadoId(): void
    {
        $this->motivoNoContactoId = null;
        $this->causaId = null;
    }

    public function updatedTipoGestionId(mixed $value): void
    {
        $this->resultadoId = null;
        $this->motivoNoContactoId = null;
        $this->causaId = null;
        $this->valoresCamposGestion = [];
    }

    public function guardar(RegistrarGestion $useCase, ServicioCamposPersonalizados $servicioCampos): void
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        // La única puerta hasta ahora era `can:casos.ver` en la ruta, que el
        // AUDITOR tiene: podía registrar gestiones. El permiso de escritura
        // existe desde F22 y nadie lo comprobaba en el camino de escritura.
        if (auth()->user()?->tienePermiso('gestiones.crear', $proyectoId) !== true) {
            abort(403, 'No tienes permiso para registrar gestiones en este proyecto.');
        }

        $reglas = [
            'canalId' => ['required', 'integer', Rule::exists('canal_proyecto', 'canal_id')
                ->where('proyecto_id', $proyectoId)
                ->where('activo', true)],
            'tipoGestionId' => ['required', 'integer', Rule::in(app(ConsultaTiposPorCanal::class)->idsAdmitidos($proyectoId, (int) $this->canalId))],
            'resultadoId' => ['required', 'integer', Rule::exists('resultados', 'id')
                ->where('proyecto_id', $proyectoId)
                ->where('activo', true)],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];

        $resultado = $this->resultadoSeleccionado($proyectoId);
        if ($resultado === null) {
            $this->addError('resultadoId', 'Selecciona un resultado válido.');

            return;
        }

        if ((bool) $resultado->es_no_contactado) {
            $reglas['motivoNoContactoId'] = ['nullable', 'integer', Rule::exists('motivos_no_contacto', 'id')->where('proyecto_id', $proyectoId)->where('activo', true)];
        } else {
            $this->motivoNoContactoId = null;
        }

        if ((bool) $resultado->requiere_causa) {
            $reglas['causaId'] = ['required', 'integer'];
        }
        $esCobranzaConPromesa = $this->tipoCaso === 'cobranza' && (bool) $resultado->requiere_compromiso;
        $esCxConResolucion = $this->tipoCaso === 'ticket_cx' && (bool) $resultado->requiere_compromiso;
        $esVentaConCierre = $this->tipoCaso === 'lead_venta' && (bool) $resultado->requiere_compromiso;
        $esServicioConAccion = $this->tipoCaso === 'servicio' && (bool) $resultado->requiere_compromiso;
        if ($esCobranzaConPromesa) {
            $reglas['promesaMonto'] = ['required', 'string'];
            $reglas['promesaFecha'] = ['required', 'date'];
        }
        if ($esCxConResolucion) {
            $reglas['resolucionAccion'] = ['required', 'string', 'max:500'];
            $reglas['resolucionFechaLimite'] = ['required', 'date'];
        }
        if ($esVentaConCierre) {
            $reglas['cierreMonto'] = ['required', 'string'];
            $reglas['cierreFechaEstimada'] = ['required', 'date'];
        }
        if ($esServicioConAccion) {
            $reglas['accionDescripcion'] = ['required', 'string', 'max:500'];
            $reglas['accionFechaProgramada'] = ['required', 'date'];
        }

        $this->validate($reglas);

        try {
            $regional = app(RegionalConfiguration::class)->forProject($proyectoId);
            $currencyTable = $this->tipoCaso === 'cobranza' ? 'casos_cobranza' : ($this->tipoCaso === 'lead_venta' ? 'casos_lead_venta' : null);
            $currency = $currencyTable === null ? $regional->currency : (DB::table($currencyTable)->where('proyecto_id', $proyectoId)->where('caso_id', $this->casoId)->value('moneda') ?? $regional->currency);
            $datosCompromiso = null;
            if ($esCobranzaConPromesa) {
                $datosCompromiso = new DatosPromesaPago(
                    monto: new MontoPromesa($regional->parseNumber((string) $this->promesaMonto), (string) $currency),
                    fechaVencimiento: new FechaPromesa(new DateTimeImmutable((string) $this->promesaFecha)),
                    tipoPagoId: $this->promesaTipoPagoId,
                );
            } elseif ($esCxConResolucion) {
                $datosCompromiso = new DatosResolucionTicket(
                    accion: new AccionComprometida((string) $this->resolucionAccion),
                    fechaLimite: new FechaLimiteSla($regional->parseDate((string) $this->resolucionFechaLimite, true)),
                    nivelEscalamientoId: $this->resolucionNivelEscalamientoId,
                );
            } elseif ($esVentaConCierre) {
                $datosCompromiso = new DatosCierreVenta(
                    monto: new MontoCierre($regional->parseNumber((string) $this->cierreMonto), (string) $currency),
                    fechaEstimada: new FechaCierreEstimada(new DateTimeImmutable((string) $this->cierreFechaEstimada)),
                    etapaEmbudoId: $this->cierreEtapaEmbudoId,
                );
            } elseif ($esServicioConAccion) {
                $datosCompromiso = new DatosAccionServicio(
                    descripcion: new DescripcionAccion((string) $this->accionDescripcion),
                    fechaProgramada: new FechaProgramada($regional->parseDate((string) $this->accionFechaProgramada, true)),
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

            // Los campos del caso NO se escriben desde aquí. Se leen en la
            // Vista de Trabajo y se editan en «Editar caso», que es la pantalla
            // dueña del dato. Tenerlos también en este formulario era una
            // segunda superficie de escritura sobre lo mismo (§13.3), y era por
            // donde se borraban: el componente enviaba los 34 campos en cada
            // gestión, incluidos los que no sabía leer.
        } catch (Throwable $e) {
            $this->addError('general', $e->getMessage());

            return;
        }

        // Writeback CRM→ViciDial: sincroniza al lead activo SOLO los campos del
        // caso (no los de la gestión). Serializa el estado ya persistido para
        // resolver selección→etiqueta y moneda→monto, y adjunta las etiquetas
        // para el emparejamiento por label en el wrapper. Best-effort: ningún
        // fallo aquí debe abortar el flujo post-guardado (igual que EditarCaso).
        try {
            $carteraId = (int) DB::table('casos')->where('id', $this->casoId)->value('cartera_id');
            $valores = $servicioCampos->valoresSerializadosParaWriteback(
                $proyectoId,
                AmbitoCampo::CASO,
                $carteraId,
                $this->casoId,
            );
            if ($valores !== []) {
                $this->emitirWritebackFicha([
                    'custom' => $valores,
                    'custom_labels' => $servicioCampos->etiquetasDeCampos(
                        $proyectoId,
                        AmbitoCampo::CASO,
                        $carteraId,
                        array_keys($valores),
                    ),
                ], $this->personaPublicIdDelCaso());
            }
        } catch (Throwable $e) {
            Log::warning('lead-writeback: fallo al serializar/emitir', [
                'caso_id' => $this->casoId,
                'error' => $e->getMessage(),
            ]);
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

        return view('casos::livewire.nueva-gestion', [
            'canales' => $this->canales($proyectoId),
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
            'esNoContactado' => $resultadoActual ? (bool) $resultadoActual->es_no_contactado : false,
            'camposGestion' => $camposGestion,
            'plantillasNota' => $this->plantillasNota($proyectoId),
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

    /**
     * Los canales que este proyecto usa, con el nombre que les da y en su orden.
     *
     * `canales` sigue siendo el catálogo global; `canal_proyecto` dice qué hace
     * cada proyecto con él. La etiqueta del proyecto gana sobre la global
     * cuando existe.
     */
    private function canales(int $proyectoId): Collection
    {
        return DB::table('canal_proyecto as cp')
            ->join('canales as c', 'c.id', '=', 'cp.canal_id')
            ->where('cp.proyecto_id', $proyectoId)
            ->where('cp.activo', true)
            ->where('c.activo', true)
            ->orderBy('cp.orden')
            ->orderBy('c.id')
            ->get([
                'c.id',
                'c.codigo',
                DB::raw('COALESCE(cp.etiqueta, c.nombre) as nombre'),
                'cp.requiere_duracion',
                'cp.permite_adjunto',
            ]);
    }

    private function tiposGestion(int $proyectoId): Collection
    {
        return DB::table('tipos_gestion')
            ->whereIn('id', app(ConsultaTiposPorCanal::class)->idsAdmitidos($proyectoId, (int) $this->canalId))
            ->where('proyecto_id', $proyectoId)->where('activo', true)
            ->orderBy('orden')->get();
    }

    /**
     * Los resultados que admite el tipo de gestión elegido.
     *
     * Sin tipo elegido no hay lista: el selector sale deshabilitado en vez de
     * ofrecer los nueve resultados del proyecto, que era lo que hacía que
     * «Promesa de pago fraccionado» apareciera bajo «No contactado».
     *
     * Arranque en abierto POR TIPO: un tipo sin ninguna combinación declarada
     * admite todos. Con la regla al revés, los proyectos que no lo tienen
     * configurado —hoy, todos— se quedarían sin poder registrar una gestión.
     */
    private function resultados(int $proyectoId): Collection
    {
        if ($this->tipoGestionId === null) {
            return collect();
        }

        $declarados = DB::table('resultado_tipo_gestion')
            ->where('proyecto_id', $proyectoId)
            ->where('tipo_gestion_id', (int) $this->tipoGestionId)
            ->count();

        $query = DB::table('resultados as r')
            ->where('r.proyecto_id', $proyectoId)
            ->where('r.activo', true);

        if ($declarados > 0) {
            $query->join('resultado_tipo_gestion as rtg', function ($join): void {
                $join->on('rtg.resultado_id', '=', 'r.id')
                    ->where('rtg.tipo_gestion_id', (int) $this->tipoGestionId);
            })->orderBy('rtg.orden');
        }

        return $query->orderBy('r.orden')->get(['r.*']);
    }

    /**
     * Frases hechas para las notas: las generales del proyecto más las del
     * resultado elegido, que son las que de verdad ahorran escribir.
     *
     * @return Collection<int, \stdClass>
     */
    private function plantillasNota(int $proyectoId): Collection
    {
        return DB::table('plantillas_nota')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->where(function ($q): void {
                $q->whereNull('resultado_id');

                if ($this->resultadoId !== null) {
                    $q->orWhere('resultado_id', (int) $this->resultadoId);
                }
            })
            ->orderByRaw('resultado_id is null')
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'etiqueta', 'texto']);
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

    /**
     * El writeback se ancla a la persona que abrió la llamada; el componente
     * sólo conoce el id interno, así que se resuelve el público al emitir.
     */
    private function personaPublicIdDelCaso(): ?string
    {
        $publicId = DB::table('personas')->where('id', $this->personaId)->value('public_id');

        return is_string($publicId) && $publicId !== '' ? $publicId : null;
    }
}
