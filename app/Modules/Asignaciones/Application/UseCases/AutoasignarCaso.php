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
 * Las cuatro condiciones son las mismas que las del listener, y por las mismas
 * razones. La diferencia está en el fallo: aquí se lanza y se muestra.
 */
final readonly class AutoasignarCaso
{
    public function __construct(
        private RegistrarAsignacion $registrar,
        private ConnectionInterface $db,
    ) {}

    public function execute(
        int $proyectoId,
        int $casoId,
        int $usuarioId,
        DateTimeImmutable $ahora,
    ): int {
        if (! $this->proyectoLoPermite($proyectoId)) {
            throw new AutoasignacionNoPermitida(
                'Este proyecto no permite que el asesor tome cuentas por su cuenta; las reparte el supervisor.'
            );
        }

        $casoValido = $this->db->table('casos')
            ->where('id', $casoId)
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->exists();

        if (! $casoValido) {
            throw new AutoasignacionNoPermitida('La cuenta no existe en este proyecto.');
        }

        $duenio = $this->duenioActual($casoId);

        if ($duenio !== null) {
            throw new AutoasignacionNoPermitida(
                $duenio === $usuarioId
                    ? 'Esta cuenta ya es tuya.'
                    : 'Esta cuenta ya tiene dueño. Pídesela al supervisor.'
            );
        }

        $campanaId = $this->campanaUnicaActiva($proyectoId);

        return $this->registrar->execute(new RegistrarAsignacionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $proyectoId,
            campanaId: $campanaId,
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
     * Una asignación cerrada también cuenta: el único `(campana_id, caso_id)`
     * impide crear otra fila para la misma cuenta en la misma campaña, así que
     * una cuenta ya trabajada no se vuelve a tomar sola —la devuelve a la
     * circulación el supervisor, reasignándola—.
     */
    private function duenioActual(int $casoId): ?int
    {
        $usuarioId = $this->db->table('asignaciones')
            ->where('caso_id', $casoId)
            ->orderByDesc('id')
            ->value('usuario_id');

        return $usuarioId === null ? null : (int) $usuarioId;
    }

    /**
     * Con ninguna campaña activa no hay dónde colgar la asignación
     * (`asignaciones.campana_id` es NOT NULL); con varias, elegir por el asesor
     * sería adivinar, y una cuenta en la campaña equivocada descuadra el reparto.
     */
    private function campanaUnicaActiva(int $proyectoId): int
    {
        $campanas = $this->db->table('campanas')
            ->where('proyecto_id', $proyectoId)
            ->where('estado', 'activa')
            ->whereNull('eliminada_en')
            ->pluck('id');

        if ($campanas->count() === 0) {
            throw new AutoasignacionNoPermitida(
                'El proyecto no tiene ninguna campaña activa donde colgar la asignación.'
            );
        }

        if ($campanas->count() > 1) {
            throw new AutoasignacionNoPermitida(
                'El proyecto tiene varias campañas activas: el supervisor tiene que asignar la cuenta a una.'
            );
        }

        return (int) $campanas->first();
    }
}
