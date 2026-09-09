<?php

return [
    'col_rol_contacto' => 'Genera contactos',
    'rol_contacto' => [
        'ninguno' => '—',
        'telefono' => 'Teléfonos',
        'correo' => 'Correos',
        'referencia' => 'Referencias (nombre y número)',
    ],
    // Página
    'title' => 'Importaciones',
    'back_to_project' => '← Volver al proyecto',

    // Stepper
    'step_upload' => 'Subir archivo',
    'step_configure' => 'Configurar columnas',
    'step_confirm' => 'Confirmar',
    'step_process' => 'Procesar',

    // Paso 1 — subir archivo
    'what_to_import' => '¿Qué deseas importar?',
    'select_cartera' => 'Cartera',
    'select_cartera_placeholder' => 'Selecciona una cartera…',
    'drop_here' => 'Suelta el archivo aquí',
    'uploading' => 'Subiendo :name…',
    'drag_or_select' => 'Arrastra tu archivo aquí o <span class="text-brand-600 underline">selecciona</span>',
    'file_hint' => 'CSV, XLSX o XLSM · máx. 16 MB',
    'btn_continue_mapping' => 'Continuar al mapeo',
    'btn_processing' => 'Procesando...',

    // Paso 2 — configurar columnas
    'configure_columns' => 'Configurar columnas',
    'importing_subtitle' => 'Importando: :target · :count columnas detectadas.',
    'col_file_column' => 'Columna del archivo',
    'col_inferred_type' => 'Tipo inferido',
    'col_action' => 'Acción',
    'col_identifier' => 'Identificador',
    'identifier_persona' => 'Persona',
    'identifier_caso' => ':Entidad',
    'action_create_cp' => 'Crear campo personalizado',
    'action_ignore' => 'Ignorar',
    'mapped_to_system' => ':count mapeadas al sistema',
    'new_as_cp' => ':count nuevas como CP',
    'ignored' => ':count ignoradas',
    'warn_no_persona_id' => '⚠ Sin identificador de persona',
    'warn_no_case_id' => '⚠ Sin identificador de :entidad',
    'btn_discard' => 'Descartar',
    'btn_validate_continue' => 'Validar y continuar',

    // Paso 3 — confirmar
    'confirm_title' => 'Confirmar importación',
    'label_target' => 'Target',
    'label_total_rows' => 'Total filas',
    'label_mode' => 'Modo',
    'label_system_fields' => 'Campos sistema',
    'label_cp_to_create' => 'CP a crear',
    'label_cp_reused' => 'CP reutilizados',
    'cp_to_create_summary' => 'Campos personalizados a crear (:count)',
    'import_mode_label' => 'Modo de importación',
    'btn_execute' => 'Ejecutar importación',
    'confirm_execute' => '¿Confirmar importación? Este proceso puede tardar varios minutos.',

    // Paso 4 — procesando
    'label_status' => 'Estado',
    'label_total' => 'Total',
    'label_inserted' => 'Insertadas',
    'label_updated' => 'Actualizadas',
    'label_duplicated' => 'Duplicadas',
    'label_invalid' => 'Inválidas',
    'label_progress' => 'Progreso',
    'label_file' => 'Archivo',
    'label_started' => 'Iniciado',
    'label_finished' => 'Terminado',
    'btn_download_rejected' => 'Descargar filas rechazadas (:count)',
    'error_prefix' => 'Error: :message',
    'cp_created_notice' => '✓ :count campos personalizados creados durante la importación.',
    'btn_cancel_import' => 'Cancelar',
    'confirm_cancel' => '¿Cancelar la importación en curso?',
    'btn_new_import' => 'Nueva importación',

    // Historial general
    'history_title' => 'Historial de importaciones (:count)',
    'history_empty' => 'Aún no hay importaciones en este proyecto.',
    'col_date' => 'Fecha',
    'col_file' => 'Archivo',
    'col_type' => 'Tipo',
    'col_mode' => 'Modo',
    'col_user' => 'Usuario',
    'col_total' => 'Total',
    'col_inserted' => 'Insertadas',
    'col_updated' => 'Actualizadas',
    'col_duplicated' => 'Duplicadas',
    'col_invalid' => 'Inválidas',
    'col_status' => 'Estado',
    'col_actions' => 'Acciones',
    'link_download_rejected' => 'Rechazadas (:count)',

    // Importar casos
    'link_view' => 'Ver',

    // Importar personas

    // fila-mapeo partial
    'required_badge' => 'requerido',
    'optional_badge' => 'opcional',
    'no_map_option' => '— No mapear —',

    // Tipos inferidos (tipoLabel en @php)
    'tipo_texto_corto' => 'Texto corto',
    'tipo_texto_largo' => 'Texto largo',
    'tipo_numero_entero' => 'Nº entero',
    'tipo_numero_decimal' => 'Nº decimal',
    'tipo_fecha' => 'Fecha',
    'tipo_fecha_hora' => 'Fecha/hora',
    'tipo_booleano' => 'Booleano',
    'tipo_seleccion_unica' => 'Selección',
    'tipo_moneda' => 'Moneda',

];
