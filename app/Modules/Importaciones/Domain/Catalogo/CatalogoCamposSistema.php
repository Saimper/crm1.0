<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Catalogo;

use App\Modules\Importaciones\Domain\Enums\TargetImportacion;

/**
 * Catálogo declarativo de campos sistema disponibles para mapear desde columnas CSV.
 * Las keys canónicas (campo->codigo) coinciden 1:1 con las que los UseCases procesadores
 * (ProcesarImportacionPersonas, ProcesarImportacionCasos*) leen del payload almacenado en
 * importacion_filas.payload. No agregar campos aquí sin ajustar el procesador correspondiente.
 *
 * Auto-defaults aplicados en Livewire al construir payload (no se exigen mapeo):
 *   - persona.tipo_persona: inferido de razon_social vs nombres.
 *   - caso_*.estado_caso_codigo: primer estado_caso activo del proyecto.
 *   - caso_*.fecha_ingreso: hoy.
 */
final class CatalogoCamposSistema
{
    /** @return list<CampoSistema> */
    public static function paraTarget(TargetImportacion $target): array
    {
        return match ($target) {
            TargetImportacion::PERSONA => self::campoPersona(),
            TargetImportacion::CASO_COBRANZA => self::campoCasoCobranza(),
            TargetImportacion::CASO_TICKET_CX => self::campoCasoTicketCx(),
            TargetImportacion::CASO_LEAD_VENTA => self::campoCasoLeadVenta(),
            TargetImportacion::CASO_SERVICIO => self::campoCasoServicio(),
        };
    }

    /** @return list<TargetImportacion> */
    public static function targetsDisponibles(string $tipoOperacionProyecto): array
    {
        return match ($tipoOperacionProyecto) {
            'cobranza' => [TargetImportacion::PERSONA, TargetImportacion::CASO_COBRANZA],
            'cx' => [TargetImportacion::PERSONA, TargetImportacion::CASO_TICKET_CX],
            'venta' => [TargetImportacion::PERSONA, TargetImportacion::CASO_LEAD_VENTA],
            'servicio' => [TargetImportacion::PERSONA, TargetImportacion::CASO_SERVICIO],
            default => [TargetImportacion::PERSONA],
        };
    }

    /** @return list<CampoSistema> */
    private static function campoPersona(): array
    {
        return [
            new CampoSistema('tipo_identificacion_codigo', 'Tipo de identificación', true, 'codigo_catalogo', 'tipos_identificacion', 'CED, RUC, DNI, PAS según el catálogo global.'),
            new CampoSistema('identificacion', 'Identificación', true, 'string', null, 'Cédula, RUC o documento equivalente.'),
            new CampoSistema('nombres', 'Nombres', false, 'string', null, 'Obligatorio para persona física.'),
            new CampoSistema('apellidos', 'Apellidos', false, 'string'),
            new CampoSistema('razon_social', 'Razón social', false, 'string', null, 'Si está presente, persona se trata como jurídica.'),
            new CampoSistema('tipo_persona', 'Tipo de persona', false, 'string', null, 'Si no se mapea, se infiere: razón social → jurídica, nombres → física.'),
            new CampoSistema('fecha_nacimiento', 'Fecha de nacimiento', false, 'fecha'),
        ];
    }

    /** @return list<CampoSistema> */
    private static function camposBaseCaso(): array
    {
        return [
            new CampoSistema('cartera_codigo', 'Cartera', true, 'codigo_catalogo', 'carteras', 'Código de cartera del proyecto.'),
            new CampoSistema('tipo_identificacion_codigo', 'Tipo de identificación de la persona', true, 'codigo_catalogo', 'tipos_identificacion'),
            new CampoSistema('identificacion', 'Identificación de la persona', true, 'string', null, 'La persona debe existir previamente en el proyecto.'),
            new CampoSistema('estado_caso_codigo', 'Estado del caso', false, 'codigo_catalogo', 'estados_caso', 'Si no se mapea, se asigna el primer estado activo del proyecto.'),
            new CampoSistema('fecha_ingreso', 'Fecha de ingreso', false, 'fecha', null, 'Si no se mapea, se asigna la fecha de hoy.'),
            new CampoSistema('prioridad', 'Prioridad (1-5)', false, 'numero_entero'),
        ];
    }

