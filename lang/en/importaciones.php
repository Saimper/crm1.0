<?php

return [
    'contact_columns' => ':count contact columns',
    'mapping_destination' => 'Column destination',
    'native_fields' => 'Standard fields',
    'contact_fields' => 'Person contacts',
    'col_rol_contacto' => 'Creates contacts',
    'rol_contacto' => [
        'ninguno' => '—',
        'telefono' => 'Phone numbers',
        'correo' => 'Emails',
        'referencia' => 'References (name and number)',
    ],
    // Page
    'title' => 'Imports',
    'back_to_project' => '← Back to project',

    // Stepper
    'step_upload' => 'Upload file',
    'step_configure' => 'Configure columns',
    'step_confirm' => 'Confirm',
    'step_process' => 'Process',

    // Step 1 — upload
    'what_to_import' => 'What do you want to import?',
    'select_cartera' => 'Portfolio',
    'select_cartera_placeholder' => 'Select a portfolio…',
    'drop_here' => 'Drop the file here',
    'uploading' => 'Uploading :name…',
    'drag_or_select' => 'Drag your file here or <span class="text-brand-600 underline">browse</span>',
    'file_hint' => 'CSV, XLSX or XLSM · max 16 MB',
    'btn_continue_mapping' => 'Continue to mapping',
    'btn_processing' => 'Processing...',

    // Step 2 — configure columns
    'configure_columns' => 'Configure columns',
    'importing_subtitle' => 'Importing: :target · :count columns detected.',
    'col_file_column' => 'File column',
    'col_inferred_type' => 'Inferred type',
    'col_action' => 'Action',
    'col_identifier' => 'Identifier',
    'identifier_persona' => 'Person',
    'identifier_caso' => ':Entidad',
    'action_create_cp' => 'Create custom field',
    'action_ignore' => 'Ignore',
    'mapped_to_system' => ':count mapped to system',
    'new_as_cp' => ':count new as custom field',
    'ignored' => ':count ignored',
    'warn_no_persona_id' => '⚠ No person identifier',
    'warn_no_case_id' => '⚠ No :entidad identifier',
    'btn_discard' => 'Discard',
    'btn_validate_continue' => 'Validate and continue',

    // Step 3 — confirm
    'confirm_title' => 'Confirm import',
    'label_target' => 'Target',
    'label_total_rows' => 'Total rows',
    'label_mode' => 'Mode',
    'label_system_fields' => 'System fields',
    'label_cp_to_create' => 'Custom fields to create',
    'label_cp_reused' => 'Reused custom fields',
    'cp_to_create_summary' => 'Custom fields to create (:count)',
    'import_mode_label' => 'Import mode',
    'btn_execute' => 'Run import',
    'confirm_execute' => 'Confirm import? This process may take several minutes.',

    // Step 4 — processing
    'label_status' => 'Status',
    'label_total' => 'Total',
    'label_inserted' => 'Inserted',
    'label_updated' => 'Updated',
    'label_duplicated' => 'Duplicates',
    'label_invalid' => 'Invalid',
    'label_progress' => 'Progress',
    'label_file' => 'File',
    'label_started' => 'Started',
    'label_finished' => 'Finished',
    'btn_download_rejected' => 'Download rejected rows (:count)',
    'error_prefix' => 'Error: :message',
    'cp_created_notice' => '✓ :count custom fields created during import.',
    'btn_cancel_import' => 'Cancel',
    'confirm_cancel' => 'Cancel the current import?',
    'btn_new_import' => 'New import',

    // General history
    'history_title' => 'Import history (:count)',
    'history_empty' => 'No imports yet in this project.',
    'col_date' => 'Date',
    'col_file' => 'File',
    'col_type' => 'Type',
    'col_mode' => 'Mode',
    'col_user' => 'User',
    'col_total' => 'Total',
    'col_inserted' => 'Inserted',
    'col_updated' => 'Updated',
    'col_duplicated' => 'Duplicates',
    'col_invalid' => 'Invalid',
    'col_status' => 'Status',
    'col_actions' => 'Actions',
    'link_download_rejected' => 'Rejected (:count)',

    // Import cases
    'link_view' => 'View',

    // Import people

    // fila-mapeo partial
    'required_badge' => 'required',
    'optional_badge' => 'optional',
    'no_map_option' => '— Do not map —',

    // Inferred types (tipoLabel in @php)
    'tipo_texto_corto' => 'Short text',
    'tipo_texto_largo' => 'Long text',
    'tipo_numero_entero' => 'Integer',
    'tipo_numero_decimal' => 'Decimal',
    'tipo_fecha' => 'Date',
    'tipo_fecha_hora' => 'Date/time',
    'tipo_booleano' => 'Boolean',
    'tipo_seleccion_unica' => 'Selection',
    'tipo_moneda' => 'Currency',

    // Inline mode options (importar-casos and importar-personas)
];
