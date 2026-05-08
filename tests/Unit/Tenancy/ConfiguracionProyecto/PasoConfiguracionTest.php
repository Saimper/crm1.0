<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy\ConfiguracionProyecto;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\PasoConfiguracion;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use PHPUnit\Framework\TestCase;

final class PasoConfiguracionTest extends TestCase
{
    public function test_orden_completo_de_nueve_pasos(): void
    {
        $todos = PasoConfiguracion::todos();

        $this->assertCount(9, $todos);
        $this->assertSame([
            PasoConfiguracion::DATOS_PROYECTO,
            PasoConfiguracion::CARTERAS,
            PasoConfiguracion::ESTADOS_CASO,
            PasoConfiguracion::TIPOS_GESTION,
            PasoConfiguracion::RESULTADOS,
            PasoConfiguracion::MOTIVOS_NO_CONTACTO,
            PasoConfiguracion::CATALOGOS_TIPO,
            PasoConfiguracion::CAMPOS_PERSONALIZADOS,
            PasoConfiguracion::RESUMEN,
        ], $todos);
    }

    public function test_indice_va_de_uno_a_nueve_segun_orden(): void
    {
        $this->assertSame(1, PasoConfiguracion::DATOS_PROYECTO->indice());
        $this->assertSame(2, PasoConfiguracion::CARTERAS->indice());
        $this->assertSame(5, PasoConfiguracion::RESULTADOS->indice());
        $this->assertSame(8, PasoConfiguracion::CAMPOS_PERSONALIZADOS->indice());
        $this->assertSame(9, PasoConfiguracion::RESUMEN->indice());
    }

    public function test_siguiente_avanza_secuencialmente(): void
    {
        $this->assertSame(PasoConfiguracion::CARTERAS, PasoConfiguracion::DATOS_PROYECTO->siguiente());
        $this->assertSame(PasoConfiguracion::ESTADOS_CASO, PasoConfiguracion::CARTERAS->siguiente());
        $this->assertSame(PasoConfiguracion::RESUMEN, PasoConfiguracion::CAMPOS_PERSONALIZADOS->siguiente());
    }

    public function test_siguiente_de_resumen_es_null(): void
    {
        $this->assertNull(PasoConfiguracion::RESUMEN->siguiente());
    }

    public function test_anterior_retrocede_secuencialmente(): void
    {
        $this->assertSame(PasoConfiguracion::DATOS_PROYECTO, PasoConfiguracion::CARTERAS->anterior());
        $this->assertSame(PasoConfiguracion::CAMPOS_PERSONALIZADOS, PasoConfiguracion::RESUMEN->anterior());
    }

    public function test_anterior_de_datos_proyecto_es_null(): void
    {
        $this->assertNull(PasoConfiguracion::DATOS_PROYECTO->anterior());
    }

    public function test_etiqueta_no_vacia_para_cada_paso(): void
    {
        foreach (PasoConfiguracion::cases() as $paso) {
            $this->assertNotSame('', $paso->etiqueta());
        }
    }

    public function test_solo_campos_personalizados_es_opcional(): void
    {
        foreach (PasoConfiguracion::cases() as $paso) {
            if ($paso === PasoConfiguracion::CAMPOS_PERSONALIZADOS) {
                $this->assertTrue($paso->esOpcional());
                $this->assertFalse($paso->esObligatorio());
            } else {
                $this->assertFalse($paso->esOpcional());
                $this->assertTrue($paso->esObligatorio());
            }
        }
    }

    public function test_subpasos_catalogos_por_tipo_cobranza(): void
    {
        $this->assertSame(
            ['tramos_mora', 'tipos_pago'],
            PasoConfiguracion::subPasosCatalogosPorTipo(TipoOperacion::COBRANZA),
        );
    }

    public function test_subpasos_catalogos_por_tipo_cx(): void
    {
        $this->assertSame(
            ['categorias_ticket', 'prioridades_ticket', 'niveles_sla', 'niveles_escalamiento'],
            PasoConfiguracion::subPasosCatalogosPorTipo(TipoOperacion::CX),
        );
    }

    public function test_subpasos_catalogos_por_tipo_venta(): void
    {
        $this->assertSame(
            ['productos_venta', 'etapas_embudo'],
            PasoConfiguracion::subPasosCatalogosPorTipo(TipoOperacion::VENTA),
        );
    }

    public function test_subpasos_catalogos_por_tipo_servicio(): void
    {
        $this->assertSame(
            ['tipos_accion_servicio', 'estados_tecnicos'],
            PasoConfiguracion::subPasosCatalogosPorTipo(TipoOperacion::SERVICIO),
        );
    }
}
