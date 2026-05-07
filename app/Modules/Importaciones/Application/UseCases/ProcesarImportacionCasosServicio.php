<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Application\Services\ResolverPersonaImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ResumenChunk;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use App\Modules\Servicio\Application\DTOs\RegistrarCasoServicioInput;
use App\Modules\Servicio\Application\UseCases\RegistrarCasoServicio;
use App\Modules\Servicio\Domain\Exceptions\CodigoServicioYaRegistrado;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Procesa CSV de casos servicio con modo y chunking.
 * Match existente por (proyecto_id, codigo_servicio) en casos_servicio.
 */
final readonly class ProcesarImportacionCasosServicio
{
    /** @var list<string> */
    private const COLUMNAS_MUTABLES = ['direccion_servicio', 'tecnico_asignado', 'fecha_solicitud', 'fecha_programada'];

    public function __construct(
        private RegistrarCasoServicio $registrar,
        private ResolverPersonaImportacion $personaResolver,
    ) {}

    public function ejecutar(
        int $importacionId,
        bool $commit,
        ModoImportacion $modo = ModoImportacion::MERGE,
        ?int $offset = null,
        ?int $limit = null,
    ): ResumenChunk {
        /** @var ImportacionModel $importacion */
        $importacion = ImportacionModel::query()->sinScopeProyecto()->findOrFail($importacionId);
        $proyectoId = (int) $importacion->proyecto_id;

        $tiposIdentificacion = DB::table('tipos_identificacion')->pluck('id', 'codigo')->all();
        $carteras = DB::table('carteras')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();
        $estadosCaso = DB::table('estados_caso')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();
        $tiposAccion = DB::table('tipos_accion_servicio')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();
        $estadosTec = DB::table('estados_tecnicos')->where('proyecto_id', $proyectoId)->pluck('id', 'codigo')->all();

        $query = ImportacionFilaModel::query()
            ->sinScopeProyecto()
            ->where('importacion_id', $importacionId)
            ->orderBy('numero_fila');
        if ($offset !== null) {
            $query->offset($offset);
        }
        if ($limit !== null) {
            $query->limit($limit);
        }
        $filas = $query->get();
        if ($filas->isEmpty()) {
            return ResumenChunk::vacio();
        }

        $procesadas = 0;
        $validas = 0;
        $invalidas = 0;
        $duplicadas = 0;
        $ultimaFilaId = null;

        foreach ($filas as $fila) {
            $ultimaFilaId = (int) $fila->id;
            $payload = is_array($fila->payload) ? $fila->payload : [];
            $errores = [];

            $carteraId = $carteras[strtoupper((string) ($payload['cartera_codigo'] ?? ''))] ?? null;
            if ($carteraId === null) {
                $errores[] = 'cartera_codigo no existe en el proyecto.';
            }
            $estadoCasoId = $estadosCaso[strtoupper((string) ($payload['estado_caso_codigo'] ?? ''))] ?? null;
            if ($estadoCasoId === null) {
                $errores[] = 'estado_caso_codigo no existe en el proyecto.';
            }
            $tipoIdentId = $tiposIdentificacion[strtoupper((string) ($payload['tipo_identificacion_codigo'] ?? ''))] ?? null;
            $personaId = null;
            if ($tipoIdentId === null) {
                $errores[] = 'tipo_identificacion_codigo inválido.';
            } else {
                $personaId = $this->personaResolver->lookup(
                    $proyectoId,
                    (int) $tipoIdentId,
                    (string) ($payload['identificacion'] ?? ''),
                );
                if ($personaId === null) {
                    $errores = array_merge($errores, $this->personaResolver->validarParaCrear($payload));
                }
            }

            foreach (['codigo_servicio', 'fecha_solicitud', 'fecha_ingreso'] as $campo) {
                if (trim((string) ($payload[$campo] ?? '')) === '') {
                    $errores[] = "{$campo} es obligatorio.";
                }
            }

            if ($errores !== []) {
                $fila->estado = 'invalida';
                $fila->mensaje_error = implode(' | ', $errores);
                $fila->save();
                $invalidas++;

                continue;
            }

            if (! $commit) {
                $fila->estado = 'pendiente';
                $fila->mensaje_error = null;
                $fila->save();
                $validas++;

                continue;
            }

            $existenteCasoId = $this->buscarCasoExistente($proyectoId, (string) $payload['codigo_servicio']);

            try {
                if ($personaId === null) {
                    $personaId = $this->personaResolver->resolverOCrear($proyectoId, (int) $tipoIdentId, $payload);
                }
                if ($existenteCasoId !== null) {
                    $resultado = $this->resolverExistente($existenteCasoId, $payload, $modo);
                    $fila->estado = $resultado['estado'];
                    $fila->entidad_id = $existenteCasoId;
                    $fila->razon_omision = $resultado['razon'];
                    $fila->mensaje_error = null;
                    $fila->save();
                    match ($resultado['estado']) {
                        'duplicada' => $duplicadas++,
                        'procesada' => $procesadas++,
                        default => null,
                    };

                    continue;
                }

                $fechaProg = trim((string) ($payload['fecha_programada'] ?? '')) !== ''
                    ? new DateTimeImmutable((string) $payload['fecha_programada'])
                    : null;
                $out = $this->registrar->execute(new RegistrarCasoServicioInput(
                    proyectoId: $proyectoId,
                    carteraId: (int) $carteraId,
                    personaId: (int) $personaId,
                    estadoCasoId: (int) $estadoCasoId,
                    fechaIngreso: new DateTimeImmutable((string) $payload['fecha_ingreso']),
                    prioridad: (int) ($payload['prioridad'] ?? 3),
                    codigoServicio: (string) $payload['codigo_servicio'],
                    tipoAccionServicioId: $tiposAccion[strtoupper((string) ($payload['tipo_accion_codigo'] ?? ''))] ?? null,
                    estadoTecnicoId: $estadosTec[strtoupper((string) ($payload['estado_tecnico_codigo'] ?? ''))] ?? null,
                    direccionServicio: $this->opcional($payload, 'direccion_servicio'),
                    tecnicoAsignado: $this->opcional($payload, 'tecnico_asignado'),
                    fechaSolicitud: new DateTimeImmutable((string) $payload['fecha_solicitud']),
                    fechaProgramada: $fechaProg,
                ));
                $fila->estado = 'procesada';
                $fila->entidad_id = $out->casoId;
                $fila->mensaje_error = null;
                $fila->save();
                $procesadas++;
            } catch (CodigoServicioYaRegistrado) {
                $fila->estado = 'duplicada';
                $fila->razon_omision = 'código de servicio ya registrado (carrera con import paralelo)';
                $fila->save();
                $duplicadas++;
            } catch (Throwable $e) {
                $fila->estado = 'invalida';
                $fila->mensaje_error = 'Error: '.mb_substr($e->getMessage(), 0, 400);
                $fila->save();
                $invalidas++;
            }
        }

        return new ResumenChunk($procesadas, $validas, $invalidas, 0, $duplicadas, $filas->count(), $ultimaFilaId);
    }

    private function buscarCasoExistente(int $proyectoId, string $codigoServicio): ?int
    {
        $id = DB::table('casos_servicio')
            ->where('proyecto_id', $proyectoId)
            ->where('codigo_servicio', $codigoServicio)
            ->value('caso_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{estado: string, razon: ?string}
     */
    private function resolverExistente(int $casoId, array $payload, ModoImportacion $modo): array
    {
        if ($modo === ModoImportacion::SKIP_DUPLICADOS) {
            return ['estado' => 'duplicada', 'razon' => 'ya existe en proyecto'];
        }

        $existente = DB::table('casos_servicio')->where('caso_id', $casoId)->first();
        if ($existente === null) {
            return ['estado' => 'duplicada', 'razon' => 'caso desaparecido'];
        }

        $update = [];
        foreach (self::COLUMNAS_MUTABLES as $col) {
            $valor = trim((string) ($payload[$col] ?? ''));
            if ($valor === '') {
                continue;
            }
            if ($modo === ModoImportacion::MERGE) {
                $valorActual = $existente->{$col} ?? null;
                if ($valorActual !== null && (string) $valorActual !== '') {
                    continue;
                }
            }
            $update[$col] = $valor;
        }

        if ($update !== []) {
            $update['actualizada_en'] = CarbonImmutable::now();
            DB::table('casos_servicio')->where('caso_id', $casoId)->update($update);
        }

        return ['estado' => 'procesada', 'razon' => null];
    }

    /** @param array<string, mixed> $p */
    private function opcional(array $p, string $k): ?string
    {
        $v = trim((string) ($p[$k] ?? ''));

        return $v === '' ? null : $v;
    }
}
