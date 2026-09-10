<?php

return [
    'plantillas' => [
        'titulo' => 'Plantillas de nota',
        'ayuda' => 'Frases hechas que el gestor pega en las notas con un clic y edita después.',
        'etiqueta' => 'Etiqueta',
        'etiqueta_ph' => 'Pide llamada',
        'texto' => 'Texto',
        'texto_ph' => 'El titular pide que se le llame después de las 6.',
        'resultado' => 'Sólo con el resultado',
        'siempre' => '— Siempre —',
    ],
    'causas' => [
        'titulo' => 'Causas de gestión',
        'ayuda' => 'Por qué salió así. Los resultados marcados como «requiere causa» piden una de estas.',
        'nueva' => 'Nombre de la causa…',
        'confirm_eliminar' => '¿Eliminar esta causa? Sólo si ninguna gestión la usa.',
    ],
    'matriz' => [
        'titulo' => 'Qué resultados admite cada tipo de gestión',
        'ayuda' => 'Un tipo sin ninguna casilla marcada admite todos los resultados. Marca casillas para acotarlo.',
        'col_resultado' => 'Resultado',
    ],
    'canales' => [
        'titulo' => 'Canales',
        'ayuda' => 'Por dónde se hace la gestión. El catálogo es común a todos los clientes; aquí eliges cuáles usa este proyecto, en qué orden y con qué nombre.',
        'col_nombre' => 'Nombre en este proyecto',
        'col_duracion' => 'Pide duración',
        'col_adjunto' => 'Admite adjunto',
    ],

    // Wizard / configurador principal
    'titulo_editar' => 'Editar configuración',
    'titulo_configurar' => 'Configurar proyecto',
    'volver' => 'Volver',
    'secciones' => 'Secciones',
    'pasos_label' => 'Pasos',
    'opcional' => 'Opcional',
    'seccion_label' => 'Sección',
    'paso_de' => 'Paso :n de :total',
    'configurado' => 'Configurado',
    'configuracion_parcial' => 'Configuración parcial',
    'paso_opcional' => 'Paso opcional',
    'anterior' => 'Anterior',
    'siguiente' => 'Siguiente',
    'cerrar' => 'Cerrar',
    'sin_catalogos_especificos' => 'Tipo de proyecto sin catálogos específicos',

    // Campos de formulario comunes a todos los pasos
    'campo_codigo' => 'Código',
    'campo_nombre' => 'Nombre',
    'campo_descripcion' => 'Descripción (opcional)',
    'campo_orden' => 'Orden',
    'campo_estado' => 'Estado',
    'activo' => 'Activo',
    'inactivo' => 'Inactivo',

    // Paso: datos del proyecto
    'datos' => [
        'tipo_operacion' => 'Tipo de operación',
        'tipo_no_cambia' => 'El tipo no se puede cambiar después de creado.',
        'estado' => 'Estado',
        'proyecto_activo' => 'Proyecto activo',
        'guardar_continuar' => 'Guardar y continuar',
    ],

    // Paso: carteras
    'carteras' => [
        'n_carteras' => ':n carteras',
        'nueva' => 'Nueva cartera',
        'sin_titulo' => 'Sin carteras',
        'sin_desc' => 'Crea la primera cartera para clasificar los casos del proyecto.',
        'col_casos' => 'Casos',
        'activa' => 'Activa',
        'inactiva' => 'Inactiva',
        'cartera_activa' => 'Cartera activa',
        'drawer_nueva' => 'Nueva cartera',
        'drawer_editar' => 'Editar cartera',
        'confirm_eliminar' => '¿Eliminar esta cartera? Sus cuentas dejarán de estar disponibles para trabajar. El historial se conserva. Esta acción no se puede deshacer desde esta pantalla.',
    ],

    // Paso: resultados
    'resultados' => [
        'no_contactado' => 'No contactado',
        'es_no_contactado' => 'No contactado (habilita motivo de no contacto)',
        'n_resultados' => ':n resultados',
        'nuevo' => 'Nuevo resultado',
        'sin_titulo' => 'Sin resultados',
        'sin_desc' => 'Define los resultados posibles de una gestión y sus banderas de dominio.',
        'col_compromiso' => 'Compromiso',
        'col_causa' => 'Causa',
        'col_contacto_efectivo' => 'Contacto efectivo',
        'si' => 'Sí',
        'drawer_nuevo' => 'Nuevo resultado',
        'drawer_editar' => 'Editar resultado',
        'banderas' => 'Banderas de dominio',
        'es_contacto_efectivo' => 'Contacto efectivo',
        'requiere_compromiso' => 'Requiere compromiso',
        'requiere_causa' => 'Requiere causa',
        'estado_cierre' => 'Cierra el caso en',
        'estado_cierre_ninguno' => 'No cierra el caso',
        'estado_cierre_ayuda' => 'Al registrar una gestión con este resultado, el caso pasa a ese estado y queda cerrado.',
        'confirm_eliminar' => '¿Eliminar este resultado? Solo se permite si no hay gestiones que lo usen.',
    ],

    // Paso: tipos de gestión
    'tipos_gestion' => [
        'todos_canales' => 'Todos los canales activos',
        'canales_permitidos' => 'Canales permitidos',
        'canales_ayuda' => 'Selecciona uno o varios canales. Sin marcar ninguno, el tipo estará disponible en todos los canales activos.',
        'n_tipos' => ':n tipos',
        'nuevo' => 'Nuevo tipo',
        'sin_titulo' => 'Sin tipos de gestión',
        'sin_desc' => 'Define los tipos de gestión (ej. Llamada, Visita, Email).',
        'drawer_nuevo' => 'Nuevo tipo de gestión',
        'drawer_editar' => 'Editar tipo de gestión',
        'confirm_eliminar' => '¿Eliminar este tipo de gestión? Solo se permite si no hay gestiones registradas.',
    ],

    // Paso: motivos de no contacto
    'motivos' => [
        'n_motivos' => ':n motivos',
        'nuevo' => 'Nuevo motivo',
        'sin_titulo' => 'Sin motivos de no contacto',
        'sin_desc' => 'Define los motivos por los que una gestión no logra contacto (ej. Buzón, Línea ocupada).',
        'drawer_nuevo' => 'Nuevo motivo',
        'drawer_editar' => 'Editar motivo',
        'confirm_eliminar' => '¿Eliminar este motivo? Solo se permite si no hay gestiones que lo usen.',
    ],

    // Paso: estados de caso
    'estados_caso' => [
        'n_estados' => ':n estados',
        'nuevo' => 'Nuevo estado',
        'sin_titulo' => 'Sin estados',
        'sin_desc' => 'Define los estados operativos del caso (ej. Abierto, En gestión, Cerrado).',
        'col_terminal' => 'Terminal',
        'terminal_badge' => 'Terminal',
        'es_terminal' => 'Estado terminal (cierra el caso)',
        'drawer_nuevo' => 'Nuevo estado',
        'drawer_editar' => 'Editar estado',
        'confirm_eliminar' => '¿Eliminar este estado? Solo se permite si no hay casos asociados.',
    ],

    // Paso: catálogos tipo
    'catalogos_tipo' => [
        'label' => 'Catálogos del tipo',
    ],

    // Paso: campos personalizados
    'campos' => [
        'grupos_titulo' => 'Grupos',
        'grupos_ayuda' => 'Ordenan los campos en la vista de trabajo. Sólo un nombre y un orden.',
        'grupos_nuevo' => 'Nombre del grupo…',
        'grupos_confirm_eliminar' => '¿Eliminar este grupo? Sólo si no tiene campos dentro.',
        'grupo' => 'Grupo',
        'grupo_ninguno' => '— Sin grupo —',
        'col_grupo' => 'Grupo',
        'oculto' => 'Oculto',
        'visible_en_gestion' => 'Se ve en la vista de trabajo',
        'visible_en_gestion_ayuda' => 'Apagado, el campo sigue existiendo y se edita desde la ficha, pero no aparece al registrar una gestión.',
        'info_opcional' => 'Paso opcional. Los campos personalizados extienden el modelo de datos del proyecto sin migrar schema. Puedes completarlo después desde el panel de administración.',
        'n_campos' => ':n campos',
        'nuevo' => 'Nuevo campo',
        'sin_titulo' => 'Sin campos personalizados',
        'sin_desc' => 'Define campos por cartera (caso) o por tipo de gestión.',
        'col_ambito' => 'Ámbito',
        'col_sub_ambito' => 'Sub-ámbito',
        'col_etiqueta' => 'Etiqueta',
        'col_tipo' => 'Tipo',
        'col_obligatorio' => 'Obligatorio',
        'campo_ambito' => 'Ámbito',
        'ambito_caso' => 'Caso (× cartera)',
        'ambito_gestion' => 'Gestión (× tipo de gestión)',
        'label_tipo_gestion' => 'Tipo de gestión',
        'label_cartera' => 'Cartera',
        'seleccionar' => '— Seleccionar —',
        'campo_etiqueta' => 'Etiqueta',
        'campo_tipo' => 'Tipo',
        'longitud_max' => 'Longitud máxima (opcional, solo texto)',
        'obligatorio' => 'Obligatorio',
        'campo_activo' => 'Campo activo',
        'drawer_nuevo' => 'Nuevo campo personalizado',
        'drawer_editar' => 'Editar campo personalizado',
        'confirm_eliminar' => '¿Eliminar este campo? Solo se permite si no hay valores capturados.',
    ],

    // Paso: resumen
    'resumen' => [
        'titulo' => 'Resumen de configuración',
        'configuracion_completa' => 'Configuración completa',
        'pasos_pendientes' => ':n paso(s) pendiente(s)',
        'pasos_wizard' => 'Pasos del wizard',
        'catalogos_tipo' => 'Catálogos del tipo :tipo',
        'registro' => 'registro',
        'registros' => 'registros',
        'sin_campos_info' => 'Sin campos personalizados configurados. Puedes crearlos más adelante desde la configuración.',
        'volver_inicio' => 'Volver al inicio del wizard',
        'marcar_configurado' => 'Marcar proyecto como configurado',
        'faltan' => 'Faltan: :pasos',
    ],

    // Catálogos de tipo — cobranza
    'tramos_mora' => [
        'n_tramos' => ':n tramos',
        'nuevo' => 'Nuevo tramo',
        'sin_titulo' => 'Sin tramos de mora',
        'col_dias_desde' => 'Días desde',
        'col_dias_hasta' => 'Días hasta',
        'campo_dias_desde' => 'Días desde',
        'campo_dias_hasta' => 'Días hasta (opcional)',
        'drawer_nuevo' => 'Nuevo tramo de mora',
        'drawer_editar' => 'Editar tramo de mora',
        'confirm_eliminar' => '¿Eliminar este tramo? Solo si no hay casos asociados.',
    ],

    'tipos_pago' => [
        'n_tipos' => ':n tipos',
        'nuevo' => 'Nuevo tipo de pago',
        'sin_titulo' => 'Sin tipos de pago',
        'drawer_nuevo' => 'Nuevo tipo de pago',
        'drawer_editar' => 'Editar tipo de pago',
        'confirm_eliminar' => '¿Eliminar?',
    ],

    // Catálogos de tipo — cx
    'categorias_ticket' => [
        'n_categorias' => ':n categorías',
        'nueva' => 'Nueva categoría',
        'sin_titulo' => 'Sin categorías',
        'col_padre' => 'Padre',
        'campo_padre' => 'Categoría padre (opcional)',
        'sin_padre' => '— Sin padre —',
        'drawer_nueva' => 'Nueva categoría',
        'drawer_editar' => 'Editar categoría',
        'confirm_eliminar' => '¿Eliminar?',
    ],

    'prioridades_ticket' => [
        'n_prioridades' => ':n prioridades',
        'nueva' => 'Nueva prioridad',
        'sin_titulo' => 'Sin prioridades',
        'col_peso' => 'Peso',
        'campo_peso' => 'Peso (mayor = más prioritario)',
        'drawer_nueva' => 'Nueva prioridad',
        'drawer_editar' => 'Editar prioridad',
        'confirm_eliminar' => '¿Eliminar?',
    ],

    'niveles_sla' => [
        'n_niveles' => ':n niveles',
        'nuevo' => 'Nuevo nivel',
        'sin_titulo' => 'Sin niveles SLA',
        'col_horas' => 'Horas resolución',
        'campo_horas' => 'Horas de resolución',
        'drawer_nuevo' => 'Nuevo nivel SLA',
        'drawer_editar' => 'Editar nivel SLA',
        'confirm_eliminar' => '¿Eliminar?',
    ],

    'niveles_escalamiento' => [
        'n_niveles' => ':n niveles',
        'nuevo' => 'Nuevo nivel',
        'sin_titulo' => 'Sin niveles de escalamiento',
        'col_nivel' => 'Nivel',
        'campo_nivel' => 'Nivel (único por proyecto)',
        'drawer_nuevo' => 'Nuevo nivel de escalamiento',
        'drawer_editar' => 'Editar nivel de escalamiento',
        'confirm_eliminar' => '¿Eliminar?',
    ],

    // Catálogos de tipo — venta
    'productos_venta' => [
        'n_productos' => ':n productos',
        'nuevo' => 'Nuevo producto',
        'sin_titulo' => 'Sin productos',
        'drawer_nuevo' => 'Nuevo producto',
        'drawer_editar' => 'Editar producto',
        'confirm_eliminar' => '¿Eliminar?',
    ],

    'etapas_embudo' => [
        'n_etapas' => ':n etapas',
        'nueva' => 'Nueva etapa',
        'sin_titulo' => 'Sin etapas',
        'col_nivel' => 'Nivel',
        'col_prob_cierre' => 'Prob. cierre %',
        'campo_nivel' => 'Nivel (único por proyecto)',
        'campo_prob_cierre' => 'Probabilidad de cierre (%)',
        'drawer_nueva' => 'Nueva etapa del embudo',
        'drawer_editar' => 'Editar etapa del embudo',
        'confirm_eliminar' => '¿Eliminar?',
    ],

    // Catálogos de tipo — servicio
    'tipos_accion_servicio' => [
        'n_acciones' => ':n acciones',
        'nueva' => 'Nueva acción',
        'sin_titulo' => 'Sin tipos de acción',
        'col_duracion' => 'Duración est. (h)',
        'campo_duracion' => 'Duración estimada en horas (opcional)',
        'drawer_nuevo' => 'Nuevo tipo de acción',
        'drawer_editar' => 'Editar tipo de acción',
        'confirm_eliminar' => '¿Eliminar?',
    ],

    'estados_tecnicos' => [
        'n_estados' => ':n estados',
        'nuevo' => 'Nuevo estado técnico',
        'sin_titulo' => 'Sin estados técnicos',
        'drawer_nuevo' => 'Nuevo estado técnico',
        'drawer_editar' => 'Editar estado técnico',
        'confirm_eliminar' => '¿Eliminar?',
    ],

];
