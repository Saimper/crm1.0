<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Catalogo;

use App\Modules\Importaciones\Domain\Enums\TargetImportacion;

/**
 * Catálogo declarativo de campos sistema disponibles para mapear desde columnas CSV/XLSX.
 *
 * F41: el catálogo vuelve a cubrir las columnas nativas del núcleo. F35-D lo había
 * reducido a la identificación, con lo que nombre, saldos y demás datos del CTI
 * terminaban como Campos Personalizados aunque la columna ya existiera en el sistema:
 * la persona quedaba sin `nombres` y el caso sin `saldo_total`, y ni el listado de
 * personas ni el de casos podían mostrarlos.
 *
 * La regla ahora es: si el dato tiene columna propia en el núcleo, se mapea a ella.
 * Los Campos Personalizados §7 siguen siendo el mecanismo para todo lo que es
 * específico del mandante y no tiene columna (segmento, gestor, referencias…).
 *
 * Auto-defaults aplicados en Livewire al construir payload (no requieren mapeo):
 *   - persona.tipo_persona: inferido de nombres/razon_social.
 *   - caso.estado_caso_codigo: primer estado activo del proyecto.
 *   - caso.fecha_ingreso: hoy.
 *
 * El identificador único del caso (numero_prestamo, codigo_ticket, …) no vive aquí:
 * se elige aparte en el wizard como "columna identificadora de caso".
 */
final class CatalogoCamposSistema
{
    /** @return list<CampoSistema> */
    public static function paraTarget(TargetImportacion $target): array
    {
        return [...self::camposPersona(), ...self::camposDelTarget($target)];
    }

    /**
     * Identidad de persona: común a todos los targets.
     *
     * @return list<CampoSistema>
     */
    public static function camposBase(): array
    {
        return self::camposPersona();
    }

    /** @return list<CampoSistema> */
    private static function camposPersona(): array
    {
        return [
            new CampoSistema('identificacion', 'Identificación', true, 'string', null, 'Documento de identidad de la persona.', false, '8-990-429'),
            new CampoSistema('tipo_identificacion_codigo', 'Tipo de identificación', false, 'codigo_catalogo', 'tipos_identificacion', 'CED, RUC, DNI, NIT o PAS.', false, 'CED'),
            new CampoSistema('nombres', 'Nombre', false, 'string', null, 'Nombre de la persona natural. Si el archivo trae el nombre completo en una sola columna, mapéala aquí.', false, 'Alexis Santos'),
            new CampoSistema('apellidos', 'Apellidos', false, 'string', null, 'Apellidos, solo si el archivo los trae en columna aparte.', false, 'Santos Pérez'),
            new CampoSistema('razon_social', 'Razón social', false, 'string', null, 'Nombre legal de la persona jurídica. Su presencia marca el registro como jurídico.', true, 'Comercial Delta, S.A.'),
        ];
    }

    /** @return list<CampoSistema> */
    private static function camposDelTarget(TargetImportacion $target): array
    {
        return match ($target) {
            TargetImportacion::CASO_COBRANZA => self::camposCobranza(),
            TargetImportacion::CASO_TICKET_CX => self::camposTicketCx(),
            TargetImportacion::CASO_LEAD_VENTA => self::camposLeadVenta(),
            TargetImportacion::CASO_SERVICIO => self::camposServicio(),
            default => [],
        };
    }

    /** @return list<CampoSistema> */
    private static function camposCobranza(): array
    {
        return [
            new CampoSistema('saldo_total', 'Saldo total', false, 'decimal', null, 'Deuda total vigente del caso.', false, '1250.40'),
            new CampoSistema('saldo_capital', 'Capital', false, 'decimal', null, 'Capital pendiente, sin intereses.', false, '1000.00'),
            new CampoSistema('saldo_interes', 'Intereses', false, 'decimal', null, 'Intereses acumulados.', false, '250.40'),
            new CampoSistema('monto_original', 'Monto original', false, 'decimal', null, 'Monto desembolsado originalmente.', true, '3000.00'),
            new CampoSistema('cuota_mensual', 'Cuota', false, 'decimal', null, 'Cuota pactada del período.', false, '125.00'),
            new CampoSistema('dias_mora', 'Días de mora', false, 'entero', null, 'Días de atraso al momento de la carga.', false, '45'),
            new CampoSistema('cuotas_totales', 'Cuotas totales', false, 'entero', null, 'Número de cuotas del crédito.', true, '24'),
            new CampoSistema('cuotas_pagadas', 'Cuotas pagadas', false, 'entero', null, 'Cuotas ya canceladas.', true, '6'),
            new CampoSistema('fecha_desembolso', 'Fecha de desembolso', false, 'fecha', null, 'Fecha en que se otorgó el crédito.', true, '2026-01-15'),
            new CampoSistema('fecha_vencimiento', 'Fecha de vencimiento', false, 'fecha', null, 'Fecha de vencimiento de la obligación.', true, '2026-12-31'),
        ];
    }

    /** @return list<CampoSistema> */
    private static function camposTicketCx(): array
    {
        return [
            new CampoSistema('asunto', 'Asunto', false, 'string', null, 'Título del ticket.', false, 'Facturación duplicada'),
            new CampoSistema('descripcion', 'Descripción', false, 'string', null, 'Detalle del reclamo.', false, 'El cliente reporta doble cobro.'),
            new CampoSistema('fecha_reporte', 'Fecha de reporte', false, 'fecha', null, 'Fecha en que se reportó el caso.', true, '2026-09-01'),
            new CampoSistema('fecha_limite_sla', 'Fecha límite SLA', false, 'fecha', null, 'Vencimiento del SLA comprometido.', true, '2026-09-05'),
        ];
    }

    /** @return list<CampoSistema> */
    private static function camposLeadVenta(): array
    {
        return [
            new CampoSistema('valor_estimado_monto', 'Valor estimado', false, 'decimal', null, 'Monto potencial de la venta.', false, '800.00'),
            new CampoSistema('origen_lead', 'Origen del lead', false, 'string', null, 'Canal de origen del prospecto.', false, 'Campaña web'),
            new CampoSistema('fecha_primer_contacto', 'Fecha primer contacto', false, 'fecha', null, 'Primer contacto con el prospecto.', true, '2026-08-20'),
            new CampoSistema('fecha_estimada_cierre', 'Fecha estimada de cierre', false, 'fecha', null, 'Cierre proyectado.', true, '2026-09-30'),
        ];
    }

    /** @return list<CampoSistema> */
    private static function camposServicio(): array
    {
        return [
            new CampoSistema('direccion_servicio', 'Dirección del servicio', false, 'string', null, 'Dónde se presta el servicio.', false, 'Vía España 100'),
            new CampoSistema('tecnico_asignado', 'Técnico asignado', false, 'string', null, 'Nombre del técnico responsable.', false, 'J. Pérez'),
            new CampoSistema('fecha_solicitud', 'Fecha de solicitud', false, 'fecha', null, 'Fecha en que se solicitó.', true, '2026-09-01'),
            new CampoSistema('fecha_programada', 'Fecha programada', false, 'fecha', null, 'Fecha agendada de atención.', true, '2026-09-04'),
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
