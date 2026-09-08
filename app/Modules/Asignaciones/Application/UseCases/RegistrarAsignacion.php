<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Application\UseCases;

use App\Modules\Asignaciones\Application\DTOs\RegistrarAsignacionInput;
use App\Modules\Asignaciones\Domain\Contracts\AsignacionRepository;
use App\Modules\Asignaciones\Domain\Entities\Asignacion;
use App\Modules\Asignaciones\Domain\Exceptions\TransicionAsignacionInvalida;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class RegistrarAsignacion
{
    public function __construct(
        private AsignacionRepository $repositorio,
        private ConnectionInterface $db,
    ) {}

    public function execute(RegistrarAsignacionInput $input): int
    {
        if ($this->repositorio->existeParaCaso($input->proyectoId, $input->casoId)) {
            throw new TransicionAsignacionInvalida(
                'Esta cuenta ya tiene una asignación en el proyecto.'
            );
        }

        $asignacion = Asignacion::registrar(
            publicId: $input->publicId,
            proyectoId: $input->proyectoId,
            casoId: $input->casoId,
            usuarioId: $input->usuarioId,
            fechaAsignacion: $input->fechaAsignacion,
            prioridad: $input->prioridad,
            creadaEn: $input->creadaEn,
        );

        // La comprobación de arriba y este insert no son atómicos, y con el
        // botón «Tomar» en pantalla dos asesores pueden pulsarlo sobre la misma
        // cuenta en el mismo segundo. Quien pierde la carrera choca contra el
        // único `(proyecto_id, caso_id)`, y sin esto se llevaría un 500 en vez
        // de enterarse de que la cuenta ya es de otro. El índice es el árbitro;
        // la comprobación de arriba sólo evita el caso normal.
        try {
            $persistida = $this->db->transaction(fn (): Asignacion => $this->repositorio->save($asignacion));
        } catch (UniqueConstraintViolationException) {
            throw new TransicionAsignacionInvalida('Esta cuenta ya tiene una asignación en el proyecto.');
        }

        return (int) $persistida->id;
    }
}
