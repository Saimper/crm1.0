<?php

declare(strict_types=1);

return [
    'wrapper_domain' => env('WRAPPER_DOMAIN'),
    'token_sso_ttl_segundos' => (int) env('SSO_TOKEN_TTL', 300),
    'preview_api_throttle' => env('SSO_PREVIEW_THROTTLE', '60,1'),

    /*
     | Vida del Personal Access Token que se entrega al wrapper.
     |
     | Nacía sin caducidad: seguía siendo válido meses después aunque el mandante
     | se hubiera desactivado y aunque su `sso_secret` se hubiera rotado. Ocho
     | horas cubren un turno completo, que es para lo que se pide, y el wrapper
     | puede volver a pedirlo con un JWT nuevo cuando lo necesite.
     */
    'pat_ttl_minutos' => (int) env('SSO_PAT_TTL_MINUTOS', 480),
];
