<?php

declare(strict_types=1);

namespace App\Modules\Personas\Application\UseCases;

use App\Modules\Personas\Application\DTOs\RegistrarPersonaInput;
use App\Modules\Personas\Application\DTOs\RegistrarPersonaOutput;
use App\Modules\Personas\Domain\Contracts\PersonaRepository;
use App\Modules\Personas\Domain\Entities\Persona;
use App\Modules\Personas\Domain\Exceptions\IdentificacionYaRegistradaEnProyecto;
use Illuminate\Database\ConnectionInterface;

final readonly class RegistrarPersona
{
    public function __construct(
        private PersonaRepository $repositorio,
        private ConnectionInterface $db,
    ) {
    }

    public function execute(RegistrarPersonaInput $input): RegistrarPersonaOutput
    {
        $yaExiste = $this->repositorio->existePorIdentificacionEnProyecto(
            $input->proyectoId,
            $input->tipoIdentificacionId,
            $input->identificacion,
        );

        if ($yaExiste) {
            throw new IdentificacionYaRegistradaEnProyecto(
                "Ya existe una persona con la identificación {$input->identificacion->asString()} en el proyecto actual."
            );
        }

        $persona = Persona::registrar(
            publicId:             $input->publicId,
            proyectoId:           $input->proyectoId,
            tipoPersona:          $input->tipoPersona,
            tipoIdentificacionId: $input->tipoIdentificacionId,
            identificacion:       $input->identificacion,
            nombres:              $input->nombres,
            apellidos:            $input->apellidos,
            razonSocial:          $input->razonSocial,
            fechaNacimiento:      $input->fechaNacimiento,
            creadaEn:             $input->creadaEn,
        );

        $persistida = $this->db->transaction(fn (): Persona => $this->repositorio->save($persona));

        return new RegistrarPersonaOutput(
            id:             (int) $persistida->id,
            publicId:       $persistida->publicId,
            nombreCompleto: $persistida->nombreCompleto(),
        );
    }
}
