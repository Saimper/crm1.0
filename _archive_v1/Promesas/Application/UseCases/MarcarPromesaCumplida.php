<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Application\UseCases;

use App\Modules\Promesas\Application\DTOs\ResolverPromesaInput;
use App\Modules\Promesas\Domain\Contracts\PromesaRepository;
use App\Modules\Promesas\Domain\Events\PromesaCumplida;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class MarcarPromesaCumplida
{
    public function __construct(
        private PromesaRepository $repositorio,
        private ConnectionInterface $db,
        private Dispatcher $eventos,
    ) {}

    public function execute(ResolverPromesaInput $input): void
    {
        $this->db->transaction(function () use ($input): void {
            $promesa = $this->repositorio->buscarPorId($input->promesaId);
            $cumplida = $promesa->marcarCumplida($input->fechaResolucion);
            $persistida = $this->repositorio->save($cumplida);

            $quedan = $this->repositorio->existenVigentesParaProducto($persistida->productoId);

            $this->eventos->dispatch(new PromesaCumplida(
                promesaId: (int) $persistida->id,
                productoId: $persistida->productoId,
                usuarioId: $persistida->usuarioId,
                fechaResolucion: $input->fechaResolucion,
                quedanPromesasVigentes: $quedan,
            ));
        });
    }
}
