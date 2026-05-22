<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\Importaciones\Domain\Catalogo\CampoSistema;
use App\Modules\Importaciones\Domain\Catalogo\CatalogoCamposSistema;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use PHPUnit\Framework\TestCase;

/**
 * F35-D: catálogo simplificado a lo invariante (cartera + identidad persona + ID único).
 * Campos extras se configuran en Campos Personalizados §7 por cartera.
 */
final class CatalogoCamposSistemaTest extends TestCase
{
    public function test_persona_tiene_solo_campos_basicos(): void
    {
        $codigos = $this->codigos(CatalogoCamposSistema::paraTarget(TargetImportacion::PERSONA));

        $this->assertSame(
            ['identificacion', 'tipo_identificacion_codigo'],
            $codigos,
            'Solo identificación + tipo identificación. Nombres/apellidos/razon_social via Campos Personalizados.'
        );
    }

    public function test_caso_cobranza_minimal(): void
    {
        $codigos = $this->codigos(CatalogoCamposSistema::paraTarget(TargetImportacion::CASO_COBRANZA));

        $this->assertSame(
            ['identificacion', 'tipo_identificacion_codigo'],
            $codigos,
        );
    }

    public function test_caso_ticket_cx_minimal(): void
    {
        $codigos = $this->codigos(CatalogoCamposSistema::paraTarget(TargetImportacion::CASO_TICKET_CX));

        $this->assertSame(
            ['identificacion', 'tipo_identificacion_codigo'],
            $codigos,
        );
    }

    public function test_caso_lead_venta_minimal(): void
    {
        $codigos = $this->codigos(CatalogoCamposSistema::paraTarget(TargetImportacion::CASO_LEAD_VENTA));

        $this->assertSame(
            ['identificacion', 'tipo_identificacion_codigo'],
            $codigos,
        );
    }

    public function test_caso_servicio_minimal(): void
    {
        $codigos = $this->codigos(CatalogoCamposSistema::paraTarget(TargetImportacion::CASO_SERVICIO));

        $this->assertSame(
            ['identificacion', 'tipo_identificacion_codigo'],
            $codigos,
        );
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
