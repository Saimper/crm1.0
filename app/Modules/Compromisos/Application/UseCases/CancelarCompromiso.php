<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Application\UseCases;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Compromisos\Domain\Contracts\CompromisoRepository;
use App\Modules\Compromisos\Domain\Events\CompromisoCancelado;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class CancelarCompromiso
{
    public function __construct(
        private CompromisoRepository $repositorio,
        private ConnectionInterface $db,
        private Dispatcher $eventos,
    ) {
    }

    public function execute(ResolverCompromisoInput $input): void
    {
        $this->db->transaction(function () use ($input): void {
            $c = $this->repositorio->buscarPorId($input->compromisoId);
            $cancelado = $c->cancelar($input->fechaResolucion);
            $persistido = $this->repositorio->save($cancelado);

            $quedan = $this->repositorio->existenVigentesParaCaso($persistido->casoId);

            $this->eventos->dispatch(new CompromisoCancelado(
                compromisoId:                    (int) $persistido->id,
                proyectoId:                      $persistido->proyectoId,
                casoId:                          $persistido->casoId,
                usuarioId:                       $persistido->usuarioId,
                fechaResolucion:                 $input->fechaResolucion,
                quedanCompromisosVigentesEnCaso: $quedan,
            ));
        });
    }
}
