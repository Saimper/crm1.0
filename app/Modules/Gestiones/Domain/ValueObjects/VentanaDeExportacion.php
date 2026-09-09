<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Domain\ValueObjects;

use App\Modules\Gestiones\Domain\Exceptions\VentanaDeExportacionInvalida;
use DateTimeImmutable;

/**
 * Dos fechas de calendario que acotan una exportación de gestiones.
 *
 * Son fechas sin hora y sin zona a propósito: el día lo corta después el reloj
 * del cliente. Aquí sólo se decide si la ventana es admisible: ordenada y de
 * como mucho 92 días —un trimestre—, porque una descarga sin tope es la forma
 * más fácil de llevarse el histórico entero de un cliente en un solo clic, y
 * la que más tarda en cortarse a mitad.
 */
final readonly class VentanaDeExportacion
{
    public const MAXIMO_DIAS = 92;

    private function __construct(
        public string $desde,
        public string $hasta,
    ) {}

    /**
     * @param  string  $desde  'Y-m-d'
     * @param  string  $hasta  'Y-m-d'
     *
     * @throws VentanaDeExportacionInvalida
     */
    public static function entre(string $desde, string $hasta): self
    {
        $inicio = self::fecha($desde);
        $fin = self::fecha($hasta);

        if ($inicio === null || $fin === null) {
            throw VentanaDeExportacionInvalida::fechasIlegibles();
        }

        if ($inicio > $fin) {
            throw VentanaDeExportacionInvalida::desdeDespuesDeHasta($desde, $hasta);
        }

        // Inclusivo por los dos lados: del 1 al 1 es un día.
        $dias = (int) $inicio->diff($fin)->days + 1;
        if ($dias > self::MAXIMO_DIAS) {
            throw VentanaDeExportacionInvalida::demasiadoLarga($dias, self::MAXIMO_DIAS);
        }

        return new self($inicio->format('Y-m-d'), $fin->format('Y-m-d'));
    }

    public function dias(): int
    {
        $inicio = self::fecha($this->desde);
        $fin = self::fecha($this->hasta);

        return $inicio === null || $fin === null ? 0 : (int) $inicio->diff($fin)->days + 1;
    }

    private static function fecha(string $texto): ?DateTimeImmutable
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);

        // createFromFormat acepta «2026-02-31» y lo desborda a marzo: se exige
        // que lo leído sea exactamente lo escrito.
        return $fecha !== false && $fecha->format('Y-m-d') === $texto ? $fecha : null;
    }
}
