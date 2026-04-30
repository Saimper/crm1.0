<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Constructor\ValueObjects;

final readonly class OrdenReporte
{
    public function __construct(
        public string $campo,
        public string $direccion = 'asc',
    ) {
        if (! in_array($direccion, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('Dirección debe ser asc o desc.');
        }
    }

    /**
     * @return array{campo: string, direccion: string}
     */
    public function toArray(): array
    {
        return ['campo' => $this->campo, 'direccion' => $this->direccion];
    }

    /**
     * @param  array{campo: string, direccion?: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['campo'], $data['direccion'] ?? 'asc');
    }
}
