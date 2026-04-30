<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\UseCases;

use App\Modules\Tenancy\Application\DTOs\RegistrarMandanteInput;
use App\Modules\Tenancy\Application\DTOs\RegistrarMandanteOutput;
use App\Modules\Tenancy\Domain\Contracts\MandanteRepository;
use App\Modules\Tenancy\Domain\Entities\Mandante;
use App\Modules\Tenancy\Domain\Exceptions\CodigoMandanteDuplicado;
use Illuminate\Database\ConnectionInterface;

final readonly class RegistrarMandante
{
    public function __construct(
        private MandanteRepository $repositorio,
        private ConnectionInterface $db,
    ) {}

    public function execute(RegistrarMandanteInput $input): RegistrarMandanteOutput
    {
        if ($this->repositorio->existePorCodigo($input->codigo)) {
            throw new CodigoMandanteDuplicado(
                "Ya existe un mandante con el código {$input->codigo->asString()}."
            );
        }

        $mandante = Mandante::registrar(
            publicId: $input->publicId,
            codigo: $input->codigo,
            nombre: $input->nombre,
            documento: $input->documento,
            creadaEn: $input->creadaEn,
        );

        $persistido = $this->db->transaction(fn (): Mandante => $this->repositorio->save($mandante));

        return new RegistrarMandanteOutput(
            id: (int) $persistido->id,
            publicId: $persistido->publicId,
            codigo: $persistido->codigo->asString(),
            nombre: $persistido->nombre,
        );
    }
}
