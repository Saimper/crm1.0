<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Domain\Exceptions;

use DomainException;

/**
 * Cambiar el tipo de un campo que ya tiene valores los deja ilegibles.
 *
 * El valor de un campo personalizado vive en la columna que le corresponde a su
 * tipo (§7). Cambiar el tipo desde una pantalla actualiza la definición y no
 * mueve los valores, así que el lector pasa a mirar una columna vacía: el dato
 * sigue en la base y el usuario ve el campo en blanco. Ya ocurrió: tres campos
 * del proyecto de cobranza pasaron a `moneda` con 22.623 valores que se
 * quedaron en `valor_texto_corto`.
 *
 * Mover valores es una operación de datos, no de formulario, y tiene sus
 * comandos: `campos:convertir-tipo` cuando el tipo declarado hay que cambiarlo,
 * `campos:recolocar-valores` cuando el tipo ya es el bueno.
 */
final class CambioDeTipoNoPermitido extends DomainException
{
    public static function porqueYaTieneValores(string $etiqueta, string $tipoActual, string $tipoNuevo, int $valores): self
    {
        return new self(
            "«{$etiqueta}» ya tiene {$valores} valores guardados como {$tipoActual}. "
            ."Cambiarlo a {$tipoNuevo} desde aquí los dejaría ilegibles. "
            .'Usa `php artisan campos:convertir-tipo` para mover los valores con el tipo.'
        );
    }
}
