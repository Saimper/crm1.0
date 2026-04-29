<?php

declare(strict_types=1);

namespace App\Modules\Clientes\Application\UseCases;

use App\Modules\Clientes\Application\DTOs\RegistrarClienteInput;
use App\Modules\Clientes\Application\DTOs\RegistrarClienteOutput;
use App\Modules\Clientes\Application\Exceptions\IdentificacionYaExistente;
use App\Modules\Clientes\Domain\Contracts\ClienteRepository;
use App\Modules\Clientes\Domain\Entities\Cliente;
use Illuminate\Database\ConnectionInterface;

final readonly class RegistrarCliente
{
    public function __construct(
        private ClienteRepository $repositorio,
        private ConnectionInterface $db,
    ) {
    }

    public function execute(RegistrarClienteInput $input): RegistrarClienteOutput
    {
        if ($this->repositorio->existePorIdentificacion($input->identificacion)) {
            throw new IdentificacionYaExistente(
                "Ya existe un cliente con la identificación {$input->identificacion->asString()}."
            );
        }

        $cliente = Cliente::registrar(
            publicId:             $input->publicId,
            tipoPersona:          $input->tipoPersona,
            tipoIdentificacionId: $input->tipoIdentificacionId,
            identificacion:       $input->identificacion,
            nombres:              $input->nombres,
            apellidos:            $input->apellidos,
            razonSocial:          $input->razonSocial,
            fechaNacimiento:      $input->fechaNacimiento,
            creadaEn:             $input->creadaEn,
        );

        $persistido = $this->db->transaction(fn (): Cliente => $this->repositorio->save($cliente));

        return new RegistrarClienteOutput(
            id:             (int) $persistido->id,
            publicId:       $persistido->publicId,
            nombreCompleto: $persistido->nombreCompleto(),
        );
    }
}
