<?php

declare(strict_types=1);

return [
    'wrapper_domain' => env('WRAPPER_DOMAIN'),
    'token_sso_ttl_segundos' => (int) env('SSO_TOKEN_TTL', 300),
    'preview_api_throttle' => env('SSO_PREVIEW_THROTTLE', '60,1'),
];
