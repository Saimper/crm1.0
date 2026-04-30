<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Application\UseCases;

use App\Modules\Promesas\Application\DTOs\ResolverPromesaInput;
use App\Modules\Promesas\Domain\Contracts\PromesaRepository;
use App\Modules\Promesas\Domain\Events\PromesaCancelada;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class CancelarPromesa
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
            $cancelada = $promesa->cancelar($input->fechaResolucion);
            $persistida = $this->repositorio->save($cancelada);

            $quedan = $this->repositorio->existenVigentesParaProducto($persistida->productoId);

            $this->eventos->dispatch(new PromesaCancelada(
                promesaId: (int) $persistida->id,
                productoId: $persistida->productoId,
                usuarioId: $persistida->usuarioId,
                fechaResolucion: $input->fechaResolucion,
                quedanPromesasVigentes: $quedan,
            ));
        });
    }
}
