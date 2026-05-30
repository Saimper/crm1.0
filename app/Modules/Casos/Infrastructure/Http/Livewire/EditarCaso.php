<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Http\Livewire;

use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use App\Modules\Integracion\Infrastructure\Http\Concerns\EmiteWritebackFicha;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

/**
 * Edita datos descriptivos comunes de un caso del proyecto activo.
 *
 * Limitaciones intencionales (Domain del núcleo intacto §15.6):
 *   - tipo_caso INMUTABLE (CTI no se cambia).
 *   - estado_caso_id INMUTABLE (transiciones se hacen vía gestiones, no aquí).
 *   - identificadores únicos del CTI (numero_prestamo, codigo_ticket, codigo_lead,
 *     codigo_servicio) INMUTABLES en F34B.
 *
 * F36-Q: los campos descriptivos del CTI (saldos, asunto, valor, dirección, etc.)
 * ya NO se editan desde aquí. La variabilidad por mandante/cartera vive en campos
 * personalizados ámbito `caso × cartera`, definidos por ADMIN_GLOBAL en el wizard.
 *
 * Edita aquí: cartera, prioridad, fecha_ingreso + valores de campos personalizados
 * del caso. Un solo botón "Guardar cambios" persiste todo en una transacción.
 *
 * Permiso: casos.editar (SUPERVISOR + GESTOR + ADMIN_GLOBAL por defecto).
 */
final class EditarCaso extends Component
{
    use EmiteWritebackFicha;

    public string $casoPublicId = '';

    public ?int $casoId = null;

    public string $tipoCaso = '';

    public string $personaPublicId = '';

    public string $carteraId = '';

    public int $prioridad = 0;

    public string $fechaIngreso = '';

    /**
     * Valores de campos personalizados ámbito `caso × cartera`. Se persisten
     * junto al UPDATE del caso, en la misma transacción.
     *
     * @var array<string, mixed>
     */
    public array $valoresCamposCaso = [];

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

        $this->cargarValoresCamposCaso();
    }

    public function updatedCarteraId(mixed $value): void
    {
        // Cambia la cartera → cambian las definiciones de campos. Reset + recargar.
        $this->valoresCamposCaso = [];
        $this->cargarValoresCamposCaso();
    }

    public function guardar(ServicioCamposPersonalizados $servicioCampos): void
    {
        $proyecto = app('tenancy.proyecto_activo');
        if (auth()->user()?->tienePermiso('casos.editar', (int) $proyecto->id) !== true) {
            abort(403, 'No tienes permiso para editar casos en este proyecto.');
        }

        if ($this->casoId === null) {
            return;
        }

        $this->validate([
            'carteraId' => ['required', 'integer'],
            'prioridad' => ['integer', 'min:0', 'max:1000'],
            'fechaIngreso' => ['required', 'date'],
        ]);

        $proyectoId = (int) $proyecto->id;
        $ahora = Carbon::now();

        DB::transaction(function () use ($proyectoId, $ahora, $servicioCampos): void {
            DB::table('casos')
                ->where('id', $this->casoId)
                ->where('proyecto_id', $proyectoId)
                ->update([
                    'cartera_id' => (int) $this->carteraId,
                    'prioridad' => $this->prioridad,
                    'fecha_ingreso' => $this->fechaIngreso,
                    'actualizada_en' => $ahora,
                ]);

            $servicioCampos->guardarValores(
                proyectoId: $proyectoId,
                ambito: AmbitoCampo::CASO,
                ambitoId: (int) $this->carteraId,
                entidadId: (int) $this->casoId,
                valoresPorCodigo: $this->valoresCamposCaso,
            );
        });

        // Writeback CRM→ViciDial: serializa el estado ya persistido (resuelve
        // selección→etiqueta y moneda→monto). Las claves son el `codigo` del campo;
        // se adjunta la etiqueta para que el wrapper pueda emparejar por label.
        // Best-effort: ningún fallo de serialización/emisión debe abortar el guardado.
        try {
            $valores = $servicioCampos->valoresSerializadosParaWriteback(
                $proyectoId,
                AmbitoCampo::CASO,
                (int) $this->carteraId,
                (int) $this->casoId,
            );
            if ($valores !== []) {
                $this->emitirWritebackFicha([
                    'custom' => $valores,
                    'custom_labels' => $servicioCampos->etiquetasDeCampos(
                        $proyectoId,
                        AmbitoCampo::CASO,
                        (int) $this->carteraId,
                        array_keys($valores),
                    ),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('lead-writeback: fallo al serializar/emitir', [
                'caso_id' => $this->casoId,
                'error' => $e->getMessage(),
            ]);
        }

        session()->flash('caso_editado', 'Caso actualizado.');

        $this->redirectRoute('proyectos.trabajo', [
            'proyecto_id' => $proyectoId,
            'persona' => $this->personaPublicId,
            'caso' => $this->casoPublicId,
        ], navigate: true);
    }

    public function render(ServicioCamposPersonalizados $servicioCampos): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $carteras = DB::table('carteras')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $camposCaso = $this->carteraId !== ''
            ? $servicioCampos->campos($proyectoId, AmbitoCampo::CASO, (int) $this->carteraId)
            : collect();

        return view('casos::livewire.editar-caso', [
            'carteras' => $carteras,
            'camposCaso' => $camposCaso,
        ]);
    }

    private function cargarValoresCamposCaso(): void
    {
        if ($this->casoId === null || $this->carteraId === '') {
            return;
        }

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $filas = DB::table('valores_campo_personalizado as v')
            ->join('campos_personalizados as c', 'c.id', '=', 'v.campo_personalizado_id')
            ->where('v.entidad_id', $this->casoId)
            ->where('c.proyecto_id', $proyectoId)
            ->where('c.ambito', AmbitoCampo::CASO->value)
            ->where('c.ambito_id', (int) $this->carteraId)
            ->select(['c.codigo', 'c.tipo', 'v.*'])
            ->get();

        foreach ($filas as $f) {
            $this->valoresCamposCaso[(string) $f->codigo] = $this->leerValorCampo($f, (string) $f->tipo);
        }
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
}
