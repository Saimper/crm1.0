<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Domain\ValueObjects;

enum AmbitoCampo: string
{
    /** ambito_id = cartera_id */
    case CASO = 'caso';
    /** ambito_id = tipo_gestion_id */
    case GESTION = 'gestion';
    /** ambito_id = id numérico mapeado a tipo_compromiso */
    case COMPROMISO = 'compromiso';
    /** ambito_id = entidades_configurables.id (Fase 24) */
    case ENTIDAD_CONFIGURABLE = 'entidad_configurable';
}
