<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Domain\ValueObjects;

enum TipoCampo: string
{
    case TEXTO_CORTO        = 'texto_corto';
    case TEXTO_LARGO        = 'texto_largo';
    case NUMERO_ENTERO      = 'numero_entero';
    case NUMERO_DECIMAL     = 'numero_decimal';
    case FECHA              = 'fecha';
    case FECHA_HORA         = 'fecha_hora';
    case BOOLEANO           = 'booleano';
    case SELECCION_UNICA    = 'seleccion_unica';
    case SELECCION_MULTIPLE = 'seleccion_multiple';
    case MONEDA             = 'moneda';

    public function requiereOpciones(): bool
    {
        return $this === self::SELECCION_UNICA || $this === self::SELECCION_MULTIPLE;
    }
}
