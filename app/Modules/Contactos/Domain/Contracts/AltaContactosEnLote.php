<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Domain\Contracts;

use App\Modules\Contactos\Domain\ValueObjects\ContactoExtraido;

/**
 * Alta de contactos en lote, para quien los saca de otro sitio.
 *
 * Existe como contrato porque quien los extrae —la importación, el comando de
 * relleno— vive en otro módulo y no puede tocar el modelo Eloquent de Contactos
 * (§3, §13.6). Sólo conoce esta interfaz y el value object.
 */
interface AltaContactosEnLote
{
    /**
     * @param  list<ContactoExtraido>  $contactos
     * @return int cuántos se dieron de alta; los repetidos no cuentan
     */
    public function alta(int $proyectoId, int $personaId, array $contactos, string $origen): int;
}
