<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Domain\ValueObjects;

use App\Modules\Cobranza\Domain\Exceptions\DatosCasoCobranzaInvalidos;

final readonly class DiasMora
{
    /**
     * Techo de cordura: 40 años.
     *
     * En la réplica de producción hay cuentas con 6.279 días de mora —más de
     * diecisiete años— y semanas de atraso de 897. No son cuentas antiquísimas:
     * son fechas mal parseadas en la importación, y la pantalla las presenta
     * como si fueran ciertas. Un préstamo de consumo con cuarenta años de mora
     * no existe; lo que existe es una celda con el año equivocado.
     *
     * No se recorta el valor: se rechaza, para que la importación lo cuente como
     * fila inválida y alguien mire el fichero en vez de dar por buena una cifra
     * que nadie puede interpretar.
     */
    public const MAXIMO_RAZONABLE = 14600;

    /**
     * A partir de cuántos días sin que una FUENTE confirme la mora hay que avisar.
     *
     * El CRM envejece la mora día a día, pero no sabe si la cuenta sigue viva:
     * no hay importación «foto» que cierre lo que ya no viene en el archivo del
     * cliente, así que una cuenta pagada o castigada que el cliente dejó de
     * enviar seguiría sumando días para siempre. Cuarenta y cinco días son un
     * ciclo de facturación y medio: si en ese plazo no ha llegado ningún archivo
     * que la afirme, lo raro es que siga abierta. Es una constante de dominio y
     * no un parámetro de pantalla a propósito (§13.14).
     */
    public const DIAS_SIN_CONFIRMAR_AVISO = 45;

    public function __construct(public int $dias)
    {
        if ($dias < 0) {
            throw new DatosCasoCobranzaInvalidos("Los días de mora no pueden ser negativos. Recibido: {$dias}.");
        }

        if ($dias > self::MAXIMO_RAZONABLE) {
            throw new DatosCasoCobranzaInvalidos(
                "Los días de mora ({$dias}) superan los 40 años; casi siempre es una fecha mal leída en el archivo."
            );
        }
    }

    public function estaEnMora(): bool
    {
        return $this->dias > 0;
    }
}
