<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Concerns;

use App\Modules\Integracion\Domain\Contracts\EmisorWritebackFicha;

/**
 * Concern para los Livewire de la ficha (EditarPersona, ListaContactos, EditarCaso).
 * Emite el writeback CRM→ViciDial tras un guardado exitoso, solo cuando la sesión
 * proviene de un handshake con lead activo.
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
     */
    protected function emitirWritebackFicha(array $changes): void
    {
        $syncRef = session('crm_sync_ref');
        $mandanteId = (int) session('crm_mandante_id');

        if (! is_string($syncRef) || $syncRef === '' || $mandanteId === 0 || $changes === []) {
            return;
        }

        app(EmisorWritebackFicha::class)->emitir($mandanteId, $syncRef, $changes);
    }
}
