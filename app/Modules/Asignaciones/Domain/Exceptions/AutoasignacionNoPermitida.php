<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Domain\Exceptions;

use RuntimeException;

/**
 * La cuenta no se puede tomar, y el asesor merece saber por qué.
 *
 * A diferencia del listener —que autoasigna en silencio y calla si falla—, aquí
 * el asesor pulsó un botón: si no se puede, el mensaje se le enseña.
 */
final class AutoasignacionNoPermitida extends RuntimeException {}
