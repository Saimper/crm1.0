<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Application\Listeners;

use App\Modules\Asignaciones\Application\UseCases\AutoasignarCaso;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use DateTimeImmutable;
use Throwable;

/**
 * Cuando un asesor gestiona una cuenta sin dueño, la cuenta pasa a ser suya.
 *
 * Sin esto, gestionar una cuenta encontrada por búsqueda no dejaba rastro en la
 * bandeja de nadie: el asesor registraba una promesa y al día siguiente no tenía
 * forma de saber que le correspondía darle seguimiento.
 *
 * Las condiciones —que el proyecto lo permita y que la cuenta no tenga ya
 * dueño— viven en `AutoasignarCaso`, porque son las mismas cuando el asesor
 * toma la cuenta pulsando el botón.
 *
 * Lo único propio de este camino es el silencio: nada de esto puede tumbar el
 * registro de la gestión. La gestión es el hecho de negocio; la asignación es
 * una comodidad. Si falla, se ignora.
 */
final readonly class AutoasignarCasoDesdeGestion
{
    public function __construct(
        private AutoasignarCaso $autoasignar,
    ) {}

    public function handle(GestionRegistrada $evento): void
    {
        try {
            $this->autoasignar->execute(
                proyectoId: $evento->proyectoId,
                casoId: $evento->casoId,
                usuarioId: $evento->usuarioId,
                ahora: new DateTimeImmutable,
            );
        } catch (Throwable) {
            // Una asignación que no sale no puede impedir que la gestión quede
            // registrada: el asesor hizo su trabajo y eso no se pierde.
        }
    }
}
