<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Application\Services;

use App\Modules\Auditoria\Domain\Contracts\RegistroDeExportaciones;
use App\Support\Csv\RespuestaCsv;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Escribe el CSV de auditoría a partir de una consulta YA RECORTADA.
 *
 * A propósito no sabe nada de permisos ni de tenants: quien llama decide el
 * alcance con `AlcanceAuditoria` y aquí sólo se serializa. Así las dos
 * exportaciones (la del proyecto y la del mandante) comparten formato sin
 * compartir —ni poder saltarse— el recorte.
 *
 * EL CSV SALE POR `id` ASCENDENTE, no por `creada_en DESC` como antes. Es la
 * consecuencia de paginar por clave (RespuestaCsv::desdeConsulta): cada lote es
 * `id > último LIMIT 500`, una pasada por el índice `(proyecto_id, id)` sin
 * OFFSET ni filesort. Con el orden anterior se ordenaban las 56.219 filas de la
 * tabla en cada uno de los ~113 lotes: 20-25 s y un fichero cortado a la mitad
 * por `max_execution_time`, con el 200 ya enviado. Como `id` crece con el
 * tiempo, el fichero sigue siendo cronológico: del evento más antiguo al más
 * reciente.
 *
 * Cada descarga deja su huella en la propia auditoría (`exportado`): quién,
 * con qué filtros y cuántas filas. Sacar el historial completo de lo que se
 * hizo con los datos de un cliente es, a su vez, algo que ese cliente tiene
 * derecho a ver.
 */
final readonly class ExportadorCsvAuditoria
{
    private const CABECERA = [
        'public_id', 'creada_en', 'usuario', 'entidad_tipo', 'entidad_id',
        'evento', 'ip', 'user_agent', 'cambios_json',
        'datos_antes_json', 'datos_despues_json',
    ];

    /** El índice por el que pagina la exportación de un proyecto (migración 2026_09_09_121000). */
    public const INDICE_PAGINACION = 'auditorias_proyecto_id_idx';

    public function __construct(private RegistroDeExportaciones $registro) {}

    /**
     * La consulta de la exportación de UN proyecto, ya recortada a él.
     *
     * Lleva FORCE INDEX y no es un capricho: medido en MySQL 8.4 con 20.000
     * filas del proyecto, el optimizador elige `auditorias_proyecto_entidad_idx`
     * por ref y ordena (27 ms por lote; a 56.219 filas, los 20-25 s del
     * incidente), porque tasa la pasada por `(proyecto_id, id)` con el tamaño
     * del rango entero —5.623 frente a 1.660— sin descontar el LIMIT. Forzado,
     * cada lote es un rango `proyecto_id = ? AND id > ?` que se detiene en la
     * fila 500: 1-5 ms. Está aquí y no en el controller para que el test que
     * hace EXPLAIN mire exactamente la consulta que se ejecuta.
     *
     * Sólo para el proyecto: el recorte por mandante es un OR de tres ramas
     * (AlcanceAuditoria::aplicarAMandantes) y este índice no le sirve.
     */
    public function consultaDelProyecto(int $proyectoId): Builder
    {
        $consulta = DB::table('auditorias as a');

        if ($this->hayIndiceDePaginacion()) {
            $consulta->forceIndex(self::INDICE_PAGINACION);
        }

        return $consulta
            ->leftJoin('users as u', 'u.id', '=', 'a.usuario_id')
            // El recorte va PRIMERO y no depende de ningún parámetro: los
            // filtros que se añadan después sólo pueden estrechar esto.
            ->where('a.proyecto_id', $proyectoId)
            ->select($this->columnas());
    }

    /**
     * Una base restaurada de un dump anterior a la migración del índice no lo
     * tiene, y `FORCE INDEX` sobre un índice que no existe es un error de
     * MySQL: la descarga moriría con un 500 en vez de tardar más. Es una
     * consulta de esquema por descarga, no por lote: se pregunta al construir
     * la consulta, una sola vez.
     */
    private function hayIndiceDePaginacion(): bool
    {
        return Schema::hasIndex('auditorias', self::INDICE_PAGINACION);
    }

    /**
     * @param  Builder  $consulta  Debe seleccionar las columnas de columnas(). Se le quita
     *                             cualquier orden: se pagina por `a.id`.
     * @param  FiltrosAuditoria  $filtros  Los que ya se aplicaron a la consulta; van a la huella.
     * @param  int|null  $proyectoId  Nulo en la descarga transversal del mandante.
     * @param  int|null  $mandanteId  Nulo cuando la descarga abarca más de un cliente.
     */
    public function responder(
        Builder $consulta,
        string $nombreFichero,
        FiltrosAuditoria $filtros,
        ?int $proyectoId,
        ?int $mandanteId,
    ): StreamedResponse {
        return RespuestaCsv::desdeConsulta(
            $nombreFichero,
            self::CABECERA,
            $consulta,
            'a.id',
            'id',
            static fn (object $a): array => [
                (string) $a->public_id,
                (string) $a->creada_en,
                (string) ($a->usuario_nombre ?? ''),
                (string) $a->entidad_tipo,
                (string) $a->entidad_id,
                (string) $a->evento,
                (string) ($a->ip ?? ''),
                (string) ($a->user_agent ?? ''),
                (string) ($a->cambios ?? ''),
                (string) ($a->datos_antes ?? ''),
                (string) ($a->datos_despues ?? ''),
            ],
            alTerminar: function (int $total, bool $completa = true) use ($filtros, $proyectoId, $mandanteId): void {
                $this->registro->registrar('auditorias', $filtros->aplicados(), $total, $proyectoId, $mandanteId, completa: $completa);
            },
        );
    }

    /**
     * Columnas que el CSV necesita. Centralizadas para que las dos
     * exportaciones no se desincronicen. `a.id` no sale en el fichero: es la
     * clave por la que se pagina.
     *
     * @return list<string>
     */
    public function columnas(): array
    {
        return [
            'a.id', 'a.public_id', 'a.creada_en', 'a.entidad_tipo', 'a.entidad_id',
            'a.evento', 'a.ip', 'a.user_agent',
            'a.datos_antes', 'a.datos_despues', 'a.cambios',
            'u.name as usuario_nombre',
        ];
    }
}
