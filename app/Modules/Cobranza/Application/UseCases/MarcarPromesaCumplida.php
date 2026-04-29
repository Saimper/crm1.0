<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Application\UseCases;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Compromisos\Application\UseCases\MarcarCompromisoCumplido;

/**
 * Wrapper de cobranza sobre MarcarCompromisoCumplido (núcleo). La lógica vive en el núcleo;
 * este wrapper existe para ser el punto de entrada semántico desde la UI de cobranza.
 */
final readonly class MarcarPromesaCumplida
{
    public function __construct(
        private MarcarCompromisoCumplido $nucleo,
    ) {
    }

    public function execute(ResolverCompromisoInput $input): void
    {
        $this->nucleo->execute($input);
    }
}
