<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La exportación de auditoría de un proyecto tardaba 20-25 s y se cortaba a
 * mitad de descarga sin avisar.
 *
 * Medido con las 56.219 filas de la base local: el controller ordenaba por
 * `creada_en DESC`, el exportador añadía `orderBy(a.id)` encima y paginaba con
 * `chunk(500)`, que es OFFSET. Resultado: un filesort de las 56.219 filas en
 * cada uno de los ~113 lotes, y el `max_execution_time` cortaba el fichero por
 * la mitad con un 200 ya enviado.
 *
 * El exportador pasa a paginar por clave (`chunkById` sobre `a.id`, ver
 * RespuestaCsv): cada lote es `WHERE proyecto_id = ? AND id > ? ORDER BY id
 * LIMIT 500`. Para que eso sea UNA pasada por índice sin ordenar nada hace
 * falta un índice que empiece por `proyecto_id` y siga por `id`. Los dos que
 * había no sirven: `(proyecto_id, entidad_tipo, entidad_id)` y
 * `(proyecto_id, creada_en)` continúan por otra columna, y la extensión
 * implícita del PK que hace InnoDB queda detrás de esa columna, no detrás de
 * `proyecto_id`.
 *
 * El índice solo no basta: medido en MySQL 8.4 con 20.000 filas del proyecto,
 * el optimizador sigue eligiendo `auditorias_proyecto_entidad_idx` por ref más
 * filesort (tasa la pasada por este índice con el rango entero, sin descontar
 * el LIMIT: 5.623 frente a 1.660). Por eso la consulta del exportador lo lleva
 * con FORCE INDEX (ExportadorCsvAuditoria::consultaDelProyecto): 27 ms por
 * lote pasan a 1-5 ms.
 *
 * NO se crea `(mandante_id, id)` para la exportación del mandante, a
 * propósito. Ese recorte (AlcanceAuditoria::aplicarAMandantes) es un OR de
 * tres ramas —atribución directa, deducida del proyecto y por actor— y con un
 * OR así el optimizador no usa un índice por rama: va por la PK con el
 * `id > ?` de la paginación y filtra. Un índice que no se usa es peso muerto en
 * una tabla que crece con cada escritura de la aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias', function (Blueprint $table): void {
            $table->index(['proyecto_id', 'id'], 'auditorias_proyecto_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table): void {
            $table->dropIndex('auditorias_proyecto_id_idx');
        });
    }
};
