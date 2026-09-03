<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\Importaciones\Domain\Catalogo\CampoSistema;
use App\Modules\Importaciones\Domain\Catalogo\CatalogoCamposSistema;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use PHPUnit\Framework\TestCase;

/**
 * F41: el catálogo vuelve a cubrir las columnas nativas del núcleo. F35-D lo había
 * reducido a la identificación, con lo que el nombre y los saldos terminaban como
 * Campos Personalizados y las columnas nativas quedaban vacías.
 */
final class CatalogoCamposSistemaTest extends TestCase
{
    public function test_identidad_de_persona_disponible_en_todos_los_targets(): void
    {
        foreach (TargetImportacion::cases() as $target) {
            $codigos = $this->codigos(CatalogoCamposSistema::paraTarget($target));

            $this->assertContains('identificacion', $codigos);
            $this->assertContains('tipo_identificacion_codigo', $codigos);
            $this->assertContains('nombres', $codigos, "El nombre debe ser mapeable en {$target->value}.");
        }
    }

    public function test_persona_expone_las_columnas_nativas_de_identidad(): void
    {
        $codigos = $this->codigos(CatalogoCamposSistema::paraTarget(TargetImportacion::PERSONA));

        $this->assertSame(
            ['identificacion', 'tipo_identificacion_codigo', 'nombres', 'apellidos', 'razon_social'],
            $codigos,
        );
    }

    public function test_cobranza_expone_saldos_y_mora(): void
    {
        $codigos = $this->codigos(CatalogoCamposSistema::paraTarget(TargetImportacion::CASO_COBRANZA));

        foreach (['saldo_total', 'saldo_capital', 'saldo_interes', 'cuota_mensual', 'dias_mora'] as $esperado) {
            $this->assertContains($esperado, $codigos);
        }
    }

    public function test_cada_target_aporta_sus_campos_cti(): void
    {
        $esperados = [
            TargetImportacion::CASO_TICKET_CX->value => 'asunto',
            TargetImportacion::CASO_LEAD_VENTA->value => 'valor_estimado_monto',
            TargetImportacion::CASO_SERVICIO->value => 'tecnico_asignado',
        ];

        foreach ($esperados as $target => $campo) {
            $codigos = $this->codigos(CatalogoCamposSistema::paraTarget(TargetImportacion::from($target)));
            $this->assertContains($campo, $codigos);
        }
    }

    public function test_los_campos_de_un_target_no_se_filtran_a_otro(): void
    {
        $cobranza = $this->codigos(CatalogoCamposSistema::paraTarget(TargetImportacion::CASO_COBRANZA));

        $this->assertNotContains('asunto', $cobranza);
        $this->assertNotContains('tecnico_asignado', $cobranza);
    }

    public function test_no_hay_codigos_repetidos_en_ningun_target(): void
    {
        foreach (TargetImportacion::cases() as $target) {
            $codigos = $this->codigos(CatalogoCamposSistema::paraTarget($target));

            $this->assertSame(array_unique($codigos), $codigos, "Códigos duplicados en {$target->value}.");
        }
    }

    public function test_targets_disponibles_solo_caso_del_tipo_proyecto(): void
    {
        $this->assertSame(
            [TargetImportacion::CASO_COBRANZA],
            CatalogoCamposSistema::targetsDisponibles('cobranza'),
        );
        $this->assertSame(
            [TargetImportacion::CASO_TICKET_CX],
            CatalogoCamposSistema::targetsDisponibles('cx'),
        );
        $this->assertSame(
            [TargetImportacion::CASO_LEAD_VENTA],
            CatalogoCamposSistema::targetsDisponibles('venta'),
        );
        $this->assertSame(
            [TargetImportacion::CASO_SERVICIO],
            CatalogoCamposSistema::targetsDisponibles('servicio'),
        );
    }

    public function test_targets_disponibles_tipo_desconocido_vacio(): void
    {
        $this->assertSame([], CatalogoCamposSistema::targetsDisponibles('desconocido'));
    }

    public function test_target_importacion_etiqueta_no_vacia(): void
    {
        foreach (TargetImportacion::cases() as $t) {
            $this->assertNotSame('', $t->etiqueta());
        }
    }

    /**
     * @param  list<CampoSistema>  $campos
     * @return list<string>
     */
    private function codigos(array $campos): array
    {
        return array_map(static fn (CampoSistema $c): string => $c->codigo, $campos);
    }
}
