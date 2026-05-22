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
        return self::camposBase();
    }

    /**
     * Catálogo mínimo: solo identificación de persona y tipo.
     * TODO lo demás (nombres, apellidos, direcciones, montos, etc.)
     * se maneja como Campos Personalizados para máxima flexibilidad multi-tenant.
     */
    /** @return list<CampoSistema> */
    public static function camposBase(): array
    {
        return [
            new CampoSistema('identificacion', 'Identificación', true, 'string', null, 'Documento de identidad de la persona.', false, '1700000001'),
            new CampoSistema('tipo_identificacion_codigo', 'Tipo de identificación', false, 'codigo_catalogo', 'tipos_identificacion', 'CED, RUC, DNI, NIT o PAS.', false, 'CED'),
        ];
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
}
