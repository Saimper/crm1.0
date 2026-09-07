<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Application\Listeners;

use App\Modules\Asignaciones\Application\DTOs\RegistrarAsignacionInput;
use App\Modules\Asignaciones\Application\UseCases\RegistrarAsignacion;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cuando un asesor gestiona una cuenta sin dueño, la cuenta pasa a ser suya.
 *
 * Sin esto, gestionar una cuenta encontrada por búsqueda no dejaba rastro en la
 * bandeja de nadie: el asesor registraba una promesa y al día siguiente no tenía
 * forma de saber que le correspondía darle seguimiento.
 *
 * Cuatro condiciones, y las cuatro importan:
 *
 *  1. El proyecto tiene que permitirlo. Hay operaciones donde repartir es
 *     decisión del supervisor y sólo suya.
 *  2. La cuenta no puede tener ya dueño. Si es de otro asesor no se le quita:
 *     robar una cuenta en silencio es peor que no asignarla.
 *  3. Tiene que haber UNA campaña activa. Con ninguna no hay dónde colgar la
 *     asignación (campana_id es NOT NULL); con varias, elegir por el usuario
 *     sería adivinar, y una cuenta en la campaña equivocada descuadra el reparto.
 *  4. Nada de esto puede tumbar el registro de la gestión. La gestión es el
 *     hecho de negocio; la asignación es una comodidad. Si falla, se ignora.
 */
final readonly class AutoasignarCasoDesdeGestion
{
    public function __construct(
        private RegistrarAsignacion $registrar,
        private ConnectionInterface $db,
    ) {}

    public function handle(GestionRegistrada $evento): void
    {
        $permite = $this->db->table('proyectos')
            ->where('id', $evento->proyectoId)
            ->value('permite_autoasignacion');

        if (! (bool) $permite) {
            return;
        }

        $yaTieneDuenio = $this->db->table('asignaciones')
            ->where('caso_id', $evento->casoId)
            ->exists();

        if ($yaTieneDuenio) {
            return;
        }

        $campanas = $this->db->table('campanas')
            ->where('proyecto_id', $evento->proyectoId)
            ->where('estado', 'activa')
            ->whereNull('eliminada_en')
            ->pluck('id');

        if ($campanas->count() !== 1) {
            return;
        }

        try {
            $this->registrar->execute(new RegistrarAsignacionInput(
                publicId: (string) Str::ulid(),
                proyectoId: $evento->proyectoId,
                campanaId: (int) $campanas->first(),
                casoId: $evento->casoId,
                usuarioId: $evento->usuarioId,
                fechaAsignacion: new DateTimeImmutable,
                prioridad: 100,
                creadaEn: new DateTimeImmutable,
            ));
        } catch (Throwable) {
            // Una asignación que no sale no puede impedir que la gestión quede
            // registrada: el asesor hizo su trabajo y eso no se pierde.
        }
    }
}
