<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Contracts;

/**
 * Writeback CRM→ViciDial (lado CRM): propaga al wrapper los cambios de la ficha del
 * cliente cuando el agente guarda, vía webhook saliente firmado.
 *
 * La emisión es best-effort y no debe bloquear ni romper el guardado: la
 * implementación encola el envío y omite silenciosamente cuando no hay contexto
 * de iframe (sin sync_ref) o el mandante no tiene webhook configurado.
 */
interface EmisorWritebackFicha
{
    /**
     * @param  array<string, array<string, mixed>>  $changes  grupos opcionales `persona`|`contacto`|`custom`
     */
    public function emitir(int $mandanteId, string $syncRef, array $changes): void;
}
