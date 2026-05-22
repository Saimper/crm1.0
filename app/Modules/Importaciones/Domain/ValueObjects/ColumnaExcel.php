<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\ValueObjects;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Services\NormalizadorEtiqueta;
use Normalizer;

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
        public bool $esIdentificadorCaso = false,
        public AccionColumna $accion = AccionColumna::IGNORAR,
        public ?string $etiquetaPersonalizada = null,
    ) {}

    /**
     * Convierte el nombre original a snake_case lowercase sin caracteres especiales,
     * máximo 60 caracteres.
     */
    public function codigoSugerido(): string
    {
        $codigo = mb_strtolower($this->nombreOriginal, 'UTF-8');
        $codigo = Normalizer::normalize($codigo, Normalizer::FORM_D);
        $codigo = (string) preg_replace('/\p{Mn}/u', '', $codigo);
        $codigo = (string) preg_replace('/[^a-z0-9]+/', '_', $codigo);
        $codigo = trim($codigo, '_');
        $codigo = (string) preg_replace('/_+/', '_', $codigo);

        return substr($codigo, 0, 60);
    }

    /**
     * Etiqueta legible para mostrar en UI.
     * Si el usuario definió una personalizada, la usa; si no, deriva del header.
     */
    public function etiquetaSugerida(): string
    {
        return $this->etiquetaPersonalizada
            ?? (new NormalizadorEtiqueta)->sugerir($this->nombreOriginal);
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
