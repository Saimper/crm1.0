<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Concerns;

use App\Modules\Integracion\Domain\Contracts\EmisorWritebackFicha;
use Illuminate\Support\Facades\Log;

/**
 * Concern para los Livewire de la ficha (EditarPersona, ListaContactos, EditarCaso,
 * NuevaGestion). Emite el writeback CRM→ViciDial tras un guardado exitoso, solo
 * cuando la sesión proviene de un handshake con lead activo.
 *
 * `crm_sync_ref` y `crm_mandante_id` se persisten juntos en el mismo handshake
 * (SsoHandshakeController), así que provienen siempre del mismo tenant aunque el
 * agente navegue entre proyectos. La comunicación inter-módulo se delega a la
 * interfaz `EmisorWritebackFicha` (§3). Best-effort: encola y no afecta el guardado.
 */
trait EmiteWritebackFicha
{
    /**
     * @param  array<string, array<string, mixed>>  $changes  grupos `persona`|`contacto`|`custom`
     * @param  string|null  $personaPublicId  persona cuya ficha se está editando
     */
    protected function emitirWritebackFicha(array $changes, ?string $personaPublicId = null): void
    {
        $syncRef = session('crm_sync_ref');
        $mandanteId = (int) session('crm_mandante_id');

        if (! is_string($syncRef) || $syncRef === '' || $mandanteId === 0 || $changes === []) {
            Log::warning('lead-writeback OMITIDO (no se emite)', [
                'tiene_sync_ref' => is_string($syncRef) && $syncRef !== '',
                'mandante_id' => $mandanteId,
                'grupos' => array_keys($changes),
                'sesion_id' => session()->getId(),
            ]);

            return;
        }

        if ($this->fichaAjenaAlLead($personaPublicId)) {
            Log::info('lead-writeback OMITIDO (ficha distinta a la de la llamada)', [
                'sync_ref' => substr($syncRef, 0, 8),
                'grupos' => array_keys($changes),
            ]);

            return;
        }

        Log::info('lead-writeback EMITIDO', [
            'sync_ref' => substr($syncRef, 0, 8),
            'mandante_id' => $mandanteId,
            'grupos' => array_keys($changes),
        ]);

        app(EmisorWritebackFicha::class)->emitir($mandanteId, $syncRef, $changes);
    }

    /**
     * El sync_ref apunta al lead de la llamada que abrió la ficha. Si el gestor
     * navegó a otra persona en la misma sesión, lo que edite ahí no es de ese
     * lead: se omite antes que escribir en ViciDial los datos de otro cliente.
     * Sin persona anclada (el handshake no encontró ficha) se conserva el
     * comportamiento anterior: lo que se guarde, se escribe.
     */
    private function fichaAjenaAlLead(?string $personaPublicId): bool
    {
        $anclada = session('crm_persona_public_id');

        return is_string($anclada) && $anclada !== ''
            && $personaPublicId !== null && $personaPublicId !== $anclada;
    }
}
