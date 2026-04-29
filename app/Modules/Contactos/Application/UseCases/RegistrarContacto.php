<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Application\UseCases;

use App\Modules\Contactos\Application\DTOs\RegistrarContactoInput;
use App\Modules\Contactos\Application\DTOs\RegistrarContactoOutput;
use App\Modules\Contactos\Domain\Contracts\ContactoRepository;
use App\Modules\Contactos\Domain\Entities\Contacto;
use App\Modules\Contactos\Domain\Exceptions\DatosContactoInvalidos;
use Illuminate\Database\ConnectionInterface;

final readonly class RegistrarContacto
{
    public function __construct(
        private ContactoRepository $repositorio,
        private ConnectionInterface $db,
    ) {
    }

    public function execute(RegistrarContactoInput $input): RegistrarContactoOutput
    {
        $contacto = Contacto::registrar(
            proyectoId:  $input->proyectoId,
            personaId:   $input->personaId,
            tipo:        $input->tipo,
            valor:       $input->valor,
            etiqueta:    $input->etiqueta,
            esPrincipal: $input->esPrincipal,
            creadaEn:    $input->creadaEn,
        );

        $yaExiste = $this->repositorio->existeValorParaPersona(
            $input->proyectoId,
            $input->personaId,
            $contacto->valor,
        );
        if ($yaExiste) {
            throw new DatosContactoInvalidos(
                "Ya existe un contacto con el mismo valor para esta persona: {$contacto->valor}."
            );
        }

        $persistido = $this->db->transaction(fn (): Contacto => $this->repositorio->save($contacto));

        return new RegistrarContactoOutput(
            id:    (int) $persistido->id,
            valor: $persistido->valor,
            tipo:  $persistido->tipo->value,
        );
    }
}
