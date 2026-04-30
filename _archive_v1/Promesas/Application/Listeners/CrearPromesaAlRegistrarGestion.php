<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Application\Listeners;

use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use App\Modules\Promesas\Application\DTOs\CrearPromesaDesdeGestionInput;
use App\Modules\Promesas\Application\UseCases\CrearPromesaDesdeGestion;
use Illuminate\Support\Str;

final readonly class CrearPromesaAlRegistrarGestion
{
    public function __construct(
        private CrearPromesaDesdeGestion $crearPromesa,
    ) {}

    public function handle(GestionRegistrada $evento): void
    {
        if ($evento->datosPromesa === null) {
            return;
        }

        $this->crearPromesa->execute(new CrearPromesaDesdeGestionInput(
            publicId: (string) Str::ulid(),
            productoId: $evento->productoId,
            gestionOrigenId: $evento->gestionId,
            usuarioId: $evento->usuarioId,
            tipoPagoId: null,
            monto: $evento->datosPromesa->monto,
            fecha: $evento->datosPromesa->fecha,
            creadaEn: $evento->creadaEn,
        ));
    }
}
