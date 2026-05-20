<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Application\Services\InferidorTiposColumnas;
use App\Modules\Importaciones\Domain\Catalogo\CatalogoCamposSistema;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;

/**
 * Input DTO para InferirEsquemaDesdeHeaders.
 */
final readonly class InferirEsquemaInput
{
    /**
     * @param list<string> $headers
     * @param list<array<string, string>> $filasMuestra
     */
    public function __construct(
        public array $headers,
        public array $filasMuestra,
        public TargetImportacion $target,
        public int $proyectoId,
        public ?int $carteraId,
    ) {}
}
