<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy\ConfiguracionProyecto;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\AvanceConfiguracion;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\EstadoConfiguracionProyecto;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AvanceConfiguracionTest extends TestCase
{
    public function test_pasos_no_presentes_se_consideran_no_completados(): void
    {
        $avance = new AvanceConfiguracion([]);

        foreach (PasoConfiguracion::cases() as $paso) {
            $this->assertFalse($avance->estaCompletado($paso));
        }
    }

    public function test_clave_invalida_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AvanceConfiguracion(['paso_inexistente' => true]);
    }

    public function test_porcentaje_cero_cuando_nada_completado(): void
    {
        $avance = new AvanceConfiguracion([]);

        $this->assertSame(0, $avance->porcentaje());
    }

    public function test_porcentaje_solo_considera_obligatorios(): void
    {
        // 8 pasos obligatorios. Solo CAMPOS_PERSONALIZADOS hecho (es opcional, no cuenta).
        $avance = new AvanceConfiguracion([
            PasoConfiguracion::CAMPOS_PERSONALIZADOS->value => true,
        ]);

        $this->assertSame(0, $avance->porcentaje());
    }

    public function test_porcentaje_con_la_mitad_de_obligatorios(): void
    {
        $avance = new AvanceConfiguracion([
            PasoConfiguracion::DATOS_PROYECTO->value => true,
            PasoConfiguracion::CARTERAS->value => true,
            PasoConfiguracion::ESTADOS_CASO->value => true,
            PasoConfiguracion::TIPOS_GESTION->value => true,
        ]);

        // 4 de 8 obligatorios = 50%.
        $this->assertSame(50, $avance->porcentaje());
    }

    public function test_porcentaje_cien_cuando_todos_obligatorios(): void
    {
        $avance = $this->avanceConTodosObligatorios();

        $this->assertSame(100, $avance->porcentaje());
    }

    public function test_paso_actual_es_primer_obligatorio_no_completado(): void
    {
        $avance = new AvanceConfiguracion([
            PasoConfiguracion::DATOS_PROYECTO->value => true,
            PasoConfiguracion::CARTERAS->value => true,
        ]);

        $this->assertSame(PasoConfiguracion::ESTADOS_CASO, $avance->pasoActual());
    }

    public function test_paso_actual_salta_opcionales(): void
    {
        $mapa = $this->todosObligatoriosHechos();
        // CAMPOS_PERSONALIZADOS opcional sigue en false; pasoActual no debería caer en él.
        unset($mapa[PasoConfiguracion::RESUMEN->value]);
        $mapa[PasoConfiguracion::RESUMEN->value] = false;

        $avance = new AvanceConfiguracion($mapa);

        $this->assertSame(PasoConfiguracion::RESUMEN, $avance->pasoActual());
    }

    public function test_paso_actual_devuelve_resumen_si_todo_obligatorio_completo(): void
    {
        $avance = $this->avanceConTodosObligatorios();

        $this->assertSame(PasoConfiguracion::RESUMEN, $avance->pasoActual());
    }

    public function test_estado_borrador_solo_paso_uno_hecho(): void
    {
        $avance = new AvanceConfiguracion([
            PasoConfiguracion::DATOS_PROYECTO->value => true,
        ]);

        $this->assertSame(EstadoConfiguracionProyecto::BORRADOR, $avance->estado());
    }

    public function test_estado_borrador_si_nada_hecho(): void
    {
        $avance = new AvanceConfiguracion([]);

        $this->assertSame(EstadoConfiguracionProyecto::BORRADOR, $avance->estado());
    }

    public function test_estado_borrador_aunque_un_opcional_este_hecho(): void
    {
        $avance = new AvanceConfiguracion([
            PasoConfiguracion::DATOS_PROYECTO->value => true,
            PasoConfiguracion::CAMPOS_PERSONALIZADOS->value => true,
        ]);

        $this->assertSame(EstadoConfiguracionProyecto::BORRADOR, $avance->estado());
    }

    public function test_estado_en_progreso_si_hay_obligatorio_posterior_a_paso_uno(): void
    {
        $avance = new AvanceConfiguracion([
            PasoConfiguracion::DATOS_PROYECTO->value => true,
            PasoConfiguracion::CARTERAS->value => true,
        ]);

        $this->assertSame(EstadoConfiguracionProyecto::EN_PROGRESO, $avance->estado());
    }

    public function test_estado_completada_cuando_todos_obligatorios(): void
    {
        $avance = $this->avanceConTodosObligatorios();

        $this->assertSame(EstadoConfiguracionProyecto::COMPLETADA, $avance->estado());
    }

    public function test_puede_saltar_a_paso_si_todos_los_previos_obligatorios_completos(): void
    {
        $avance = new AvanceConfiguracion([
            PasoConfiguracion::DATOS_PROYECTO->value => true,
            PasoConfiguracion::CARTERAS->value => true,
            PasoConfiguracion::ESTADOS_CASO->value => true,
        ]);

        $this->assertTrue($avance->puedeSaltarA(PasoConfiguracion::TIPOS_GESTION));
    }

    public function test_no_puede_saltar_si_falta_obligatorio_previo(): void
    {
        $avance = new AvanceConfiguracion([
            PasoConfiguracion::DATOS_PROYECTO->value => true,
            // CARTERAS faltante.
            PasoConfiguracion::ESTADOS_CASO->value => true,
        ]);

        $this->assertFalse($avance->puedeSaltarA(PasoConfiguracion::TIPOS_GESTION));
    }

    public function test_puede_saltar_al_paso_actual(): void
    {
        $avance = new AvanceConfiguracion([
            PasoConfiguracion::DATOS_PROYECTO->value => true,
        ]);

        $this->assertTrue($avance->puedeSaltarA(PasoConfiguracion::CARTERAS));
    }

    public function test_optional_no_bloquea_salto(): void
    {
        $mapa = $this->todosObligatoriosHechos();
        $mapa[PasoConfiguracion::CAMPOS_PERSONALIZADOS->value] = false;

        $avance = new AvanceConfiguracion($mapa);

        $this->assertTrue($avance->puedeSaltarA(PasoConfiguracion::RESUMEN));
    }

    public function test_esta_completo_solo_si_todos_obligatorios(): void
    {
        $this->assertTrue($this->avanceConTodosObligatorios()->estaCompleto());

        $incompleto = new AvanceConfiguracion([
            PasoConfiguracion::DATOS_PROYECTO->value => true,
        ]);
        $this->assertFalse($incompleto->estaCompleto());
    }

    public function test_esta_completo_ignora_opcionales(): void
    {
        $mapa = $this->todosObligatoriosHechos();
        // CAMPOS_PERSONALIZADOS opcional no se setea — sigue false.

        $avance = new AvanceConfiguracion($mapa);

        $this->assertTrue($avance->estaCompleto());
    }

    /**
     * @return array<string, bool>
     */
    private function todosObligatoriosHechos(): array
    {
        $mapa = [];
        foreach (PasoConfiguracion::cases() as $paso) {
            $mapa[$paso->value] = $paso->esObligatorio();
        }

        return $mapa;
    }

    private function avanceConTodosObligatorios(): AvanceConfiguracion
    {
        return new AvanceConfiguracion($this->todosObligatoriosHechos());
    }
}
