<?php

declare(strict_types=1);

namespace App\Modules\EntidadesConfigurables\Infrastructure\Http\Livewire\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Reenvía al wrapper (ViciDial) los valores de un registro de entidad
 * configurable relacionada con persona, al guardarlo. El relay JS solo postea
 * si el CRM está embebido y con wrapper-origin firmado; el wrapper solo escribe
 * los campos que el supervisor haya mapeado y solo si el pivote coincide con el
 * lead en llamada.
 *
 * Clave de cambio: `<entidad_codigo>.<campo_codigo>` → el wrapper la combina con
 * `tipo='persona_ent'` para formar el source `persona_ent.<entidad>.<campo>`,
 * que es exactamente lo que enumera `ListarCamposDisponibles`.
 *
 * Limitación conocida: una persona puede tener N registros de la misma entidad
 * (1:N), pero el lead de ViciDial es plano — gana el último registro editado.
 * El supervisor debe mapear solo entidades-persona con sentido 1:1.
 */
trait EmiteCrmSyncEntidadPersona
{
    /**
     * @param  array<string, mixed>  $valores  codigo_campo => valor
     */
    private function emitirCrmSyncEntidadPersona(int $entidadId, ?int $personaId, array $valores): void
    {
        if ($personaId === null || $valores === []) {
            return;
        }

        $entidadCodigo = DB::table('entidades_configurables')
            ->where('id', $entidadId)
            ->value('codigo');
        $identificacion = DB::table('personas')
            ->where('id', $personaId)
            ->value('identificacion');

        if (! is_string($entidadCodigo) || $entidadCodigo === ''
            || ! is_string($identificacion) || $identificacion === '') {
            return;
        }

        $cambios = [];
        foreach ($valores as $codigo => $valor) {
            $cambios[$entidadCodigo.'.'.(string) $codigo] = $this->valorEntidadComoTexto($valor);
        }

        $this->dispatch('crm-sync', tipo: 'persona_ent', cambios: $cambios, pivote: [
            'identificacion' => $identificacion,
        ]);
    }

    private function valorEntidadComoTexto(mixed $valor): string
    {
        if (is_array($valor)) {
            // Moneda viaja como ['monto' => ..., 'moneda' => ...]; el lead solo
            // necesita el monto. El resto (selección múltiple) va como CSV.
            if (array_key_exists('monto', $valor)) {
                return (string) ($valor['monto'] ?? '');
            }

            return implode(',', array_map(static fn ($v): string => (string) $v, $valor));
        }

        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }

        return (string) ($valor ?? '');
    }
}
