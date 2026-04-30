<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Domain\ValueObjects;

enum TipoContacto: string
{
    case TELEFONO = 'telefono';
    case CORREO = 'correo';
    case DIRECCION = 'direccion';
}
