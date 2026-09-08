<?php

declare(strict_types=1);

namespace App\Modules\Gestiones\Application\Services;

use App\Modules\Auditoria\Domain\Contracts\RegistroDeExportaciones;
use App\Modules\Gestiones\Application\DTOs\FiltrosExportacionGestiones;
use App\Modules\Gestiones\Domain\Exceptions\VentanaDeExportacionInvalida;
use App\Modules\Gestiones\Domain\ValueObjects\VentanaDeExportacion;
use App\Modules\Tenancy\Application\Services\RelojDelMandante;
use App\Support\Csv\RespuestaCsv;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * El CSV de gestiones de un proyecto en un rango de tiempo del cliente.
 *
 * No hay listado de gestiones: el supervisor las mira en Reportes operativos,
 * así que el rango con nombre (hoy/ayer/semana/mes) se calcula con el MISMO
 * reloj que esa pantalla, y las dos fechas libres se cortan también en su
 * calendario. Con el corte en UTC, «el 7» de Panamá perdía las gestiones de la
 * tarde y ganaba las de la madrugada siguiente.
 */
final readonly class ExportadorCsvGestiones
{
    private const CABECERAS = [
        'gestion_public_id', 'creada_en', 'caso_public_id', 'tipo_caso',
        'identificacion_persona', 'nombre_persona',
        'tipo_gestion', 'canal', 'resultado', 'es_contacto_efectivo',
        'motivo_no_contacto', 'causa', 'duracion_segundos', 'usuario', 'notas',
    ];

    public function __construct(
        private ConnectionInterface $db,
        private RelojDelMandante $reloj,
        private RegistroDeExportaciones $registro,
    ) {}

    /**
     * @param  stdClass  $proyecto  Fila de `proyectos` con `id`, `codigo` y `mandante_id`.
     *
     * @throws VentanaDeExportacionInvalida si las fechas no forman una ventana admisible.
     */
    /**
     * @param  list<int>|null  $carterasPermitidas  El límite por cartera del rol (F22); null sin límite.
     */
    public function responder(stdClass $proyecto, FiltrosExportacionGestiones $filtros, ?array $carterasPermitidas = null): StreamedResponse
    {
        $proyectoId = (int) $proyecto->id;
        $mandanteId = (int) $proyecto->mandante_id;

        // Una vez, fuera del stream.
        $zona = $this->reloj->zonaDe($mandanteId);
        $rango = $this->rangoDe($filtros, $mandanteId);

        $q = $this->db->table('gestiones as g')
            ->leftJoin('casos as c', 'c.id', '=', 'g.caso_id')
            ->leftJoin('personas as p', 'p.id', '=', 'g.persona_id')
            ->leftJoin('tipos_gestion as tg', 'tg.id', '=', 'g.tipo_gestion_id')
            ->leftJoin('canales as cn', 'cn.id', '=', 'g.canal_id')
            ->leftJoin('resultados as r', 'r.id', '=', 'g.resultado_id')
            ->leftJoin('motivos_no_contacto as mnc', 'mnc.id', '=', 'g.motivo_no_contacto_id')
            ->leftJoin('causas_gestion as cg', 'cg.id', '=', 'g.causa_id')
            ->leftJoin('users as u', 'u.id', '=', 'g.usuario_id')
            // El recorte va primero y no depende de ningún parámetro.
            ->where('g.proyecto_id', $proyectoId)
            ->whereNull('g.eliminada_en')
            ->whereBetween('g.creada_en', [$rango['desde'], $rango['hasta']]);

        // El límite por cartera del rol (F22), por el caso de la gestión.
        if ($carterasPermitidas !== null) {
            $q->whereIn('c.cartera_id', $carterasPermitidas);
        }

        // El filtro por usuario se aplica SIEMPRE que venga: estrecha, nunca
        // amplía. Un id que no registró nada en el proyecto da un CSV vacío,
        // que es la respuesta honesta; antes se ignoraba en silencio si no
        // tenía rol base en el proyecto, y quien pedía «las gestiones de X»
        // —un admin de mandante, un rol custom— se llevaba las de todos.
        if ($filtros->usuarioId !== null) {
            $q->where('g.usuario_id', $filtros->usuarioId);
        }

        // Acotar el rango de PK antes de paginar: con sólo la ventana de fechas
        // MySQL ordenaba por id todas las gestiones de la ventana en cada lote
        // (la misma forma del incidente de auditoría, sólo que acotada a 92 días).
        [$minId, $maxId] = $this->rangoDePk($proyectoId, $rango['desde'], $rango['hasta']);
        if ($minId !== null && $maxId !== null) {
            $q->whereBetween('g.id', [$minId, $maxId]);
        }

        $q->select([
            'g.id', 'g.public_id as gestion_public_id', 'g.creada_en',
            'c.public_id as caso_public_id', 'c.tipo_caso',
            'p.identificacion', 'p.tipo_persona', 'p.nombres', 'p.apellidos', 'p.razon_social',
            'tg.nombre as tipo_gestion', 'cn.nombre as canal',
            'r.nombre as resultado', 'r.es_contacto_efectivo',
            'mnc.nombre as motivo_no_contacto', 'cg.nombre as causa',
            'g.duracion_segundos', 'u.name as usuario', 'g.notas',
        ]);

        return RespuestaCsv::desdeConsulta(
            nombreFichero: 'gestiones_'.$proyecto->codigo.'_'.Carbon::now()->format('Ymd_His').'.csv',
            cabeceras: self::CABECERAS,
            consulta: $q,
            columnaId: 'g.id',
            aliasId: 'id',
            fila: fn (stdClass $g): array => [
                $g->gestion_public_id,
                $this->instante($g->creada_en, $zona),
                $g->caso_public_id,
                $g->tipo_caso,
                $g->identificacion,
                $this->nombreDePersona($g),
                $g->tipo_gestion,
                $g->canal,
                $g->resultado,
                (bool) $g->es_contacto_efectivo,
                $g->motivo_no_contacto,
                $g->causa,
                $g->duracion_segundos === null ? null : (int) $g->duracion_segundos,
                $g->usuario,
                $g->notas,
            ],
            alTerminar: fn (int $total, bool $completa = true) => $this->registro->registrar(
                'gestiones',
                $filtros->comoParametros(),
                $total,
                $proyectoId,
                completa: $completa,
            ),
        );
    }

    /**
     * @return array{desde: Carbon, hasta: Carbon}
     *
     * @throws VentanaDeExportacionInvalida
     */
    private function rangoDe(FiltrosExportacionGestiones $filtros, int $mandanteId): array
    {
        if (! $filtros->usaVentana()) {
            return $this->reloj->rangoPreestablecido($filtros->rango, $mandanteId);
        }

        $ventana = VentanaDeExportacion::entre($filtros->desde, $filtros->hasta);

        return $this->reloj->rangoDeFechas($ventana->desde, $ventana->hasta, $mandanteId);
    }

    /**
     * El primer y el último id de gestión de la ventana, para que la paginación
     * por clave recorra un rango cerrado de la PK.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function rangoDePk(int $proyectoId, Carbon $desde, Carbon $hasta): array
    {
        $limites = $this->db->table('gestiones')
            ->where('proyecto_id', $proyectoId)
            ->whereBetween('creada_en', [$desde, $hasta])
            ->selectRaw('MIN(id) as minimo, MAX(id) as maximo')
            ->first();

        return [
            $limites?->minimo === null ? null : (int) $limites->minimo,
            $limites?->maximo === null ? null : (int) $limites->maximo,
        ];
    }

    private function nombreDePersona(stdClass $g): string
    {
        if ((string) $g->tipo_persona === 'juridica') {
            return trim((string) ($g->razon_social ?? ''));
        }

        return trim((string) ($g->nombres ?? '').' '.(string) ($g->apellidos ?? ''));
    }

    /** Un instante UTC de la base, escrito en la hora del cliente. */
    private function instante(mixed $valor, string $zona): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return Carbon::parse((string) $valor, 'UTC')->setTimezone($zona)->format('Y-m-d H:i:s');
    }
}
