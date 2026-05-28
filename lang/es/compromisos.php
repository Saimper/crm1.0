<?php

return [
    // Títulos de página
    'title_list' => 'Compromisos del proyecto',
    'title_edit' => 'Editar compromiso',

    // Subtítulo resumen
    'subtitle_summary' => ':pendientes pendientes · :vencidos vencidos · :cumplidos cumplidos · :rotos rotos',

    // Subtítulo editar
    'subtitle_edit_type' => 'Tipo: :tipo',
    'subtitle_edit_state' => 'Estado: :estado',
    'subtitle_edit_pending' => 'Solo editables mientras pendiente.',

    // Acciones / botones
    'save_changes' => 'Guardar cambios',

    // Filtros
    'all_states' => 'Todos los estados',
    'state_pending' => 'Pendiente',
    'state_fulfilled' => 'Cumplido',
    'state_broken' => 'Roto',
    'state_cancelled' => 'Cancelado',
    'any_expiry' => 'Cualquier vencimiento',
    'filter_active' => 'Vigentes',
    'filter_expired' => 'Vencidos',
    'filter_next7d' => 'Próximos 7 días',
    'all_types' => 'Todos los tipos',
    'type_promise' => 'Promesa de pago',
    'type_resolution' => 'Resolución ticket',
    'type_close' => 'Cierre de venta',
    'type_service' => 'Acción de servicio',
    'clear_filters' => 'Limpiar',
    'results' => ':count resultados',

    // Columnas de tabla
    'col_type' => 'Tipo',
    'col_state' => 'Estado',
    'col_person' => 'Persona',
    'col_id_doc' => 'Identificación',
    'col_user' => 'Usuario',
    'col_expiry' => 'Vencimiento',
    'col_resolved' => 'Resuelto',

    // Estado vacío
    'empty_title' => 'Sin compromisos',
    'empty_desc' => 'No hay compromisos que coincidan con los filtros.',

    // Campos de formulario — promesa de pago
    'field_expiry_date' => 'Fecha vencimiento',
    'field_amount' => 'Monto',
    'field_currency' => 'Moneda',
    'field_payment_type' => 'Tipo de pago',
    'no_payment_type' => '— Sin tipo —',

    // Campos de formulario — resolución ticket
    'field_committed_action' => 'Acción comprometida',
    'field_sla_deadline' => 'Fecha límite SLA',
    'field_escalation_level' => 'Nivel escalamiento',
    'no_escalation' => '— Sin escalamiento —',

    // Campos de formulario — cierre de venta
    'field_close_amount' => 'Monto cierre',
    'field_funnel_stage' => 'Etapa embudo',
    'no_stage' => '— Sin etapa —',

    // Campos de formulario — acción de servicio
    'field_action_desc' => 'Descripción acción',
    'field_scheduled_date' => 'Fecha programada',
    'field_action_type' => 'Tipo de acción',
    'no_action_type' => '— Sin tipo —',
    'field_technician' => 'Técnico asignado (opcional)',
];
