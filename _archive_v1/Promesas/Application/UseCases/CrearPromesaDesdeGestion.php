<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Application\UseCases;

use App\Modules\Promesas\Application\DTOs\CrearPromesaDesdeGestionInput;
use App\Modules\Promesas\Domain\Contracts\PromesaRepository;
use App\Modules\Promesas\Domain\Entities\Promesa;
use App\Modules\Promesas\Domain\Events\PromesaCreada;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class CrearPromesaDesdeGestion
{
    public function __construct(
        private PromesaRepository $repositorio,
        private Dispatcher $eventos,
    ) {
    }

    public function execute(CrearPromesaDesdeGestionInput $input): void
    {
        $promesa = Promesa::crear(
            publicId: $input->publicId,
            productoId: $input->productoId,
            gestionOrigenId: $input->gestionOrigenId,
            usuarioId: $input->usuarioId,
            tipoPagoId: $input->tipoPagoId,
            monto: $input->monto,
            fecha: $input->fecha,
            creadaEn: $input->creadaEn,
        );

        $persistida = $this->repositorio->save($promesa);

        $this->eventos->dispatch(new PromesaCreada(
            promesaId: $persistida->id,
            publicId: $persistida->publicId,
            productoId: $persistida->productoId,
            gestionOrigenId: $persistida->gestionOrigenId,
            usuarioId: $persistida->usuarioId,
            monto: $persistida->monto,
            fecha: $persistida->fecha,
            creadaEn: $persistida->creadaEn,
        ));
    }
}
