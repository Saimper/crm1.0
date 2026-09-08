<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Application\UseCases;

use App\Modules\Asignaciones\Application\DTOs\RegistrarAsignacionInput;
use App\Modules\Asignaciones\Domain\Exceptions\AutoasignacionNoPermitida;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * Un asesor toma para sí una cuenta que no tiene dueño.
 *
 * Es el mismo hecho que ya ocurría solo al registrar una gestión sobre una
 * cuenta huérfana (`AutoasignarCasoDesdeGestion`), pero explícito: con 5.000
 * cuentas en el proyecto, el asesor que va a empezar a trabajar una necesita
 * poder decirlo antes de gestionarla, no descubrirlo después.
 *
 * Las tres condiciones son las mismas que las del listener, y por las mismas
 * razones. La diferencia está en el fallo: aquí se lanza y se muestra.
 */
final readonly class AutoasignarCaso
{
    public function __construct(
        private RegistrarAsignacion $registrar,
        private ConnectionInterface $db,
    ) {}

    /**
     * @param  list<int>|null  $carterasPermitidas  Las carteras a las que el rol
     *                                              limita al asesor (F22), o null si no le limita a
     *                                              ninguna. Llega por parámetro y no de `auth()`
     *                                              porque un UseCase no conoce al usuario logueado
     *                                              (§13.10).
     */
    public function execute(
        int $proyectoId,
        int $casoId,
        int $usuarioId,
        DateTimeImmutable $ahora,
        ?array $carterasPermitidas = null,
    ): int {
        if (! $this->proyectoLoPermite($proyectoId)) {
            throw new AutoasignacionNoPermitida(
                'Este proyecto no permite que el asesor tome cuentas por su cuenta; las reparte el supervisor.'
            );
        }

        $caso = $this->db->table('casos')
            ->where('id', $casoId)
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->first(['cartera_id']);

        if ($caso === null) {
            throw new AutoasignacionNoPermitida('La cuenta no existe en este proyecto.');
        }

        // El límite por cartera del rol (F22) se comprueba aquí y no sólo en la
        // pantalla: quien pulsa «Tomar» manda un id de caso, y las tres
        // pantallas que ofrecen el botón filtran la LISTA, no la acción. Sin
        // esto, un asesor limitado a una cartera se lleva cuentas de otra
        // repitiendo la petición con otro id.
        if ($carterasPermitidas !== null && ! in_array((int) $caso->cartera_id, $carterasPermitidas, true)) {
            throw new AutoasignacionNoPermitida('Esta cuenta es de una cartera que no tienes asignada.');
        }

        $duenio = $this->duenioActual($proyectoId, $casoId);

        if ($duenio !== null) {
            throw new AutoasignacionNoPermitida(
                $duenio === $usuarioId
                    ? 'Esta cuenta ya es tuya.'
                    : 'Esta cuenta ya tiene dueño. Pídesela al supervisor.'
            );
        }

        return $this->registrar->execute(new RegistrarAsignacionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $proyectoId,
            casoId: $casoId,
            usuarioId: $usuarioId,
            fechaAsignacion: $ahora,
            prioridad: 100,
            creadaEn: $ahora,
        ));
    }

    public function proyectoLoPermite(int $proyectoId): bool
    {
        return (bool) $this->db->table('proyectos')
            ->where('id', $proyectoId)
            ->value('permite_autoasignacion');
    }

    /**
     * Quién tiene la cuenta, o null si no la ha tenido nadie.
     *
     * Una asignación cerrada también cuenta: el único `(proyecto_id, caso_id)`
     * impide crear una segunda fila para la misma cuenta, así que una cuenta ya
     * trabajada no se vuelve a tomar sola. La devuelve a la circulación el
     * supervisor desde la bandeja del equipo, pasándosela a alguien: eso reabre
     * la fila cerrada (`ReasignarAsignacionAUsuario`). Es la única puerta de
     * vuelta que hay, y por eso existe.
     */
    private function duenioActual(int $proyectoId, int $casoId): ?int
    {
        $usuarioId = $this->db->table('asignaciones')
            ->where('proyecto_id', $proyectoId)
            ->where('caso_id', $casoId)
            ->orderByDesc('id')
            ->value('usuario_id');

        return $usuarioId === null ? null : (int) $usuarioId;
    }
}