    /** @return list<CampoSistema> */
    private static function campoCasoCobranza(): array
    {
        return [
            ...self::camposBaseCaso(),
            new CampoSistema('numero_prestamo', 'Número de préstamo', true, 'string', null, 'Identificador único del caso en el proyecto.'),
            new CampoSistema('moneda', 'Moneda (ISO 4217)', true, 'string', null, 'Ej. USD, COP, MXN.'),
            new CampoSistema('monto_original', 'Monto original', true, 'decimal'),
            new CampoSistema('saldo_capital', 'Saldo capital', true, 'decimal'),
            new CampoSistema('saldo_total', 'Saldo total', true, 'decimal'),
            new CampoSistema('fecha_desembolso', 'Fecha de desembolso', true, 'fecha'),
            new CampoSistema('fecha_vencimiento', 'Fecha de vencimiento', true, 'fecha'),
            new CampoSistema('saldo_interes', 'Saldo intereses', false, 'decimal'),
            new CampoSistema('cuota_mensual', 'Cuota mensual', false, 'decimal'),
            new CampoSistema('cuotas_totales', 'Cuotas totales', false, 'numero_entero'),
            new CampoSistema('cuotas_pagadas', 'Cuotas pagadas', false, 'numero_entero'),
            new CampoSistema('dias_mora', 'Días de mora', false, 'numero_entero'),
        ];
    }

    /** @return list<CampoSistema> */
    private static function campoCasoTicketCx(): array
    {
        return [
            ...self::camposBaseCaso(),
            new CampoSistema('codigo_ticket', 'Código de ticket', true, 'string', null, 'Identificador único del caso en el proyecto.'),
            new CampoSistema('asunto', 'Asunto', true, 'string'),
            new CampoSistema('fecha_reporte', 'Fecha de reporte', true, 'fecha'),
            new CampoSistema('descripcion', 'Descripción', false, 'string'),
            new CampoSistema('categoria_codigo', 'Categoría', false, 'codigo_catalogo', 'categorias_ticket'),
            new CampoSistema('prioridad_codigo', 'Prioridad del ticket', false, 'codigo_catalogo', 'prioridades_ticket'),
            new CampoSistema('sla_codigo', 'Nivel de SLA', false, 'codigo_catalogo', 'niveles_sla'),
            new CampoSistema('escalamiento_codigo', 'Nivel de escalamiento', false, 'codigo_catalogo', 'niveles_escalamiento'),
            new CampoSistema('fecha_limite_sla', 'Fecha límite SLA', false, 'fecha_hora'),
        ];
    }

    /** @return list<CampoSistema> */
    private static function campoCasoLeadVenta(): array
    {
        return [
            ...self::camposBaseCaso(),
            new CampoSistema('codigo_lead', 'Código de lead', true, 'string', null, 'Identificador único del caso en el proyecto.'),
            new CampoSistema('valor_estimado_monto', 'Valor estimado', true, 'decimal'),
            new CampoSistema('moneda', 'Moneda (ISO 4217)', true, 'string'),
            new CampoSistema('fecha_primer_contacto', 'Fecha primer contacto', true, 'fecha'),
            new CampoSistema('producto_codigo', 'Producto', false, 'codigo_catalogo', 'productos_venta'),
            new CampoSistema('etapa_codigo', 'Etapa del embudo', false, 'codigo_catalogo', 'etapas_embudo'),
            new CampoSistema('origen_lead', 'Origen del lead', false, 'string'),
            new CampoSistema('fecha_estimada_cierre', 'Fecha estimada de cierre', false, 'fecha'),
        ];
    }

    /** @return list<CampoSistema> */
    private static function campoCasoServicio(): array
    {
        return [
            ...self::camposBaseCaso(),
            new CampoSistema('codigo_servicio', 'Código de servicio', true, 'string', null, 'Identificador único del caso en el proyecto.'),
            new CampoSistema('fecha_solicitud', 'Fecha de solicitud', true, 'fecha'),
            new CampoSistema('tipo_accion_codigo', 'Tipo de acción', false, 'codigo_catalogo', 'tipos_accion_servicio'),
            new CampoSistema('estado_tecnico_codigo', 'Estado técnico', false, 'codigo_catalogo', 'estados_tecnicos'),
            new CampoSistema('direccion_servicio', 'Dirección de servicio', false, 'string'),
            new CampoSistema('tecnico_asignado', 'Técnico asignado', false, 'string'),
            new CampoSistema('fecha_programada', 'Fecha programada', false, 'fecha_hora'),
        ];
    }
}
