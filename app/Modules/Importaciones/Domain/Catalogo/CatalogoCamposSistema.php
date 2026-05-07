<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Catalogo;

use App\Modules\Importaciones\Domain\Enums\TargetImportacion;

/**
 * Catálogo declarativo de campos sistema disponibles para mapear desde columnas CSV/XLSX.
 *
 * F35-D: simplificado a lo invariante. El admin del proyecto decide qué campos extra pedir
 * vía Campos Personalizados §7 (ámbito caso × cartera). La importación actualmente se
 * limita a crear el caso con su identificador único + identidad persona + cartera.
 * Para datos CTI extras (saldos, fechas, asunto, etc.) el cliente debe configurar Campos
 * Personalizados en la cartera y será otro mecanismo de carga (UI de edición o futura
 * importación-CP por cartera).
 *
 * Auto-defaults aplicados en Livewire al construir payload (no requieren mapeo):
 *   - persona.tipo_persona: inferido de nombres/razon_social.
 *   - caso.estado_caso_codigo: primer estado activo del proyecto.
 *   - caso.fecha_ingreso: hoy.
 */
final class CatalogoCamposSistema
{
    /** @return list<CampoSistema> */
    public static function paraTarget(TargetImportacion $target): array
    {
        return match ($target) {
            TargetImportacion::PERSONA => self::campoPersona(),
            TargetImportacion::CASO_COBRANZA => self::campoCaso('numero_prestamo', 'Número de préstamo', 'PR-0001'),
            TargetImportacion::CASO_TICKET_CX => self::campoCaso('codigo_ticket', 'Código de ticket', 'TKT-0001'),
            TargetImportacion::CASO_LEAD_VENTA => self::campoCaso('codigo_lead', 'Código de lead', 'LD-0001'),
            TargetImportacion::CASO_SERVICIO => self::campoCaso('codigo_servicio', 'Código de servicio', 'SV-0001'),
        };
    }

    /**
     * @return list<TargetImportacion>
     */
    public static function targetsDisponibles(string $tipoOperacionProyecto): array
    {
        return match ($tipoOperacionProyecto) {
            'cobranza' => [TargetImportacion::CASO_COBRANZA],
            'cx' => [TargetImportacion::CASO_TICKET_CX],
            'venta' => [TargetImportacion::CASO_LEAD_VENTA],
            'servicio' => [TargetImportacion::CASO_SERVICIO],
            default => [],
        };
    }

    /** @return list<CampoSistema> */
    private static function campoPersona(): array
    {
        return [
            new CampoSistema('tipo_identificacion_codigo', 'Tipo de identificación', true, 'codigo_catalogo', 'tipos_identificacion', 'CED, RUC, DNI, NIT o PAS.', false, 'CED'),
            new CampoSistema('identificacion', 'Identificación', true, 'string', null, 'Documento de identidad de la persona.', false, '1700000001'),
            new CampoSistema('nombres', 'Nombres', false, 'string', null, 'Nombre(s) de la persona.', false, 'Ana María'),
            new CampoSistema('apellidos', 'Apellidos', false, 'string', null, null, false, 'Pérez González'),
        ];
    }

    /**
     * Campos para importar un caso: identidad de persona + cartera + identificador único.
     * Lo extra del CTI lo configura el admin del proyecto vía Campos Personalizados §7.
     *
     * @return list<CampoSistema>
     */
    private static function campoCaso(string $codigoIdUnico, string $etiquetaIdUnico, string $ejemploIdUnico): array
    {
        return [
            new CampoSistema('cartera_codigo', 'Cartera', true, 'codigo_catalogo', 'carteras', 'Código de la cartera del proyecto.', false, 'CARTERA_A'),
            new CampoSistema('tipo_identificacion_codigo', 'Tipo de identificación', true, 'codigo_catalogo', 'tipos_identificacion', 'CED, RUC, DNI, NIT o PAS.', false, 'CED'),
            new CampoSistema('identificacion', 'Identificación', true, 'string', null, 'Si la persona no existe, se crea automáticamente.', false, '1700000001'),
            new CampoSistema('nombres', 'Nombres', false, 'string', null, 'Obligatorio para crear persona física nueva.', false, 'Ana María'),
            new CampoSistema('apellidos', 'Apellidos', false, 'string', null, null, false, 'Pérez González'),
            new CampoSistema($codigoIdUnico, $etiquetaIdUnico, true, 'string', null, 'Identificador único del caso en el proyecto.', false, $ejemploIdUnico),
        ];
    }
}
