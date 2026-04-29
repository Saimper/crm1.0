<?php

declare(strict_types=1);

namespace App\Modules\Campanas\Application\UseCases;

use App\Modules\Campanas\Application\DTOs\RegistrarCampanaInput;
use App\Modules\Campanas\Application\DTOs\RegistrarCampanaOutput;
use App\Modules\Campanas\Domain\Contracts\CampanaRepository;
use App\Modules\Campanas\Domain\Entities\Campana;
use App\Modules\Campanas\Domain\Events\CampanaCreada;
use App\Modules\Campanas\Domain\Exceptions\CodigoCampanaDuplicadoEnProyecto;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class RegistrarCampana
{
    public function __construct(
        private CampanaRepository $repositorio,
        private ConnectionInterface $db,
        private Dispatcher $eventos,
    ) {
    }

    public function execute(RegistrarCampanaInput $input): RegistrarCampanaOutput
    {
        if ($this->repositorio->existePorCodigoEnProyecto($input->proyectoId, $input->codigo)) {
            throw new CodigoCampanaDuplicadoEnProyecto(
                "El proyecto ya tiene una campaña con el código {$input->codigo->asString()}."
            );
        }

        $campana = Campana::registrar(
            publicId:    $input->publicId,
            proyectoId:  $input->proyectoId,
            codigo:      $input->codigo,
            nombre:      $input->nombre,
            descripcion: $input->descripcion,
            fechaInicio: $input->fechaInicio,
            fechaFin:    $input->fechaFin,
            creadaPorId: $input->creadaPorId,
            creadaEn:    $input->creadaEn,
        );

        $persistida = $this->db->transaction(function () use ($campana): Campana {
            $guardada = $this->repositorio->save($campana);

            $this->eventos->dispatch(new CampanaCreada(
                campanaId: (int) $guardada->id,
                publicId:  $guardada->publicId,
                proyectoId: $guardada->proyectoId,
                creadaEn:  $guardada->creadaEn,
            ));

            return $guardada;
        });

        return new RegistrarCampanaOutput(
            id:       (int) $persistida->id,
            publicId: $persistida->publicId,
            codigo:   $persistida->codigo->asString(),
        );
    }
}
