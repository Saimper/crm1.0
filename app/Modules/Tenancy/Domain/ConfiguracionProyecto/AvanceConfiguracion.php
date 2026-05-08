<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain\ConfiguracionProyecto;

use InvalidArgumentException;

final readonly class AvanceConfiguracion
{
    /**
     * @var array<string, bool> clave: PasoConfiguracion->value, valor: completado.
     */
    private array $completados;

    /**
     * @param  array<string, bool>  $completadosPorPaso  clave: PasoConfiguracion->value;
     *                                                   los pasos no presentes se asumen no completados.
     */
    public function __construct(array $completadosPorPaso)
    {
        foreach (array_keys($completadosPorPaso) as $clave) {
            if (! is_string($clave) || PasoConfiguracion::tryFrom($clave) === null) {
                throw new InvalidArgumentException(
                    'Clave de paso desconocida: '.(is_scalar($clave) ? (string) $clave : gettype($clave)),
                );
            }
        }

        $normalizado = [];
        foreach (PasoConfiguracion::cases() as $paso) {
            $normalizado[$paso->value] = $completadosPorPaso[$paso->value] ?? false;
        }

        $this->completados = $normalizado;
    }

    public function estaCompletado(PasoConfiguracion $paso): bool
    {
        return $this->completados[$paso->value];
    }

    public function pasoActual(): PasoConfiguracion
    {
        foreach (PasoConfiguracion::cases() as $paso) {
            if ($paso->esOpcional()) {
                continue;
            }
            if (! $this->completados[$paso->value]) {
                return $paso;
            }
        }

        return PasoConfiguracion::RESUMEN;
    }

    public function porcentaje(): int
    {
        $obligatorios = array_filter(
            PasoConfiguracion::cases(),
            static fn (PasoConfiguracion $p): bool => $p->esObligatorio(),
        );

        $total = count($obligatorios);

        if ($total === 0) {
            return 100;
        }

        $hechos = 0;
        foreach ($obligatorios as $paso) {
            if ($this->completados[$paso->value]) {
                $hechos++;
            }
        }

        return (int) floor(($hechos / $total) * 100);
    }

    public function estado(): EstadoConfiguracionProyecto
    {
        if ($this->estaCompleto()) {
            return EstadoConfiguracionProyecto::COMPLETADA;
        }

        foreach (PasoConfiguracion::cases() as $paso) {
            if ($paso === PasoConfiguracion::DATOS_PROYECTO) {
                continue;
            }
            if ($paso->esOpcional()) {
                continue;
            }
            if ($this->completados[$paso->value]) {
                return EstadoConfiguracionProyecto::EN_PROGRESO;
            }
        }

        return EstadoConfiguracionProyecto::BORRADOR;
    }

    public function puedeSaltarA(PasoConfiguracion $paso): bool
    {
        foreach (PasoConfiguracion::cases() as $previo) {
            if ($previo === $paso) {
                break;
            }
            if ($previo->esOpcional()) {
                continue;
            }
            if (! $this->completados[$previo->value]) {
                return false;
            }
        }

        return true;
    }

    public function estaCompleto(): bool
    {
        foreach (PasoConfiguracion::cases() as $paso) {
            if ($paso->esOpcional()) {
                continue;
            }
            if (! $this->completados[$paso->value]) {
                return false;
            }
        }

        return true;
    }
}
