<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\ConfiguracionProyecto;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\Contracts\VerificadorPasoConfiguracion;
use InvalidArgumentException;

final readonly class CalculadorAvanceConfiguracion
{
    /**
     * @var array<string, VerificadorPasoConfiguracion>
     */
    private array $verificadoresPorPaso;

    /**
     * @param  iterable<VerificadorPasoConfiguracion>  $verificadores  un verificador por paso (cobertura completa).
     */
    public function __construct(iterable $verificadores)
    {
        $mapa = [];
        foreach ($verificadores as $verificador) {
            $clave = $verificador->paso()->value;
            if (isset($mapa[$clave])) {
                throw new InvalidArgumentException("Verificador duplicado para paso {$clave}.");
            }
            $mapa[$clave] = $verificador;
        }

        foreach (PasoConfiguracion::cases() as $paso) {
            if (! isset($mapa[$paso->value])) {
                throw new InvalidArgumentException("Falta verificador para paso {$paso->value}.");
            }
        }

        $this->verificadoresPorPaso = $mapa;
    }

    public function calcular(int $proyectoId): AvanceConfiguracion
    {
        $mapa = [];
        foreach (PasoConfiguracion::cases() as $paso) {
            $mapa[$paso->value] = $this->verificadoresPorPaso[$paso->value]->estaCompletoParaProyecto($proyectoId);
        }

        return new AvanceConfiguracion($mapa);
    }
}
