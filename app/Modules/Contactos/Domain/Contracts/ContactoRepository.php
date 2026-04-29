<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Domain\Contracts;

use App\Modules\Contactos\Domain\Entities\Contacto;

interface ContactoRepository
{
    public function save(Contacto $contacto): Contacto;

    public function existeValorParaPersona(
        int $proyectoId,
        int $personaId,
        string $valor,
    ): bool;
}
