<?php

return [

    // Wizard / configurador principal
    'titulo_editar' => 'Edit configuration',
    'titulo_configurar' => 'Configure project',
    'volver' => 'Back',
    'secciones' => 'Sections',
    'pasos_label' => 'Steps',
    'opcional' => 'Optional',
    'seccion_label' => 'Section',
    'paso_de' => 'Step :n of :total',
    'configurado' => 'Configured',
    'configuracion_parcial' => 'Partial configuration',
    'paso_opcional' => 'Optional step',
    'anterior' => 'Previous',
    'siguiente' => 'Next',
    'cerrar' => 'Close',
    'sin_catalogos_especificos' => 'No specific catalogs for this project type',

    // Common form fields
    'campo_codigo' => 'Code',
    'campo_nombre' => 'Name',
    'campo_descripcion' => 'Description (optional)',
    'campo_orden' => 'Order',
    'campo_estado' => 'Status',
    'activo' => 'Active',
    'inactivo' => 'Inactive',

    // Step: project data
    'datos' => [
        'tipo_operacion' => 'Operation type',
        'tipo_no_cambia' => 'The type cannot be changed after creation.',
        'estado' => 'Status',
        'proyecto_activo' => 'Active project',
        'guardar_continuar' => 'Save and continue',
    ],

    // Step: portfolios
    'carteras' => [
        'n_carteras' => ':n portfolios',
        'nueva' => 'New portfolio',
        'sin_titulo' => 'No portfolios',
        'sin_desc' => 'Create the first portfolio to classify the project cases.',
        'col_casos' => 'Cases',
        'activa' => 'Active',
        'inactiva' => 'Inactive',
        'cartera_activa' => 'Active portfolio',
        'drawer_nueva' => 'New portfolio',
        'drawer_editar' => 'Edit portfolio',
        'confirm_eliminar' => 'Delete this portfolio? This cannot be undone if it has no associated cases.',
    ],

    // Step: results
    'resultados' => [
        'n_resultados' => ':n results',
        'nuevo' => 'New result',
        'sin_titulo' => 'No results',
        'sin_desc' => 'Define the possible outcomes of a management action and their domain flags.',
        'col_compromiso' => 'Commitment',
        'col_causa' => 'Cause',
        'col_contacto_efectivo' => 'Effective contact',
        'si' => 'Yes',
        'drawer_nuevo' => 'New result',
        'drawer_editar' => 'Edit result',
        'banderas' => 'Domain flags',
        'es_contacto_efectivo' => 'Effective contact',
        'requiere_compromiso' => 'Requires commitment',
        'requiere_causa' => 'Requires cause',
        'confirm_eliminar' => 'Delete this result? Only allowed if no management actions use it.',
    ],

    // Step: management types
    'tipos_gestion' => [
        'n_tipos' => ':n types',
        'nuevo' => 'New type',
        'sin_titulo' => 'No management types',
        'sin_desc' => 'Define the management types (e.g. Call, Visit, Email).',
        'drawer_nuevo' => 'New management type',
        'drawer_editar' => 'Edit management type',
        'confirm_eliminar' => 'Delete this management type? Only allowed if no management records exist.',
    ],

    // Step: non-contact reasons
    'motivos' => [
        'n_motivos' => ':n reasons',
        'nuevo' => 'New reason',
        'sin_titulo' => 'No non-contact reasons',
        'sin_desc' => 'Define the reasons a management action fails to reach contact (e.g. Voicemail, Busy line).',
        'drawer_nuevo' => 'New reason',
        'drawer_editar' => 'Edit reason',
        'confirm_eliminar' => 'Delete this reason? Only allowed if no management actions use it.',
    ],

    // Step: case statuses
    'estados_caso' => [
        'n_estados' => ':n statuses',
        'nuevo' => 'New status',
        'sin_titulo' => 'No statuses',
        'sin_desc' => 'Define the operational statuses of a case (e.g. Open, In progress, Closed).',
        'col_terminal' => 'Terminal',
        'terminal_badge' => 'Terminal',
        'es_terminal' => 'Terminal status (closes the case)',
        'drawer_nuevo' => 'New status',
        'drawer_editar' => 'Edit status',
        'confirm_eliminar' => 'Delete this status? Only allowed if no cases are associated.',
    ],

    // Step: type catalogs
    'catalogos_tipo' => [
        'label' => 'Type catalogs',
    ],

    // Step: custom fields
    'campos' => [
        'info_opcional' => 'Optional step. Custom fields extend the project data model without schema migrations. You can complete this later from the administration panel.',
        'n_campos' => ':n fields',
        'nuevo' => 'New field',
        'sin_titulo' => 'No custom fields',
        'sin_desc' => 'Define fields by portfolio (case) or by management type.',
        'col_ambito' => 'Scope',
        'col_sub_ambito' => 'Sub-scope',
        'col_etiqueta' => 'Label',
        'col_tipo' => 'Type',
        'col_obligatorio' => 'Required',
        'campo_ambito' => 'Scope',
        'ambito_caso' => 'Case (× portfolio)',
        'ambito_gestion' => 'Management (× management type)',
        'label_tipo_gestion' => 'Management type',
        'label_cartera' => 'Portfolio',
        'seleccionar' => '— Select —',
        'campo_etiqueta' => 'Label',
        'campo_tipo' => 'Type',
        'longitud_max' => 'Maximum length (optional, text only)',
        'obligatorio' => 'Required',
        'campo_activo' => 'Active field',
        'drawer_nuevo' => 'New custom field',
        'drawer_editar' => 'Edit custom field',
        'confirm_eliminar' => 'Delete this field? Only allowed if no values have been captured.',
    ],

    // Step: summary
    'resumen' => [
        'titulo' => 'Configuration summary',
        'configuracion_completa' => 'Configuration complete',
        'pasos_pendientes' => ':n pending step(s)',
        'pasos_wizard' => 'Wizard steps',
        'catalogos_tipo' => ':tipo type catalogs',
        'registro' => 'record',
        'registros' => 'records',
        'sin_campos_info' => 'No custom fields configured. You can create them later from the configuration.',
        'volver_inicio' => 'Back to wizard start',
        'marcar_configurado' => 'Mark project as configured',
        'faltan' => 'Missing: :pasos',
    ],

    // Type catalogs — collections
    'tramos_mora' => [
        'n_tramos' => ':n tranches',
        'nuevo' => 'New tranche',
        'sin_titulo' => 'No delinquency tranches',
        'col_dias_desde' => 'Days from',
        'col_dias_hasta' => 'Days to',
        'campo_dias_desde' => 'Days from',
        'campo_dias_hasta' => 'Days to (optional)',
        'drawer_nuevo' => 'New delinquency tranche',
        'drawer_editar' => 'Edit delinquency tranche',
        'confirm_eliminar' => 'Delete this tranche? Only if no cases are associated.',
    ],

    'tipos_pago' => [
        'n_tipos' => ':n types',
        'nuevo' => 'New payment type',
        'sin_titulo' => 'No payment types',
        'drawer_nuevo' => 'New payment type',
        'drawer_editar' => 'Edit payment type',
        'confirm_eliminar' => 'Delete?',
    ],

    // Type catalogs — cx
    'categorias_ticket' => [
        'n_categorias' => ':n categories',
        'nueva' => 'New category',
        'sin_titulo' => 'No categories',
        'col_padre' => 'Parent',
        'campo_padre' => 'Parent category (optional)',
        'sin_padre' => '— No parent —',
        'drawer_nueva' => 'New category',
        'drawer_editar' => 'Edit category',
        'confirm_eliminar' => 'Delete?',
    ],

    'prioridades_ticket' => [
        'n_prioridades' => ':n priorities',
        'nueva' => 'New priority',
        'sin_titulo' => 'No priorities',
        'col_peso' => 'Weight',
        'campo_peso' => 'Weight (higher = more priority)',
        'drawer_nueva' => 'New priority',
        'drawer_editar' => 'Edit priority',
        'confirm_eliminar' => 'Delete?',
    ],

    'niveles_sla' => [
        'n_niveles' => ':n levels',
        'nuevo' => 'New level',
        'sin_titulo' => 'No SLA levels',
        'col_horas' => 'Resolution hours',
        'campo_horas' => 'Resolution hours',
        'drawer_nuevo' => 'New SLA level',
        'drawer_editar' => 'Edit SLA level',
        'confirm_eliminar' => 'Delete?',
    ],

    'niveles_escalamiento' => [
        'n_niveles' => ':n levels',
        'nuevo' => 'New level',
        'sin_titulo' => 'No escalation levels',
        'col_nivel' => 'Level',
        'campo_nivel' => 'Level (unique per project)',
        'drawer_nuevo' => 'New escalation level',
        'drawer_editar' => 'Edit escalation level',
        'confirm_eliminar' => 'Delete?',
    ],

    // Type catalogs — sales
    'productos_venta' => [
        'n_productos' => ':n products',
        'nuevo' => 'New product',
        'sin_titulo' => 'No products',
        'drawer_nuevo' => 'New product',
        'drawer_editar' => 'Edit product',
        'confirm_eliminar' => 'Delete?',
    ],

    'etapas_embudo' => [
        'n_etapas' => ':n stages',
        'nueva' => 'New stage',
        'sin_titulo' => 'No stages',
        'col_nivel' => 'Level',
        'col_prob_cierre' => 'Close prob. %',
        'campo_nivel' => 'Level (unique per project)',
        'campo_prob_cierre' => 'Close probability (%)',
        'drawer_nueva' => 'New funnel stage',
        'drawer_editar' => 'Edit funnel stage',
        'confirm_eliminar' => 'Delete?',
    ],

    // Type catalogs — service
    'tipos_accion_servicio' => [
        'n_acciones' => ':n actions',
        'nueva' => 'New action',
        'sin_titulo' => 'No action types',
        'col_duracion' => 'Est. duration (h)',
        'campo_duracion' => 'Estimated duration in hours (optional)',
        'drawer_nuevo' => 'New action type',
        'drawer_editar' => 'Edit action type',
        'confirm_eliminar' => 'Delete?',
    ],

    'estados_tecnicos' => [
        'n_estados' => ':n statuses',
        'nuevo' => 'New technical status',
        'sin_titulo' => 'No technical statuses',
        'drawer_nuevo' => 'New technical status',
        'drawer_editar' => 'Edit technical status',
        'confirm_eliminar' => 'Delete?',
    ],

];
