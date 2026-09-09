<?php

declare(strict_types=1);

namespace App\Modules\Compromisos\Application\Services;

use App\Modules\Auditoria\Domain\Contracts\RegistroDeExportaciones;
use App\Modules\Compromisos\Application\DTOs\FiltrosListadoCompromisos;
use App\Modules\Compromisos\Domain\Columnas\CatalogoColumnasCompromiso;
use App\Modules\Compromisos\Domain\Columnas\ColumnaCompromiso;
use App\Modules\Tenancy\Application\Services\RelojDelMandante;
use App\Support\Csv\RespuestaCsv;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * El CSV de compromisos del proyecto, con el recorte del listado y lo que
 * aporta el tipo de operación: en cobranza el monto de la promesa, que es lo
 * primero que pide un supervisor y lo que la exportación antigua no traía.
 */
final readonly class ExportadorCsvCompromisos
{
    public function __construct(
        private ConnectionInterface $db,
        private ConsultaListadoCompromisos $consulta,
        private RelojDelMandante $reloj,
        private RegistroDeExportaciones $registro,
    ) {}

    /**
     * @param  stdClass  $proyecto  Fila de `proyectos` con `id`, `codigo`, `mandante_id` y `tipo_operacion`.
     */
    /**
     * @param  list<int>|null  $carterasPermitidas  El límite por cartera del rol (F22); null sin límite.
     */
    public function responder(stdClass $proyecto, FiltrosListadoCompromisos $filtros, ?array $carterasPermitidas = null): StreamedResponse
    {
        $proyectoId = (int) $proyecto->id;
        $mandanteId = (int) $proyecto->mandante_id;
        $tipoOperacion = (string) $proyecto->tipo_operacion;

        // Una vez, fuera del stream: ni la zona ni el «hoy» cambian entre filas.
        $zona = $this->reloj->zonaDe($mandanteId);
        $hoy = $this->reloj->hoy($mandanteId);

        $especificas = CatalogoColumnasCompromiso::especificasDe($tipoOperacion);

        $q = $this->consulta->aplicarFiltros(
            $this->consulta->recortarACarteras($this->consulta->consultaBase($proyectoId), $carterasPermitidas),
            $filtros,
            $hoy,
        );

        $seleccion = [
            'co.id', 'co.public_id as compromiso_public_id', 'co.tipo_compromiso', 'co.estado',
            'co.fecha_vencimiento', 'co.fecha_resolucion', 'co.creada_en',
            'cs.public_id as caso_public_id', 'cs.tipo_caso',
            'p.identificacion', 'p.nombres', 'p.apellidos', 'p.razon_social',
            'u.name as usuario',
        ];

        $tablaCti = CatalogoColumnasCompromiso::tablaCti($tipoOperacion);
        if ($tablaCti !== null) {
            $q->leftJoin($tablaCti.' as cti', 'cti.compromiso_id', '=', 'co.id');

            $catalogo = CatalogoColumnasCompromiso::catalogoDelTipo($tipoOperacion);
            if ($catalogo !== null) {
                $q->leftJoin($catalogo['tabla'].' as cat', 'cat.id', '=', $catalogo['fk']);
            }

            foreach ($especificas as $columna) {
                $seleccion[] = $this->db->raw($columna->expresion.' as '.$columna->alias());
            }
        }

        $q->select($seleccion);

        return RespuestaCsv::desdeConsulta(
            nombreFichero: 'compromisos_'.$proyecto->codigo.'_'.Carbon::now()->format('Ymd_His').'.csv',
            cabeceras: [
                ...CatalogoColumnasCompromiso::BASE,
                ...array_map(static fn (ColumnaCompromiso $c): string => $c->cabecera, $especificas),
            ],
            consulta: $q,
            columnaId: 'co.id',
            aliasId: 'id',
            fila: function (stdClass $co) use ($especificas, $zona): array {
                $fila = [
                    $co->compromiso_public_id,
                    $co->tipo_compromiso,
                    $co->estado,
                    // `date` de calendario, tal cual.
                    $co->fecha_vencimiento,
                    $co->fecha_resolucion,
                    $this->instante($co->creada_en, $zona),
                    $co->caso_public_id,
                    $co->tipo_caso,
                    $co->identificacion,
                    $co->nombres,
                    $co->apellidos,
                    $co->razon_social,
                    $co->usuario,
                ];

                foreach ($especificas as $columna) {
                    $fila[] = $co->{$columna->alias()} ?? null;
                }

                return $fila;
            },
            alTerminar: fn (int $total, bool $completa = true) => $this->registro->registrar(
                'compromisos',
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
