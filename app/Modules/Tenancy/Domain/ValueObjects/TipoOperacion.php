<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\ValueObjects;

enum TipoOperacion: string
{
    case COBRANZA = 'cobranza';
    case CX = 'cx';
    case VENTA = 'venta';
    case SERVICIO = 'servicio';

    /**
     * Cómo llama esta operación a lo que trabaja.
     *
     * «Caso» es correcto en el modelo y no significa nada para quien opera: en
     * cobranza es una cuenta, en soporte un ticket y en ventas una oportunidad.
     * Se devuelve la CLAVE de traducción, no el texto: la decisión de idioma no
     * es del dominio.
     */
    public function claveEtiqueta(): string
    {
        return 'casos.entidad_singular.'.$this->value;
    }

    public function claveEtiquetaPlural(): string
    {
        return 'casos.entidad_plural.'.$this->value;
    }

    /**
     * El artículo indeterminado que acompaña a la etiqueta.
     *
     * Hace falta porque el género no es del dominio sino del idioma: en español
     * es «una cuenta» pero «un ticket», y en inglés «an account» pero
     * «a ticket». Quien traduce decide, no quien programa.
     */
    public function claveArticulo(): string
    {
        return 'casos.entidad_articulo.'.$this->value;
    }

    /**
     * La etiqueta a partir del valor crudo que guarda la base, tolerando basura.
     * Las vistas leen `proyectos.tipo_operacion` como string, no como enum.
     */
    public static function etiquetaDe(?string $tipo, bool $plural = false): string
    {
        $caso = $tipo === null ? null : self::tryFrom($tipo);

        if ($caso === null) {
            return __($plural ? 'casos.entidad_plural.generico' : 'casos.entidad_singular.generico');
        }

        return __($plural ? $caso->claveEtiquetaPlural() : $caso->claveEtiqueta());
    }

    public static function articuloDe(?string $tipo): string
    {
        $caso = $tipo === null ? null : self::tryFrom($tipo);

        return __($caso?->claveArticulo() ?? 'casos.entidad_articulo.generico');
    }
}
