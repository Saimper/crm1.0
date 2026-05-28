<?php

return [
    'title' => 'SSO secrets per tenant',
    'subtitle' => 'Shared secret with the wrapper to sign JWT (HS256). 1 secret per tenant = N projects. When rotated, the previous secret remains valid for 24h to avoid breaking in-flight sessions.',
    'back_to_panel' => '← Admin panel',

    'col_id' => 'ID',
    'col_mandante' => 'Tenant',
    'col_secret' => 'Current secret',
    'col_old_secret' => 'Previous secret',
    'col_last_rotation' => 'Last rotation',
    'col_status' => 'Status',
    'col_actions' => 'Actions',

    'status_active' => 'Active',
    'status_inactive' => 'Inactive',

    'secret_not_set' => '— not configured —',
    'secret_copy_now' => '⚠ Copy it now: it is only shown in full once after rotation.',
    'old_valid_until' => 'Valid until',

    'empty_title' => 'No tenants',
    'empty_desc' => 'No tenants have been created yet.',

    'btn_hide' => 'Hide',
    'btn_show' => 'Show',
    'btn_webhooks' => 'Webhooks',
    'btn_rotate' => 'Rotate',
    'confirm_rotate' => 'Rotate the secret for :codigo? The previous one remains valid for 24h. Wrapper receives an automatic webhook.',

    'drawer_webhooks_title' => 'Tenant webhooks',
    'webhooks_desc' => 'URLs the CRM will call on the wrapper. The body carries an HMAC-SHA256 signature in the <code>X-Signature</code> header using the tenant\'s sso_secret.',
    'label_url_rotated' => 'URL on secret rotation',
    'label_url_status' => 'URL on tenant status change',
    'btn_test_status' => 'Test status webhook',
];
