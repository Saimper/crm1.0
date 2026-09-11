<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Application\Services\DescriptorDeFalloImportacion;
use App\Modules\Importaciones\Domain\Contracts\CampoPersonalizadoImportacionRepository;
use App\Modules\Importaciones\Domain\Enums\EstadoFila;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\Exceptions\FalloDeImportacion;
use App\Modules\Importaciones\Domain\Exceptions\FilaNoImportable;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionNoEncontrada;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionNoProcesable;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ResultadoFila;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Orquesta el procesamiento completo de una importación dinámica.
 *
 * Procesa filas en chunks, llama a obtenerMapaCampos() UNA vez por chunk,
 * acumula valores de CP y llama a guardarValoresEnLote() UNA vez por chunk.
 *
 * Todo lo que pueda fallar —desde leer la importación hasta el último lote—
 * va dentro de UN try/catch: la importación se marca fallida con un motivo
 * apto para pantalla y lo que sale de aquí es `FalloDeImportacion`, sin la
 * excepción original detrás. Antes cada capa reescribía `error_global` con
 * `getMessage()` y el INSERT con los datos del cliente acababa en la columna.
 */
final readonly class EjecutarImportacionDinamica
{
    public function __construct(
        private ProcesarFilaDinamica $procesarFila,
        private CampoPersonalizadoImportacionRepository $cpRepo,
        private ConnectionInterface $db,
        private DescriptorDeFalloImportacion $descriptor,
    ) {}

    /**
     * @return array{procesadas: int, insertadas: int, actualizadas: int, invalidas: int, omitidas: int, duplicadas: int}
     *
     * @throws FalloDeImportacion
     */
    public function execute(EjecutarImportacionInput $input): array
    {
        $proyectoId = null;
        $lote = 0;

        try {
            return $this->procesar($input, $proyectoId, $lote);
        } catch (Throwable $e) {
            $fallo = $this->descriptor->describir($e, [
                'importacion_id' => $input->importacionId,
                'proyecto_id' => $proyectoId,
                'lote' => $lote,
            ]);

            $this->db->table('importaciones')
                ->where('id', $input->importacionId)
                ->whereIn('estado', [EstadoImportacion::PREPARADA->value, EstadoImportacion::PROCESANDO->value])
                ->update([
                    'estado' => EstadoImportacion::FALLIDA->value,
                    'error_global' => $fallo->motivo,
                    'terminado_en' => CarbonImmutable::now(),
                ]);

            throw FalloDeImportacion::desde($fallo);
        }
    }

    /**
     * `$proyectoId` y `$lote` van por referencia para que el catch de arriba
     * pueda decir en qué proyecto y en qué lote (el ordinal del chunk) se rompió.
     *
     * @param-out int $proyectoId
     *
     * @return array{procesadas: int, insertadas: int, actualizadas: int, invalidas: int, omitidas: int, duplicadas: int}
     */
    private function procesar(EjecutarImportacionInput $input, ?int &$proyectoId, int &$lote): array
    {
        $importacion = $this->db->table('importaciones')
            ->where('id', $input->importacionId)
            ->first();

        if ($importacion === null) {
            throw ImportacionNoEncontrada::conId($input->importacionId);
        }

        $proyectoId = (int) $importacion->proyecto_id;

        // PREPARADA: ejecución directa. PROCESANDO: ya fue marcada por EncolarImportacion
        // al despachar el job (o el worker reanuda tras un corte); el job serializa con GET_LOCK.
        $estado = EstadoImportacion::from((string) $importacion->estado);
        if (! in_array($estado, [EstadoImportacion::PREPARADA, EstadoImportacion::PROCESANDO], true)) {
            throw ImportacionNoProcesable::enEstado($estado);
        }

        if ($importacion->esquema === null) {
            throw ImportacionNoProcesable::sinEsquema();
        }

        try {
            $esquema = EsquemaImportacion::deserializar((string) $importacion->esquema);
        } catch (InvalidArgumentException $e) {
            throw ImportacionNoProcesable::esquemaMalformado($e->getMessage());
        }

        // La columna `importaciones.modo` es la última palabra del supervisor:
        // la escribe `marcarComoEncolada` con lo elegido en el paso 3. El JSON
        // se guardó al terminar el paso 2, cuando el modo aún era el de por
        // defecto, y leerlo de ahí fue lo que hizo que todo corriera como upsert.
        $esquema = $esquema->conModo(ModoImportacion::from((string) $importacion->modo));
        $tipoOperacion = (string) $this->db->table('proyectos')->where('id', $proyectoId)->value('tipo_operacion');
        $esquema->validarContexto($proyectoId, (string) $importacion->tipo_entidad, $tipoOperacion);
        $carteraId = $esquema->carteraId;

        $iniciada = $this->db->table('importaciones')
            ->where('id', $input->importacionId)
            ->where('proyecto_id', $proyectoId)
            ->whereIn('estado', [EstadoImportacion::PREPARADA->value, EstadoImportacion::PROCESANDO->value])
            ->update([
                'estado' => EstadoImportacion::PROCESANDO->value,
                'iniciado_en' => CarbonImmutable::now(),
            ]);
        if ($iniciada === 0 && $this->verificarCancelacion($input->importacionId)) {
            throw ImportacionNoProcesable::enEstado(EstadoImportacion::CANCELADA);
        }

        $totalProcesadas = 0;
        $totalInsertadas = 0;
        $totalActualizadas = 0;
        $totalInvalidas = 0;
        $totalOmitidas = 0;
        $totalDuplicadas = 0;

        // Cursor por `numero_fila` y sólo filas pendientes, en vez de OFFSET:
        // el worker que reanuda tras un corte no vuelve a pasar por lo ya
        // procesado, y el salto no se paga leyendo y descartando N filas por
        // chunk. El cursor además cierra el bucle aunque una fila quedara
        // pendiente por lo que fuera: sin él, esa fila se releería sin fin.
        $ultimoNumeroFila = 0;

        while (true) {
            if ($this->verificarCancelacion($input->importacionId)) {
                break;
            }
            $filas = ImportacionFilaModel::query()
                ->sinScopeProyecto()
                ->where('proyecto_id', $proyectoId)
                ->where('importacion_id', $input->importacionId)
                ->where('estado', EstadoFila::PENDIENTE->value)
                ->where('numero_fila', '>', $ultimoNumeroFila)
                ->orderBy('numero_fila')
                ->limit($input->chunkSize)
                ->get();

            if ($filas->isEmpty()) {
                break;
            }

            $ultimoNumeroFila = (int) $filas->last()->numero_fila;
            $lote++;

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

                    try {
                        $resultado = $this->db->transaction(function () use ($payload, $esquema, $fila, $mapaCampos, $tiposIdentificacion, $personasExistentes, $casosExistentes): ResultadoFilaConValoresCp {
                            $resultado = $this->procesarFila->execute(new ProcesarFilaInput(
                                fila: $payload,
                                esquema: $esquema,
                                importacionFilaId: (int) $fila->id,
                                mapaCampos: $mapaCampos,
                                tiposIdentificacion: $tiposIdentificacion,
                                personasExistentes: $personasExistentes,
                                casosExistentes: $casosExistentes,
                            ));
                            if ($resultado->resultadoFila->estado === EstadoFila::INVALIDA) {
                                throw new FilaNoImportable($resultado->resultadoFila->razon ?? 'Fila inválida.');
                            }

                            return $resultado;
                        });
                    } catch (DomainException|InvalidArgumentException $e) {
                        $resultado = new ResultadoFilaConValoresCp(ResultadoFila::invalida($this->descriptor->motivoDeFila($e)), []);
                    }

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

        }

        $this->db->table('importaciones')
            ->where('id', $input->importacionId)
            ->where('proyecto_id', $proyectoId)
            ->where('estado', EstadoImportacion::PROCESANDO->value)
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
     * Las personas del chunk que ya existen en el proyecto, keyeadas por
     * "tipoIdentId:identificacion" con la identificación tal como viene en el
     * archivo, que es como la busca ProcesarFilaDinamica.
     *
     * UNA consulta por tipo de identificación con `whereIn`, que cae sobre el
     * índice único (proyecto, tipo, identificación). Antes era una consulta por
     * identificación: 778 ms por lote de 1.000 frente a 11 ms.
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
        $identKey = $columnaIdentidad->clavePayload();

        /** @var array<int, array<string, true>> $porTipo tipoIdentId → identificaciones del archivo */
        $porTipo = [];

        foreach ($filas as $fila) {
            $payload = is_array($fila->payload) ? $fila->payload : [];
            $valor = trim((string) ($payload[$identKey] ?? ''));
            if ($valor === '') {
                continue;
            }

            $tipoIdentId = null;
            if ($tipoIdentCol !== null) {
                $codigo = strtoupper(trim((string) ($payload[$tipoIdentCol->campoSistemaMapeado] ?? '')));
                $tipoIdentId = $tiposIdentificacion[$codigo] ?? null;
            }

            if ($tipoIdentId === null) {
                $primerTipo = reset($tiposIdentificacion);
                $tipoIdentId = $primerTipo !== false ? (int) $primerTipo : null;
            }

            if ($tipoIdentId !== null) {
                $porTipo[$tipoIdentId][$valor] = true;
            }
        }

        $personasMap = [];

        foreach ($porTipo as $tipoId => $identificaciones) {
            // PHP convierte «8123456» en clave entera; si llegara así al
            // `whereIn`, MySQL compararía la columna varchar como número y
            // dejaría de usar el índice único de personas para todo el lote.
            $valores = array_map('strval', array_keys($identificaciones));

            $encontradas = $this->db->table('personas')
                ->where('proyecto_id', $proyectoId)
                ->where('tipo_identificacion_id', $tipoId)
                ->whereIn('identificacion', $valores)
                ->get(['id', 'identificacion']);

            // La comparación en la base es case-insensitive (collation
            // unicode_ci) y la clave del mapa lleva el valor del archivo, así
            // que se casan sin distinguir mayúsculas, igual que hacía el
            // `where identificacion = ?` de antes.
            $porValor = [];
            foreach ($encontradas as $persona) {
                $porValor[mb_strtolower((string) $persona->identificacion)] = (int) $persona->id;
            }

            foreach ($valores as $ident) {
                $personaId = $porValor[mb_strtolower($ident)] ?? null;
                if ($personaId !== null) {
                    $personasMap[$tipoId.':'.$ident] = $personaId;
                }
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
