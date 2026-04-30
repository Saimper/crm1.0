<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Constructor\ValueObjects;

use App\Modules\Reportes\Domain\Constructor\Enums\Agregacion;

final readonly class ColumnaReporte
{
    public function __construct(
        public string $campo,
        public string $etiqueta,
        public ?Agregacion $agregacion = null,
    ) {}

    /**
     * @return array{campo: string, etiqueta: string, agregacion: ?string}
     */
    public function toArray(): array
    {
        return [
            'campo' => $this->campo,
            'etiqueta' => $this->etiqueta,
            'agregacion' => $this->agregacion?->value,
        ];
    }

    /**
     * @param  array{campo: string, etiqueta: string, agregacion?: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['campo'],
            $data['etiqueta'],
            isset($data['agregacion']) && $data['agregacion'] !== null
                ? Agregacion::from($data['agregacion'])
                : null,
        );
    }
}
