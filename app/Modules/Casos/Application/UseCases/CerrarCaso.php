<?php

declare(strict_types=1);

namespace App\Modules\Casos\Application\UseCases;

use App\Modules\Casos\Application\DTOs\CerrarCasoInput;
use App\Modules\Casos\Domain\Contracts\CasoRepository;
use App\Modules\Casos\Domain\Entities\Caso;
use App\Modules\Casos\Domain\Events\CasoCerrado;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class CerrarCaso
{
    public function __construct(
        private CasoRepository $repositorio,
        private ConnectionInterface $db,
        private Dispatcher $eventos,
    ) {
    }

    public function execute(CerrarCasoInput $input): void
    {
        $this->db->transaction(function () use ($input): void {
            $caso = $this->repositorio->buscarPorId($input->casoId);
            $cerrado = $caso->cerrar($input->estadoCasoTerminalId, $input->cerradoEn);
            $persistido = $this->repositorio->save($cerrado);

            $this->eventos->dispatch(new CasoCerrado(
                casoId:       (int) $persistido->id,
                proyectoId:   $persistido->proyectoId,
                estadoCasoId: $persistido->estadoCasoId,
                cerradoEn:    $input->cerradoEn,
            ));
        });
    }
}
