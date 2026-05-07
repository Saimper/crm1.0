<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\Importaciones\Domain\Catalogo\CampoSistema;
use App\Modules\Importaciones\Domain\Catalogo\CatalogoCamposSistema;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use PHPUnit\Framework\TestCase;

final class CatalogoCamposSistemaTest extends TestCase
{
    public function test_persona_incluye_campos_minimos_requeridos(): void
    {
        $codigos = $this->codigos(CatalogoCamposSistema::paraTarget(TargetImportacion::PERSONA));

        $this->assertContains('tipo_identificacion_codigo', $codigos);
        $this->assertContains('identificacion', $codigos);
        $this->assertContains('nombres', $codigos);
        $this->assertContains('razon_social', $codigos);
        $this->assertContains('fecha_nacimiento', $codigos);
    }

    public function test_persona_tipo_persona_es_opcional(): void
    {
        $tipoPersona = $this->buscar(CatalogoCamposSistema::paraTarget(TargetImportacion::PERSONA), 'tipo_persona');
        $this->assertNotNull($tipoPersona);
        $this->assertFalse($tipoPersona->requerido, 'tipo_persona se infiere; no debe ser obligatorio mapearlo');
    }

    public function test_caso_cobranza_tiene_keys_que_usecase_espera(): void
    {
        $codigos = $this->codigos(CatalogoCamposSistema::paraTarget(TargetImportacion::CASO_COBRANZA));

        foreach (['cartera_codigo', 'tipo_identificacion_codigo', 'identificacion', 'numero_prestamo', 'moneda', 'monto_original', 'saldo_capital', 'saldo_total', 'fecha_desembolso', 'fecha_vencimiento'] as $req) {
            $this->assertContains($req, $codigos, "Cobranza debe tener {$req}");
        }
    }

    public function test_caso_cobranza_estado_caso_codigo_y_fecha_ingreso_son_opcionales(): void
    {
        $campos = CatalogoCamposSistema::paraTarget(TargetImportacion::CASO_COBRANZA);
        $this->assertFalse($this->buscar($campos, 'estado_caso_codigo')->requerido);
        $this->assertFalse($this->buscar($campos, 'fecha_ingreso')->requerido);
    }

    public function test_caso_ticket_cx_tiene_keys_que_usecase_espera(): void
    {
        $codigos = $this->codigos(CatalogoCamposSistema::paraTarget(TargetImportacion::CASO_TICKET_CX));

        foreach (['cartera_codigo', 'tipo_identificacion_codigo', 'identificacion', 'codigo_ticket', 'asunto', 'fecha_reporte'] as $req) {
            $this->assertContains($req, $codigos);
        }
    }

    public function test_caso_lead_venta_tiene_keys_que_usecase_espera(): void
    {
        $codigos = $this->codigos(CatalogoCamposSistema::paraTarget(TargetImportacion::CASO_LEAD_VENTA));

        foreach (['cartera_codigo', 'tipo_identificacion_codigo', 'identificacion', 'codigo_lead', 'valor_estimado_monto', 'moneda', 'fecha_primer_contacto'] as $req) {
            $this->assertContains($req, $codigos);
        }
    }

    public function test_caso_servicio_tiene_keys_que_usecase_espera(): void
    {
        $codigos = $this->codigos(CatalogoCamposSistema::paraTarget(TargetImportacion::CASO_SERVICIO));

        foreach (['cartera_codigo', 'tipo_identificacion_codigo', 'identificacion', 'codigo_servicio', 'fecha_solicitud'] as $req) {
            $this->assertContains($req, $codigos);
        }
    }

    public function test_targets_disponibles_segun_tipo_operacion(): void
    {
        $this->assertSame(
            [TargetImportacion::PERSONA, TargetImportacion::CASO_COBRANZA],
            CatalogoCamposSistema::targetsDisponibles('cobranza'),
        );
        $this->assertSame(
            [TargetImportacion::PERSONA, TargetImportacion::CASO_TICKET_CX],
            CatalogoCamposSistema::targetsDisponibles('cx'),
        );
        $this->assertSame(
            [TargetImportacion::PERSONA, TargetImportacion::CASO_LEAD_VENTA],
            CatalogoCamposSistema::targetsDisponibles('venta'),
        );
        $this->assertSame(
            [TargetImportacion::PERSONA, TargetImportacion::CASO_SERVICIO],
            CatalogoCamposSistema::targetsDisponibles('servicio'),
        );
    }

    public function test_targets_disponibles_tipo_desconocido_solo_persona(): void
    {
        $this->assertSame(
            [TargetImportacion::PERSONA],
            CatalogoCamposSistema::targetsDisponibles('desconocido'),
        );
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

    /** @param list<CampoSistema> $campos */
    private function buscar(array $campos, string $codigo): ?CampoSistema
    {
        foreach ($campos as $c) {
            if ($c->codigo === $codigo) {
                return $c;
            }
        }

        return null;
    }
}
