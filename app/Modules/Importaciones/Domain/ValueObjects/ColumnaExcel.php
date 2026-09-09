<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\ValueObjects;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\RolContacto;
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
        /**
         * Si además de lo que se haga con la columna, sus valores generan
         * contactos de la persona. Es ortogonal a `accion`: una columna de
         * teléfonos suele guardarse también como campo personalizado para que
         * siga viéndose en la ficha.
         */
        public RolContacto $rolContacto = RolContacto::NINGUNO,
    ) {}

    public function generaContactos(): bool
    {
        return $this->rolContacto !== RolContacto::NINGUNO;
    }

    /**
     * Con qué clave viaja el valor de esta columna en el `payload` de la fila.
     *
     * Es UNA función y no una expresión repetida porque la escriben tres sitios
     * (el wizard al construir el payload, el motor al leerlo y la descarga de
     * filas rechazadas al devolverlo) y la única forma de que un supervisor
     * pueda corregir el CSV descargado y volver a subirlo con el mismo mapeo es
     * que los tres coincidan siempre.
     */
    public function clavePayload(): string
    {
        return $this->accion === AccionColumna::MAPEAR_SISTEMA && $this->campoSistemaMapeado !== null
            ? $this->campoSistemaMapeado
            : $this->codigoSugerido();
    }

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

    /**
     * Si el valor de esta columna tiene que viajar en el payload de la fila.
     *
     * Una columna ignorada con rol de contacto también: «ignorar» dice que no
     * es un campo del caso ni de la persona, y el rol dice que de ella salen
     * teléfonos o correos. Si no viajara, `generarContactos` la buscaría en el
     * payload y no la encontraría, y el archivo cargaría sin un solo contacto
     * sin decir nada.
     */
    public function debePersistirse(): bool
    {
        return $this->accion !== AccionColumna::IGNORAR || $this->generaContactos();
    }
}
