<?php

declare(strict_types=1);

namespace App\Modules\Usuarios\Domain\RolesCustom\ValueObjects;

use InvalidArgumentException;

/**
 * Código de un rol custom dentro de un proyecto.
 *
 * Reglas:
 *   - 2..40 caracteres
 *   - solo MAYÚSCULAS, dígitos y guion bajo
 *   - debe iniciar con letra
 *
 * Coherente con codigos de roles base (`SUPERVISOR`, `GESTOR`, `AUDITOR`,
 * `ADMIN_GLOBAL`) por consistencia visual en la UI y matriz de permisos.
 */
final readonly class CodigoRolCustom
{
    public string $valor;

    public function __construct(string $valor)
    {
        $limpio = strtoupper(trim($valor));
        $longitud = mb_strlen($limpio);

        if ($longitud < 2 || $longitud > 40) {
            throw new InvalidArgumentException(
                "Código de rol custom debe tener entre 2 y 40 caracteres. Recibido: {$longitud}.",
            );
        }
        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $limpio) !== 1) {
            throw new InvalidArgumentException(
                "Código de rol custom debe empezar por letra y contener solo letras mayúsculas, dígitos y guion bajo. Recibido: {$valor}.",
            );
        }

        $this->valor = $limpio;
    }

    public function asString(): string
    {
        return $this->valor;
    }
}
