<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\Exceptions;

use RuntimeException;

/**
 * Se intentó escribir una fila fuera del proyecto que está activo.
 *
 * No es un fallo del usuario ni un caso de uso raro: es código que resolvió mal
 * el proyecto. Por eso revienta en vez de corregirse solo — reescribir en
 * silencio el `proyecto_id` que alguien puso a mano sería peor, porque el dato
 * acabaría donde nadie pidió y sin dejar rastro de que se movió.
 *
 * Si la escritura cross-proyecto es intencionada —una tarea de plataforma—, el
 * sitio de esa tarea es fuera de un contexto de proyecto, no dentro del de otro.
 */
final class EscrituraFueraDelProyectoActivo extends RuntimeException
{
    public static function para(string $modelo, string $accion, int $destino, int $activo): self
    {
        return new self(sprintf(
            'Intento de %s %s en el proyecto %d con el proyecto %d activo. '
            .'Resuelve el proyecto correcto, o haz la escritura fuera de un contexto de proyecto.',
            $accion,
            $modelo,
            $destino,
            $activo,
        ));
    }
}
