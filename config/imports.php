<?php

declare(strict_types=1);

return [
    /*
    | Tamaño de chunk para procesar filas por iteración.
    | Recomendado: 500-2000. Default 1000.
    */
    'batch_size' => (int) env('IMPORTS_BATCH_SIZE', 1000),

    /*
    | Cola dedicada a importaciones. Ejecutar workers con:
    |   php artisan queue:work --queue=imports
    */
    'queue' => (string) env('IMPORTS_QUEUE', 'imports'),

    /*
    | Tope máximo de filas por archivo. Bloquea uploads excesivos.
    */
    'max_filas_por_archivo' => (int) env('IMPORTS_MAX_FILAS', 200000),

    /*
    | Timeout total del Job en segundos.
    */
    'job_timeout' => (int) env('IMPORTS_JOB_TIMEOUT', 3600),
];
