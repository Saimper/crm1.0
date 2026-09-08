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

    /*
    | Días que se conserva el contenido del archivo importado (el `payload` de
    | cada fila) después de que la importación termine. Pasado ese plazo,
    | `importaciones:purgar-payloads` lo vacía: es la fila cruda del cliente
    | —cédula, teléfonos, saldos— y su único uso posterior es descargar las
    | filas rechazadas para corregirlas y volver a subirlas.
    */
    'retencion_payload_dias' => (int) env('IMPORTS_RETENCION_PAYLOAD_DIAS', 30),
];
