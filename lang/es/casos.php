<?php

return [
    'pick_type_first' => 'Elige primero el tipo',
    'fields_ungrouped' => 'Sin grupo',
    'currency_symbol' => 'USD',
    'entidad_singular' => [
        'cobranza' => 'cuenta',
        'cx' => 'ticket',
        'venta' => 'oportunidad',
        'servicio' => 'servicio',
        'generico' => 'caso',
    ],
    'entidad_plural' => [
        'cobranza' => 'cuentas',
        'cx' => 'tickets',
        'venta' => 'oportunidades',
        'servicio' => 'servicios',
        'generico' => 'casos',
    ],
    'entidad_articulo' => [
        'cobranza' => 'una',
        'cx' => 'un',
        'venta' => 'una',
        'servicio' => 'un',
        'generico' => 'un',
    ],
    // Títulos de página
    'title_list' => ':Entidades del proyecto',
    'title_create' => 'Crear :entidad',
    'title_edit' => 'Editar :entidad',
    'title_work' => 'Vista de trabajo',

    // Subtítulos / meta
    'subtitle_open' => ':count :entidades',
    'subtitle_type' => 'Tipo de proyecto: :tipo',
    'subtitle_person' => 'Persona: :nombre',
    'subtitle_type_edit' => 'Tipo: :tipo',
    'subtitle_state_via' => 'Estado: se modifica vía gestiones',

    // Acciones / botones
    'create_case' => 'Crear :entidad',
    'save_changes' => 'Guardar cambios',
    'back_to_tray' => '← Volver a bandeja',
    'new_case' => 'Crear :entidad',
    'edit_case' => 'Editar :entidad',

    // Campos de formulario
    'field_wallet' => 'Cartera',
    'field_priority' => 'Prioridad (0–9)',
    'field_entry_date' => 'Fecha ingreso',
    'select_wallet' => '— Selecciona —',

    // Información adicional
    'additional_info' => 'Información adicional',
    'no_custom_fields' => '(sin campos definidos por el administrador para esta cartera)',
    'custom_fields_title' => 'Campos personalizados',
    'case_fields_title' => 'Campos de :entidad',

    // Aviso sin persona
    'no_person_alert' => 'Selecciona una persona desde el listado para crear :un :entidad. La pantalla espera <code>?persona={ulid}</code>.',

    // Filtros / barra de búsqueda
    'search_placeholder' => 'Buscar por persona…',
    'all_wallets' => 'Todas las carteras',
    'all_states' => 'Todos los estados',
    'clear_filters' => 'Limpiar',
    'export_csv' => 'Exportar CSV',
    'results' => ':count resultados',

    // Columnas de tabla
    'col_type' => 'Tipo',
    'col_person' => 'Persona',
    'col_id_doc' => 'Identificación',
    'col_wallet' => 'Cartera',
    'col_state' => 'Estado',
    'col_priority' => 'Prio',
    'col_commitment' => 'Compromiso',

    // Estado vacío
    'empty_title' => 'Sin :entidades',
    'empty_no_filters' => 'Aún no hay :entidades en este proyecto.',
    'empty_with_filters' => 'No hay :entidades que coincidan con los filtros.',

    // Badge compromiso
    'commitment_active' => 'Vigente',

    // Vista de trabajo — panel izquierdo
    'cases_count' => ':Entidades (:count)',
    'active_commitment' => 'Compromiso vigente',
    'expires' => 'Vence :date',
    'active_commitment_edit' => 'Editar',
    'no_open_cases' => 'Sin :entidades',
    'no_open_cases_desc' => 'Esta persona aún no tiene :entidades en este proyecto.',
    'resolved_commitments' => 'Compromisos resueltos (:count)',
    'expiry_label' => 'Vencimiento: :date',
    'resolved_label' => 'Resuelto',
    'no_date' => 'sin fecha',
    'prio_label' => 'prio :value',
    'active_commitment_label' => 'compromiso vigente',
    'contacts_button' => 'Contactos',
    'register_gestion_title' => 'Registrar gestión',
    'select_case_title' => 'Selecciona :un :entidad',
    'select_case_desc' => 'Elige :un :entidad del listado para registrar gestiones.',
    'history_title' => 'Historial (:count)',
    'no_gestions' => 'Sin gestiones',
    'no_gestions_desc' => 'Aún no hay gestiones registradas.',
    'custom_fields_panel' => 'Campos personalizados',
    'no_active_case' => 'Sin selección',
    'no_active_case_desc' => 'Selecciona :un :entidad para ver sus campos personalizados e historial.',
    'no_contact_badge' => 'No contacto: :motivo',
    'cause_badge' => 'Causa: :causa',

    // Nueva gestión
    'gestion_title' => 'Nueva gestión',
    'field_channel' => 'Canal',
    'field_gestion_type' => 'Tipo de gestión',
    'field_result' => 'Resultado',
    'field_contact_used' => 'Contacto usado',
    'field_no_contact_reason' => 'Motivo no contacto',
    'field_cause' => 'Causa',
    'field_duration' => 'Duración (seg)',
    'field_notes' => 'Notas (opcional)',
    'notes_placeholder' => 'Qué pasó, en una frase. Los datos que se filtran o cuentan van en los campos de arriba, no aquí.',
    'ctrl_enter_hint' => 'Ctrl+Enter o ⌘+Enter para guardar.',
    'submit_gestion' => 'Registrar gestión',

    // Compromisos inline en nueva gestión
    'promise_title' => 'Promesa de pago',
    'promise_amount' => 'Monto USD',
    'promise_date' => 'Fecha',
    'promise_payment_type' => 'Tipo de pago',
    'close_title' => 'Compromiso de cierre',
    'close_amount' => 'Monto USD',
    'close_estimated_date' => 'Fecha estimada',
    'close_funnel_stage' => 'Etapa del embudo',
    'service_action_title' => 'Acción de servicio programada',
    'service_action_desc' => 'Descripción de la acción',
    'service_action_desc_ph' => 'Ej. Instalación de equipos en domicilio',
    'service_scheduled_date' => 'Fecha programada',
    'service_action_type' => 'Tipo de acción',
    'service_technician' => 'Técnico asignado',
    'service_technician_ph' => 'Nombre del técnico',
    'resolution_title' => 'Compromiso de resolución',
    'resolution_action' => 'Acción comprometida',
    'resolution_action_ph' => 'Ej. Revisar facturación y llamar al cliente',
    'resolution_deadline' => 'Fecha límite',
    'escalation_section' => 'Escalamiento',
    'escalation_level' => 'Nivel',
    'yes' => 'Sí',
    'no' => 'No',
    'born_abbrev' => 'nac.',

    // F41: selector de columnas del listado
    'columns' => 'Columnas',
    'columns_title' => 'Columnas visibles',
    'columns_reset' => 'Restaurar',
    // Autoasignación: el asesor toma la cuenta que va a trabajar
    'assign_take' => 'Tomar',
    'assign_take_title' => 'Tomar esta cuenta y verla en mi bandeja',
    'assign_taken' => 'Cuenta tomada. Ya está en tu bandeja.',
    'assign_owner' => 'Asignada a',
    'assign_unowned' => 'Sin dueño',
    'assign_only_unowned' => 'Sólo sin dueño',
    'last_outcome_none' => 'sin resultado',
    'last_outcome_never' => 'sin gestiones todavía',
    'history_all' => 'Todas',
    'history_effective' => 'Efectivas',
    'history_effective_hint' => 'Sólo las gestiones que terminaron en contacto con la persona',
];
