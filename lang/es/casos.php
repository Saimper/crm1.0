<?php

return [
    // Títulos de página
    'title_list' => 'Casos del proyecto',
    'title_create' => 'Nuevo caso',
    'title_edit' => 'Editar caso',
    'title_work' => 'Vista de trabajo',

    // Subtítulos / meta
    'subtitle_open' => ':count casos abiertos',
    'subtitle_type' => 'Tipo de proyecto: :tipo',
    'subtitle_person' => 'Persona: :nombre',
    'subtitle_type_edit' => 'Tipo: :tipo',
    'subtitle_state_via' => 'Estado: se modifica vía gestiones',

    // Acciones / botones
    'create_case' => 'Crear caso',
    'save_changes' => 'Guardar cambios',
    'back_to_tray' => '← Volver a bandeja',
    'new_case' => 'Nuevo caso',
    'edit_case' => 'Editar caso',

    // Campos de formulario
    'field_wallet' => 'Cartera',
    'field_priority' => 'Prioridad (0–9)',
    'field_entry_date' => 'Fecha ingreso',
    'select_wallet' => '— Selecciona —',

    // Información adicional
    'additional_info' => 'Información adicional del caso',
    'no_custom_fields' => '(sin campos definidos por el administrador para esta cartera)',
    'custom_fields_title' => 'Campos personalizados',
    'case_fields_title' => 'Campos del caso',

    // Aviso sin persona
    'no_person_alert' => 'Selecciona una persona desde el listado para crear un caso. La pantalla espera <code>?persona={ulid}</code>.',

    // Filtros / barra de búsqueda
    'search_placeholder' => 'Buscar por persona…',
    'all_wallets' => 'Todas las carteras',
    'all_states' => 'Todos los estados',
    'clear_filters' => 'Limpiar',
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
    'empty_title' => 'Sin casos',
    'empty_no_filters' => 'Aún no hay casos en este proyecto.',
    'empty_with_filters' => 'No hay casos que coincidan con los filtros.',

    // Badge compromiso
    'commitment_active' => 'Vigente',

    // Vista de trabajo — panel izquierdo
    'cases_count' => 'Casos (:count)',
    'active_commitment' => 'Compromiso vigente',
    'expires' => 'Vence :date',
    'active_commitment_edit' => 'Editar',
    'no_open_cases' => 'Sin casos abiertos',
    'no_open_cases_desc' => 'Esta persona aún no tiene casos en este proyecto.',
    'resolved_commitments' => 'Compromisos resueltos (:count)',
    'expiry_label' => 'Vencimiento: :date',
    'resolved_label' => 'Resuelto',
    'no_date' => 'sin fecha',
    'prio_label' => 'prio :value',
    'active_commitment_label' => 'compromiso vigente',
    'contacts_button' => 'Contactos',
    'register_gestion_title' => 'Registrar gestión',
    'select_case_title' => 'Selecciona un caso',
    'select_case_desc' => 'Elige un caso del listado para registrar gestiones.',
    'history_title' => 'Historial (:count)',
    'no_gestions' => 'Sin gestiones',
    'no_gestions_desc' => 'Aún no hay gestiones registradas.',
    'custom_fields_panel' => 'Campos personalizados',
    'no_active_case' => 'Sin caso activo',
    'no_active_case_desc' => 'Selecciona un caso para ver sus campos personalizados e historial.',
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
    'notes_placeholder' => 'Complemento libre. No extraigas datos de aquí, usa los campos estructurados.',
    'ctrl_enter_hint' => 'Ctrl+Enter para guardar.',
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
];
