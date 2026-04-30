<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Constructor\ValueObjects;

use App\Modules\Reportes\Domain\Constructor\Enums\OperadorFiltro;

final readonly class FiltroReporte
{
    public function __construct(
        public string $campo,
        public OperadorFiltro $operador,
        public mixed $valor = null,
    ) {}

    /**
     * @return array{campo: string, operador: string, valor: mixed}
     */
    public function toArray(): array
    {
        return [
            'campo' => $this->campo,
            'operador' => $this->operador->value,
            'valor' => $this->valor,
        ];
    }

    /**
     * @param  array{campo: string, operador: string, valor?: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['campo'],
            OperadorFiltro::from($data['operador']),
            $data['valor'] ?? null,
        );
    }
}
