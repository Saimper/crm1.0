<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy\ConfiguracionProyecto;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\CalculadorAvanceConfiguracion;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\Contracts\VerificadorPasoConfiguracion;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\EstadoConfiguracionProyecto;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CalculadorAvanceConfiguracionTest extends TestCase
{
    public function test_lanza_si_falta_verificador_para_algun_paso(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CalculadorAvanceConfiguracion([
            new VerificadorFake(PasoConfiguracion::DATOS_PROYECTO, [1 => true]),
        ]);
    }

    public function test_lanza_si_hay_verificador_duplicado(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $verificadores = $this->verificadoresCompletos();
        $verificadores[] = new VerificadorFake(PasoConfiguracion::DATOS_PROYECTO, [1 => true]);

        new CalculadorAvanceConfiguracion($verificadores);
    }

    public function test_calcular_devuelve_avance_segun_verificadores(): void
    {
        $verificadores = $this->verificadoresPersonalizados([
            PasoConfiguracion::DATOS_PROYECTO->value => true,
            PasoConfiguracion::CARTERAS->value => true,
            PasoConfiguracion::ESTADOS_CASO->value => false,
            PasoConfiguracion::TIPOS_GESTION->value => false,
            PasoConfiguracion::RESULTADOS->value => false,
            PasoConfiguracion::MOTIVOS_NO_CONTACTO->value => false,
            PasoConfiguracion::CATALOGOS_TIPO->value => false,
            PasoConfiguracion::CAMPOS_PERSONALIZADOS->value => false,
            PasoConfiguracion::RESUMEN->value => false,
        ]);

        $calculador = new CalculadorAvanceConfiguracion($verificadores);
        $avance = $calculador->calcular(7);

        $this->assertTrue($avance->estaCompletado(PasoConfiguracion::DATOS_PROYECTO));
        $this->assertTrue($avance->estaCompletado(PasoConfiguracion::CARTERAS));
        $this->assertFalse($avance->estaCompletado(PasoConfiguracion::ESTADOS_CASO));
        $this->assertSame(PasoConfiguracion::ESTADOS_CASO, $avance->pasoActual());
        $this->assertSame(EstadoConfiguracionProyecto::EN_PROGRESO, $avance->estado());
    }

    public function test_calcular_invoca_verificador_con_proyecto_id_correcto(): void
    {
        $verificadores = $this->verificadoresCompletos();
        $calculador = new CalculadorAvanceConfiguracion($verificadores);

        $calculador->calcular(99);

        foreach ($verificadores as $verificador) {
            $this->assertSame([99], $verificador->idsConsultados);
        }
    }

    public function test_calcular_distintos_proyectos_devuelve_resultados_distintos(): void
    {
        $verificadores = $this->verificadoresPorProyecto([
            42 => true,
            99 => false,
        ]);

        $calculador = new CalculadorAvanceConfiguracion($verificadores);

        $avance42 = $calculador->calcular(42);
        $avance99 = $calculador->calcular(99);

        $this->assertTrue($avance42->estaCompleto());
        $this->assertFalse($avance99->estaCompleto());
    }

    /**
     * @return list<VerificadorFake>
     */
    private function verificadoresCompletos(): array
    {
        $lista = [];
        foreach (PasoConfiguracion::cases() as $paso) {
            $lista[] = new VerificadorFake($paso, []);
        }

        return $lista;
    }

    /**
     * @param  array<string, bool>  $resultados  clave: PasoConfiguracion->value.
     * @return list<VerificadorFake>
     */
    private function verificadoresPersonalizados(array $resultados): array
    {
        $lista = [];
        foreach (PasoConfiguracion::cases() as $paso) {
            $lista[] = new VerificadorFake(
                $paso,
                ['*' => $resultados[$paso->value] ?? false],
            );
        }

        return $lista;
    }

    /**
     * @param  array<int, bool>  $porProyecto  clave: proyectoId, valor: completado para todos los pasos.
     * @return list<VerificadorFake>
     */
    private function verificadoresPorProyecto(array $porProyecto): array
    {
        $lista = [];
        foreach (PasoConfiguracion::cases() as $paso) {
            $lista[] = new VerificadorFake($paso, $porProyecto);
        }

        return $lista;
    }
}

final class VerificadorFake implements VerificadorPasoConfiguracion
{
    /**
     * @var list<int>
     */
    public array $idsConsultados = [];

    /**
     * @param  array<int|string, bool>  $resultadosPorProyecto  usa la clave '*' como fallback global.
     */
    public function __construct(
        private readonly PasoConfiguracion $paso,
        private readonly array $resultadosPorProyecto,
    ) {}

    public function paso(): PasoConfiguracion
    {
        return $this->paso;
    }

    public function estaCompletoParaProyecto(int $proyectoId): bool
    {
        $this->idsConsultados[] = $proyectoId;

        if (array_key_exists($proyectoId, $this->resultadosPorProyecto)) {
            return $this->resultadosPorProyecto[$proyectoId];
        }

        if (array_key_exists('*', $this->resultadosPorProyecto)) {
            return $this->resultadosPorProyecto['*'];
        }

        return true;
    }
}
