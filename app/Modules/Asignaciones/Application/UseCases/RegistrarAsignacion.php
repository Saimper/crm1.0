<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Application\UseCases;

use App\Modules\Asignaciones\Application\DTOs\RegistrarAsignacionInput;
use App\Modules\Asignaciones\Domain\Contracts\AsignacionRepository;
use App\Modules\Asignaciones\Domain\Entities\Asignacion;
use App\Modules\Asignaciones\Domain\Exceptions\TransicionAsignacionInvalida;
use Illuminate\Database\ConnectionInterface;

final readonly class RegistrarAsignacion
{
    public function __construct(
        private AsignacionRepository $repositorio,
        private ConnectionInterface $db,
    ) {
    }

    public function execute(RegistrarAsignacionInput $input): int
    {
        if ($this->repositorio->existeParaCampanaCaso($input->campanaId, $input->casoId)) {
            throw new TransicionAsignacionInvalida(
                'Ya existe una asignación para la misma campaña y caso.'
            );
        }

        $asignacion = Asignacion::registrar(
            publicId:        $input->publicId,
            proyectoId:      $input->proyectoId,
            campanaId:       $input->campanaId,
            casoId:          $input->casoId,
            usuarioId:       $input->usuarioId,
            fechaAsignacion: $input->fechaAsignacion,
            prioridad:       $input->prioridad,
            creadaEn:        $input->creadaEn,
        );

        $persistida = $this->db->transaction(fn (): Asignacion => $this->repositorio->save($asignacion));

        return (int) $persistida->id;
    }
}
