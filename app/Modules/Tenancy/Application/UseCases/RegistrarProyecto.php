<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\UseCases;

use App\Modules\Tenancy\Application\DTOs\RegistrarProyectoInput;
use App\Modules\Tenancy\Application\DTOs\RegistrarProyectoOutput;
use App\Modules\Tenancy\Domain\Contracts\ProyectoRepository;
use App\Modules\Tenancy\Domain\Entities\Proyecto;
use App\Modules\Tenancy\Domain\Events\ProyectoCreado;
use App\Modules\Tenancy\Domain\Exceptions\CodigoProyectoDuplicadoEnMandante;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class RegistrarProyecto
{
    public function __construct(
        private ProyectoRepository $repositorio,
        private ConnectionInterface $db,
        private Dispatcher $eventos,
    ) {
    }

    public function execute(RegistrarProyectoInput $input): RegistrarProyectoOutput
    {
        if ($this->repositorio->existePorCodigoEnMandante($input->mandanteId, $input->codigo)) {
            throw new CodigoProyectoDuplicadoEnMandante(
                "El mandante ya tiene un proyecto con el código {$input->codigo->asString()}."
            );
        }

        $proyecto = Proyecto::registrar(
            publicId:      $input->publicId,
            mandanteId:    $input->mandanteId,
            codigo:        $input->codigo,
            nombre:        $input->nombre,
            descripcion:   $input->descripcion,
            tipoOperacion: $input->tipoOperacion,
            fechaInicio:   $input->fechaInicio,
            fechaFin:      $input->fechaFin,
            creadaEn:      $input->creadaEn,
        );

        $persistido = $this->db->transaction(function () use ($proyecto): Proyecto {
            $guardado = $this->repositorio->save($proyecto);

            $this->eventos->dispatch(new ProyectoCreado(
                proyectoId:    (int) $guardado->id,
                publicId:      $guardado->publicId,
                mandanteId:    $guardado->mandanteId,
                tipoOperacion: $guardado->tipoOperacion,
                creadaEn:      $guardado->creadaEn,
            ));

            return $guardado;
        });

        return new RegistrarProyectoOutput(
            id:            (int) $persistido->id,
            publicId:      $persistido->publicId,
            codigo:        $persistido->codigo->asString(),
            nombre:        $persistido->nombre,
            tipoOperacion: $persistido->tipoOperacion->value,
        );
    }
}
