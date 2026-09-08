<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Exceptions;

use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use RuntimeException;

/**
 * La importación no puede correr, y el motivo es de la importación misma, no
 * de una fila: el estado no lo admite, no hay esquema, el esquema guardado no
 * se puede leer, o el proyecto no tiene con qué crear casos.
 *
 * Sustituye a los `RuntimeException` sueltos que tenía el motor. Es apta para
 * pantalla porque cada texto es fijo y lo único que interpola son ids y valores
 * de enum, nunca un dato de una persona.
 */
final class ImportacionNoProcesable extends RuntimeException implements MensajeAptoParaPantalla
{
    public static function enEstado(EstadoImportacion $estado): self
    {
        return new self(sprintf(
            'La importación está en estado «%s»; sólo se procesa una importación preparada o en proceso.',
            $estado->value,
        ));
    }

    public static function sinEsquema(): self
    {
        return new self('La importación no tiene esquema configurado.');
    }

    public static function esquemaMalformado(string $detalle): self
    {
        return new self('El esquema guardado de la importación no se puede leer: '.$detalle);
    }

    public static function sinEstadosDeCaso(int $proyectoId): self
    {
        return new self("No hay estados de caso activos configurados para el proyecto {$proyectoId}.");
    }

    public static function sinTiposDeIdentificacion(): self
    {
        return new self('No hay tipos de identificación disponibles en el sistema.');
    }

    public static function targetNoSoportado(string $target): self
    {
        return new self("Target no soportado: {$target}.");
    }
}
