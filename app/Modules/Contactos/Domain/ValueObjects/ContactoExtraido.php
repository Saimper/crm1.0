<?php

declare(strict_types=1);

namespace App\Modules\Contactos\Domain\ValueObjects;

/**
 * Un contacto sacado de una celda de texto libre, ya normalizado.
 */
final readonly class ContactoExtraido
{
    public function __construct(
        public TipoContacto $tipo,
        public string $valor,
        public ?string $etiqueta = null,
    ) {}

    /** Clave para deduplicar: el mismo número no se guarda dos veces por persona. */
    public function clave(): string
    {
        return $this->tipo->value.':'.$this->valor;
    }
}
