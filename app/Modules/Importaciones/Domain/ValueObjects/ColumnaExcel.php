<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\ValueObjects;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;

/**
 * Representa una columna del archivo importado con toda la metadata
 * necesaria para decidir cómo procesarla.
 */
final readonly class ColumnaExcel
{
    public function __construct(
        public string $nombreOriginal,
        public TipoCampo $tipoInferido,
        public ?string $campoSistemaMapeado = null,
        public bool $esIdentificadorPersona = false,
        public AccionColumna $accion = AccionColumna::IGNORAR,
    ) {}

    /**
     * Convierte el nombre original a snake_case lowercase sin caracteres especiales,
     * máximo 60 caracteres.
     */
    public function codigoSugerido(): string
    {
        $codigo = strtolower($this->nombreOriginal);
        $codigo = preg_replace('/[^a-z0-9]+/', '_', $codigo) ?? $codigo;
        $codigo = trim($codigo, '_');
        $codigo = preg_replace('/_+/', '_', $codigo) ?? $codigo;

        return substr($codigo, 0, 60);
    }

    /**
     * Etiqueta legible para mostrar en UI.
     */
    public function etiquetaSugerida(): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $this->nombreOriginal));
    }

    public function esCampoDeSistema(): bool
    {
        return $this->campoSistemaMapeado !== null;
    }

    public function debePersistirse(): bool
    {
        return $this->accion !== AccionColumna::IGNORAR;
    }
}
