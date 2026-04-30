<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire;

use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

/**
 * Componente genérico para renderizar y persistir valores de campos personalizados.
 * Recibe proyecto/ámbito/ámbito_id/entidad_id y descubre los campos aplicables.
 * Las reglas (obligatorio, longitud, min, max) las valida el dominio en `guardarValores`.
 *
 * Autorización: auto-detecta si el usuario tiene `campos.editar` en el proyecto y entra en
 * modo solo-lectura (`bloqueado = true`) si no lo tiene. El permiso se re-valida en `guardar()`
 * como defensa en profundidad — nunca confiar solo en el estado del componente.
 */
final class FormularioCamposPersonalizados extends Component
{
    public int $proyectoId = 0;

    public string $ambito = '';

    public int $ambitoId = 0;

    public int $entidadId = 0;

    /** @var array<string, mixed> */
    public array $valores = [];

    public bool $bloqueado = false;

    public function mount(int $proyectoId, string $ambito, int $ambitoId, int $entidadId, ?bool $bloqueado = null): void
    {
        $this->proyectoId = $proyectoId;
        $this->ambito = $ambito;
        $this->ambitoId = $ambitoId;
        $this->entidadId = $entidadId;
        $this->bloqueado = $bloqueado ?? ! $this->puedeEditar();

        $this->cargarValores();
    }

    public function guardar(ServicioCamposPersonalizados $servicio): void
    {
        // Defensa en profundidad: re-valida permiso en cada guardar, independiente del estado.
        if (! $this->puedeEditar()) {
            Log::warning('Bloqueo guardar campos personalizados', [
                'usuario_id' => auth()->id(),
                'proyecto_id' => $this->proyectoId,
                'ambito' => $this->ambito,
                'ambito_id' => $this->ambitoId,
                'entidad_id' => $this->entidadId,
            ]);
            abort(403, 'No tienes permiso para editar campos personalizados en este proyecto.');
        }
        if ($this->bloqueado) {
            return;
        }

        try {
            $servicio->guardarValores(
                $this->proyectoId,
                AmbitoCampo::from($this->ambito),
                $this->ambitoId,
                $this->entidadId,
                $this->valores,
            );
        } catch (Throwable $e) {
            $this->addError('general', $e->getMessage());

            return;
        }

        session()->flash('campos-ok', 'Campos guardados.');
        $this->dispatch('campos-personalizados-guardados');
    }

    public function render(): View
    {
        $servicio = app(ServicioCamposPersonalizados::class);
        $campos = $servicio->campos(
            $this->proyectoId,
            AmbitoCampo::from($this->ambito),
            $this->ambitoId,
        );

        return view('campos_personalizados::livewire.formulario-campos-personalizados', [
            'campos' => $campos,
        ]);
    }

    private function puedeEditar(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return (bool) $user->tienePermiso('campos.editar', $this->proyectoId);
    }

    private function cargarValores(): void
    {
        $filas = DB::table('valores_campo_personalizado as v')
            ->join('campos_personalizados as c', 'c.id', '=', 'v.campo_personalizado_id')
            ->where('v.entidad_id', $this->entidadId)
            ->where('c.proyecto_id', $this->proyectoId)
            ->where('c.ambito', $this->ambito)
            ->where('c.ambito_id', $this->ambitoId)
            ->select(['c.codigo', 'c.tipo', 'v.*'])
            ->get();

        foreach ($filas as $f) {
            $this->valores[(string) $f->codigo] = $this->leerValor($f, (string) $f->tipo);
        }
    }

    private function leerValor(object $fila, string $tipo): mixed
    {
        return match ($tipo) {
            'texto_corto' => $fila->valor_texto_corto,
            'texto_largo' => $fila->valor_texto_largo,
            'numero_entero' => $fila->valor_numero_entero === null ? null : (int) $fila->valor_numero_entero,
            'numero_decimal' => $fila->valor_numero_decimal,
            'fecha' => $fila->valor_fecha,
            'fecha_hora' => $fila->valor_fecha_hora,
            'booleano' => $fila->valor_booleano === null ? null : (bool) $fila->valor_booleano,
            'seleccion_unica' => $fila->valor_opcion_id === null ? null : (int) $fila->valor_opcion_id,
            'seleccion_multiple' => $fila->valor_opciones_ids === null ? [] : (array) json_decode((string) $fila->valor_opciones_ids, true),
            'moneda' => $fila->valor_moneda_monto,
            default => null,
        };
    }
}
