<?php

declare(strict_types=1);

namespace App\Modules\Personas\Application\Services;

use App\Modules\Auditoria\Domain\Contracts\RegistroDeExportaciones;
use App\Modules\Personas\Application\DTOs\FiltrosListadoPersonas;
use App\Modules\Tenancy\Application\Services\RelojDelMandante;
use App\Support\Csv\RespuestaCsv;
use Illuminate\Support\Carbon;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * El CSV de personas de un proyecto, con el mismo recorte que el listado.
 *
 * No sabe de permisos: quien llama ya comprobó `personas.exportar` y que el
 * proyecto existe. Aquí sólo se decide qué columnas salen y cómo se escriben,
 * y se deja la huella en auditoría al terminar —descargar el padrón de un
 * cliente es la acción con más peso en privacidad de la aplicación—.
 */
final readonly class ExportadorCsvPersonas
{
    private const CABECERAS = [
        'tipo_persona', 'tipo_identificacion_codigo', 'identificacion',
        'nombres', 'apellidos', 'razon_social', 'fecha_nacimiento',
        'total_casos', 'creada_en',
    ];

    public function __construct(
        private ConsultaListadoPersonas $consulta,
        private RelojDelMandante $reloj,
        private RegistroDeExportaciones $registro,
    ) {}

    /**
     * @param  stdClass  $proyecto  Fila de `proyectos` con `id`, `codigo` y `mandante_id`.
     */
    public function responder(stdClass $proyecto, FiltrosListadoPersonas $filtros): StreamedResponse
    {
        $proyectoId = (int) $proyecto->id;

        // Una sola vez, fuera del stream: la zona no cambia entre filas y
        // resolverla por fila sería una lectura de configuración por persona.
        $zona = $this->reloj->zonaDe((int) $proyecto->mandante_id);

        $q = $this->consulta
            ->aplicarFiltros($this->consulta->consultaBase($proyectoId), $filtros)
            ->select([
                'p.id', 'p.tipo_persona',
                'ti.codigo as tipo_identificacion_codigo',
                'p.identificacion', 'p.nombres', 'p.apellidos', 'p.razon_social',
                'p.fecha_nacimiento',
                $this->consulta->totalCasos(),
                'p.creada_en',
            ]);

        return RespuestaCsv::desdeConsulta(
            nombreFichero: 'personas_'.$proyecto->codigo.'_'.Carbon::now()->format('Ymd_His').'.csv',
            cabeceras: self::CABECERAS,
            consulta: $q,
            columnaId: 'p.id',
            aliasId: 'id',
            fila: fn (stdClass $p): array => [
                $p->tipo_persona,
                $p->tipo_identificacion_codigo,
                $p->identificacion,
                $p->nombres,
                $p->apellidos,
                $p->razon_social,
                // `date` de calendario: tal cual, sin pasar por la zona (§reloj).
                $p->fecha_nacimiento,
                (int) $p->total_casos,
                $this->instante($p->creada_en, $zona),
            ],
            alTerminar: fn (int $total, bool $completa = true) => $this->registro->registrar(
                'personas',
                $filtros->comoParametros(),
                $total,
                $proyectoId,
                completa: $completa,
            ),
        );
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
