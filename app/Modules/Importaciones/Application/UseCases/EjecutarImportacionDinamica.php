<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Domain\Contracts\CampoPersonalizadoImportacionRepository;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\EstadoFila;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Orquesta el procesamiento completo de una importación dinámica.
 *
 * Procesa filas en chunks, llama a obtenerMapaCampos() UNA vez por chunk,
 * acumula valores de CP y llama a guardarValoresEnLote() UNA vez por chunk.
 */
final readonly class EjecutarImportacionDinamica
{
    public function __construct(
        private ProcesarFilaDinamica $procesarFila,
        private CampoPersonalizadoImportacionRepository $cpRepo,
        private ConnectionInterface $db,
    ) {}

    /**
     * @return array{procesadas: int, insertadas: int, actualizadas: int, invalidas: int, omitidas: int, duplicadas: int}
     */
    public function execute(EjecutarImportacionInput $input): array
    {
        $importacion = $this->db->table('importaciones')
            ->where('id', $input->importacionId)
            ->first();

        if ($importacion === null) {
            throw new \RuntimeException("Importación {$input->importacionId} no encontrada.");
        }

        $estado = EstadoImportacion::from((string) $importacion->estado);
        if ($estado !== EstadoImportacion::PREPARADA) {
            throw new \RuntimeException(
                "La importación está en estado {$estado->value}, se requiere PREPARADA."
            );
        }

        if ($importacion->esquema === null) {
            throw new \RuntimeException('La importación no tiene esquema configurado.');
        }

        $esquema = EsquemaImportacion::deserializar((string) $importacion->esquema);
        $proyectoId = (int) $importacion->proyecto_id;
        $carteraId = $esquema->carteraId;

        $this->db->table('importaciones')
            ->where('id', $input->importacionId)
            ->update([
                'estado' => EstadoImportacion::PROCESANDO->value,
                'iniciado_en' => CarbonImmutable::now(),
            ]);

        $offset = 0;
        $totalProcesadas = 0;
        $totalInsertadas = 0;
        $totalActualizadas = 0;
        $totalInvalidas = 0;
        $totalOmitidas = 0;
        $totalDuplicadas = 0;

        while (true) {
            $filas = ImportacionFilaModel::query()
                ->sinScopeProyecto()
                ->where('importacion_id', $input->importacionId)
                ->orderBy('numero_fila')
                ->offset($offset)
                ->limit($input->chunkSize)
                ->get();

            if ($filas->isEmpty()) {
                break;
            }

            try {
                $this->db->transaction(function () use (
                    $filas,
                    $esquema,
                    $proyectoId,
                    $carteraId,
                    &$totalProcesadas,
                    &$totalInsertadas,
                    &$totalActualizadas,
                    &$totalInvalidas,
                    &$totalOmitidas,
                    &$totalDuplicadas,
                ): void {
                    $mapaCampos = $carteraId !== null
                        ? $this->cpRepo->obtenerMapaCampos($proyectoId, $carteraId)
                        : [];

                    $tiposIdentificacion = $this->db->table('tipos_identificacion')
                        ->pluck('id', 'codigo')
                        ->all();

                    $personasExistentes = $this->cargarPersonasExistentes($filas, $esquema, $proyectoId, $tiposIdentificacion);
                    $casosExistentes = $this->cargarCasosExistentes($filas, $esquema, $proyectoId);

                    $valoresCpAcumulados = [];
                    $chunkProcesadas = 0;
                    $chunkInsertadas = 0;
                    $chunkActualizadas = 0;
                    $chunkInvalidas = 0;
                    $chunkOmitidas = 0;
                    $chunkDuplicadas = 0;

                    foreach ($filas as $fila) {
                        $payload = is_array($fila->payload) ? $fila->payload : [];

                        $resultado = $this->procesarFila->execute(new ProcesarFilaInput(
                            fila: $payload,
                            esquema: $esquema,
                            importacionFilaId: (int) $fila->id,
                            mapaCampos: $mapaCampos,
                            tiposIdentificacion: $tiposIdentificacion,
                            personasExistentes: $personasExistentes,
                            casosExistentes: $casosExistentes,
                        ));

                        $fila->estado = $resultado->resultadoFila->estado->value;
                        $fila->mensaje_error = $resultado->resultadoFila->razon;
                        $fila->entidad_id = $resultado->resultadoFila->entidadId;
                        $fila->save();

                        $valoresCpAcumulados = array_merge($valoresCpAcumulados, $resultado->valoresCp);

                        match ($resultado->resultadoFila->estado) {
                            EstadoFila::PROCESADA => $chunkProcesadas++,
                            EstadoFila::INVALIDA => $chunkInvalidas++,
                            EstadoFila::OMITIDA => $chunkOmitidas++,
                            EstadoFila::DUPLICADA => $chunkDuplicadas++,
                            default => null,
                        };

                        if ($resultado->fueInsert) {
                            $chunkInsertadas++;
                        } elseif ($resultado->resultadoFila->estado === EstadoFila::PROCESADA) {
                            $chunkActualizadas++;
                        }
                    }

                    if ($valoresCpAcumulados !== []) {
                        $this->cpRepo->guardarValoresEnLote($valoresCpAcumulados);
                    }

                    $this->db->table('importaciones')
                        ->where('id', $filas->first()->importacion_id)
                        ->update([
                            'procesadas' => DB::raw("procesadas + {$chunkProcesadas}"),
                            'insertadas' => DB::raw("insertadas + {$chunkInsertadas}"),
                            'actualizadas' => DB::raw("actualizadas + {$chunkActualizadas}"),
                            'invalidas' => DB::raw("invalidas + {$chunkInvalidas}"),
                            'omitidas' => DB::raw("omitidas + {$chunkOmitidas}"),
                            'duplicadas' => DB::raw("duplicadas + {$chunkDuplicadas}"),
                        ]);

                    $totalProcesadas += $chunkProcesadas;
                    $totalInsertadas += $chunkInsertadas;
                    $totalActualizadas += $chunkActualizadas;
                    $totalInvalidas += $chunkInvalidas;
                    $totalOmitidas += $chunkOmitidas;
                    $totalDuplicadas += $chunkDuplicadas;
                });
            } catch (Throwable $e) {
                $this->db->table('importaciones')
                    ->where('id', $input->importacionId)
                    ->update([
                        'estado' => EstadoImportacion::FALLIDA->value,
                        'error_global' => mb_substr($e->getMessage(), 0, 500),
                        'terminado_en' => CarbonImmutable::now(),
                    ]);

                throw $e;
            }

            if ($this->verificarCancelacion($input->importacionId)) {
                break;
            }

            $offset += $input->chunkSize;
        }

        $this->db->table('importaciones')
            ->where('id', $input->importacionId)
            ->update([
                'estado' => EstadoImportacion::COMPLETADA->value,
                'terminado_en' => CarbonImmutable::now(),
            ]);

        return [
            'procesadas' => $totalProcesadas,
            'insertadas' => $totalInsertadas,
            'actualizadas' => $totalActualizadas,
            'invalidas' => $totalInvalidas,
            'omitidas' => $totalOmitidas,
            'duplicadas' => $totalDuplicadas,
        ];
    }

    private function verificarCancelacion(int $importacionId): bool
    {
        $estado = $this->db->table('importaciones')
            ->where('id', $importacionId)
            ->value('estado');

        return $estado === EstadoImportacion::CANCELADA->value;
    }

    /**
     * Carga en un solo query todas las personas del chunk que ya existen
     * en el proyecto, keyeadas por "tipoIdentId:identificacion".
     *
     * @param  Collection<int, ImportacionFilaModel>  $filas
     * @param  array<string, int>  $tiposIdentificacion
     * @return array<string, int>
     */
    private function cargarPersonasExistentes(
        $filas,
        EsquemaImportacion $esquema,
        int $proyectoId,
        array $tiposIdentificacion,
    ): array {
        $columnaIdentidad = $esquema->columnaIdentificador();
        if ($columnaIdentidad === null) {
            return [];
        }

        $columnasSistema = $esquema->columnasParaSistema();
        $tipoIdentCol = $columnasSistema['tipo_identificacion_codigo'] ?? null;

        $identificaciones = [];

        foreach ($filas as $fila) {
            $payload = is_array($fila->payload) ? $fila->payload : [];
            $identKey = $columnaIdentidad->accion === AccionColumna::MAPEAR_SISTEMA
                ? $columnaIdentidad->campoSistemaMapeado
                : $columnaIdentidad->codigoSugerido();
            $valor = trim($payload[$identKey] ?? '');
            if ($valor === '') {
                continue;
            }

            $tipoIdentId = null;
            if ($tipoIdentCol !== null) {
                $codigo = strtoupper(trim($payload[$tipoIdentCol->campoSistemaMapeado] ?? ''));
                $tipoIdentId = $tiposIdentificacion[$codigo] ?? null;
            }

            if ($tipoIdentId === null) {
                $primerTipo = reset($tiposIdentificacion);
                $tipoIdentId = $primerTipo !== false ? (int) $primerTipo : null;
            }

            if ($tipoIdentId !== null) {
                $identificaciones[] = [$tipoIdentId, $valor];
            }
        }

        if ($identificaciones === []) {
            return [];
        }

        $personasMap = [];

        foreach ($identificaciones as [$tipoId, $ident]) {
            $personaId = $this->db->table('personas')
                ->where('proyecto_id', $proyectoId)
                ->where('tipo_identificacion_id', $tipoId)
                ->where('identificacion', $ident)
                ->value('id');

            if ($personaId !== null) {
                $personasMap[$tipoId.':'.$ident] = (int) $personaId;
            }
        }

        return $personasMap;
    }

    /**
     * Carga en un solo query todos los casos del chunk que ya existen
     * en el proyecto, keyeados por id_cpelegido.
     *
     * @param  Collection<int, ImportacionFilaModel>  $filas
     * @return array<string, int>
     */
    private function cargarCasosExistentes(
        $filas,
        EsquemaImportacion $esquema,
        int $proyectoId,
    ): array {
        $tabla = match ($esquema->target) {
            TargetImportacion::CASO_COBRANZA => 'casos_cobranza',
            TargetImportacion::CASO_TICKET_CX => 'casos_ticket_cx',
            TargetImportacion::CASO_LEAD_VENTA => 'casos_lead_venta',
            TargetImportacion::CASO_SERVICIO => 'casos_servicio',
            default => null,
        };

        $columnaUnique = match ($esquema->target) {
            TargetImportacion::CASO_COBRANZA => 'numero_prestamo',
            TargetImportacion::CASO_TICKET_CX => 'codigo_ticket',
            TargetImportacion::CASO_LEAD_VENTA => 'codigo_lead',
            TargetImportacion::CASO_SERVICIO => 'codigo_servicio',
            default => null,
        };

        if ($tabla === null || $columnaUnique === null) {
            return [];
        }

        $valores = [];

        foreach ($filas as $fila) {
            $payload = is_array($fila->payload) ? $fila->payload : [];
            $valor = trim($payload['id_cpelegido'] ?? '');
            if ($valor !== '') {
                $valores[] = $valor;
            }
        }

        if ($valores === []) {
            return [];
        }

        $casosMap = [];
        $rows = $this->db->table($tabla)
            ->where('proyecto_id', $proyectoId)
            ->whereIn($columnaUnique, $valores)
            ->get(['caso_id', $columnaUnique]);

        foreach ($rows as $row) {
            $casosMap[(string) $row->{$columnaUnique}] = (int) $row->caso_id;
        }

        return $casosMap;
    }
}
