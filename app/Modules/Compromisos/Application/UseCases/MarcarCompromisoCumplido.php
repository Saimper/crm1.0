<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Application\UseCases;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Compromisos\Domain\Contracts\CompromisoRepository;
use App\Modules\Compromisos\Domain\Events\CompromisoCumplido;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class MarcarCompromisoCumplido
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
            $cumplido = $c->marcarCumplido($input->fechaResolucion);
            $persistido = $this->repositorio->save($cumplido);

            $quedan = $this->repositorio->existenVigentesParaCaso($persistido->casoId);

            $this->eventos->dispatch(new CompromisoCumplido(
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
