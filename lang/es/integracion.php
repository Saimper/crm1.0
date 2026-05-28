<?php

return [
    'title' => 'SSO secrets por mandante',
    'subtitle' => 'Secret compartido con el wrapper para firmar JWT (HS256). 1 secret por mandante = N proyectos. Al rotar, el secret anterior queda válido 24h para no romper sesiones en vuelo.',
    'back_to_panel' => '← Panel admin',

    'col_id' => 'ID',
    'col_mandante' => 'Mandante',
    'col_secret' => 'Secret actual',
    'col_old_secret' => 'Secret anterior',
    'col_last_rotation' => 'Última rotación',
    'col_status' => 'Estado',
    'col_actions' => 'Acciones',

    'status_active' => 'Activo',
    'status_inactive' => 'Inactivo',

    'secret_not_set' => '— sin configurar —',
    'secret_copy_now' => '⚠ Cópialo ahora: solo se muestra completo una vez tras rotar.',
    'old_valid_until' => 'Vigente hasta',

    'empty_title' => 'Sin mandantes',
    'empty_desc' => 'Aún no hay mandantes creados.',

    'btn_hide' => 'Ocultar',
    'btn_show' => 'Ver',
    'btn_webhooks' => 'Webhooks',
    'btn_rotate' => 'Rotar',
    'confirm_rotate' => '¿Rotar el secret de :codigo? El anterior queda vigente 24h. Wrapper recibe webhook automático.',

    'drawer_webhooks_title' => 'Webhooks del mandante',
    'webhooks_desc' => 'URLs que el CRM llamará en el wrapper. El body lleva firma HMAC-SHA256 en el header <code>X-Signature</code> usando el sso_secret del mandante.',
    'label_url_rotated' => 'URL al rotar secret',
    'label_url_status' => 'URL al cambiar estado del mandante',
    'btn_test_status' => 'Probar webhook status',
];
