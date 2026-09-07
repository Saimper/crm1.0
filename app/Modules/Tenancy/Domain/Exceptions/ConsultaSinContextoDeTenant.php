<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\Exceptions;

use RuntimeException;

/**
 * Se consultó un modelo con scope de tenant sin que hubiera tenant activo.
 *
 * Antes eso devolvía los datos de todos los clientes en silencio. Devolver de
 * más es la peor forma de fallar en un sistema multi-tenant: nadie se entera.
 *
 * Si la consulta es legítimamente cross-tenant —un reporte consolidado, una
 * migración, un comando de mantenimiento— dilo en el código con
 * `sinScopeProyecto()` o `sinScopeMandante()`. La intención se escribe, no se
 * deduce del hecho de que no hubiera contexto.
 */
final class ConsultaSinContextoDeTenant extends RuntimeException
{
    public static function paraModelo(string $modelo, string $tipo): self
    {
        return new self(
            "Consulta sobre {$modelo} sin {$tipo} activo. "
            ."Declara el contexto, o pide explícitamente saltarte el scope si la consulta es cross-tenant."
        );
    }
}
