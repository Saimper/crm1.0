<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\UseCases;

use App\Modules\Tenancy\Application\DTOs\RegistrarCarteraInput;
use App\Modules\Tenancy\Application\DTOs\RegistrarCarteraOutput;
use App\Modules\Tenancy\Domain\Contracts\CarteraRepository;
use App\Modules\Tenancy\Domain\Entities\Cartera;
use App\Modules\Tenancy\Domain\Events\CarteraCreada;
use App\Modules\Tenancy\Domain\Exceptions\CodigoCarteraDuplicadoEnProyecto;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class RegistrarCartera
{
    public function __construct(
        private CarteraRepository $repositorio,
        private ConnectionInterface $db,
        private Dispatcher $eventos,
    ) {}

    public function execute(RegistrarCarteraInput $input): RegistrarCarteraOutput
    {
        if ($this->repositorio->existePorCodigoEnProyecto($input->proyectoId, $input->codigo)) {
            throw new CodigoCarteraDuplicadoEnProyecto(
                "El proyecto ya tiene una cartera con el código {$input->codigo->asString()}."
            );
        }

        $cartera = Cartera::registrar(
            publicId: $input->publicId,
            proyectoId: $input->proyectoId,
            codigo: $input->codigo,
            nombre: $input->nombre,
            descripcion: $input->descripcion,
            creadaEn: $input->creadaEn,
        );

        $persistida = $this->db->transaction(function () use ($cartera): Cartera {
            $guardada = $this->repositorio->save($cartera);

            $this->eventos->dispatch(new CarteraCreada(
                carteraId: (int) $guardada->id,
                publicId: $guardada->publicId,
                proyectoId: $guardada->proyectoId,
                creadaEn: $guardada->creadaEn,
            ));

            return $guardada;
        });

        return new RegistrarCarteraOutput(
            id: (int) $persistida->id,
            publicId: $persistida->publicId,
            codigo: $persistida->codigo->asString(),
            nombre: $persistida->nombre,
        );
    }
}
