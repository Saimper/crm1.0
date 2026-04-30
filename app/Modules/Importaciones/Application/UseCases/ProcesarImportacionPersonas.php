<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ResumenChunk;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use App\Modules\Personas\Application\DTOs\RegistrarPersonaInput;
use App\Modules\Personas\Application\UseCases\RegistrarPersona;
use App\Modules\Personas\Domain\Exceptions\DatosPersonaInvalidos;
use App\Modules\Personas\Domain\Exceptions\IdentificacionYaRegistradaEnProyecto;
use App\Modules\Personas\Domain\ValueObjects\Identificacion;
use App\Modules\Personas\Domain\ValueObjects\TipoPersona;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Procesa importación de personas. Soporta modo (merge/skip_duplicados/overwrite)
 * y chunking (offset/limit). offset=null procesa todas las filas.
 *
 * - merge: si persona ya existe en proyecto, rellena solo columnas nulas/vacías.
 * - skip_duplicados: si existe, marca fila duplicada y continúa.
 * - overwrite: si existe, actualiza todas las columnas con valores no-null del CSV.
 *
 * Para inserts nuevos siempre delega a RegistrarPersona (mantiene invariantes).
 * Para updates usa DB::table (no importa modelos de Personas, respeta §3 y §13.6).
 */
final readonly class ProcesarImportacionPersonas
{
    /** @var list<string> Columnas mutables vía merge/overwrite. */
    private const COLUMNAS_MUTABLES = ['nombres', 'apellidos', 'razon_social', 'fecha_nacimiento'];

    public function __construct(
        private RegistrarPersona $registrarPersona,
        private ConnectionInterface $db,
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
        $omitidas = 0;
        $duplicadas = 0;
        $ultimaFilaId = null;

        foreach ($filas as $fila) {
            $ultimaFilaId = (int) $fila->id;
            $payload = is_array($fila->payload) ? $fila->payload : [];
            $codigoTipo = strtoupper((string) ($payload['tipo_identificacion_codigo'] ?? ''));
            $tipoIdentId = $tiposIdentificacion[$codigoTipo] ?? null;

            $errores = $this->validarPayload($payload, $tipoIdentId);
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

            $existenteId = $this->buscarPersonaExistente(
                $proyectoId,
                (int) $tipoIdentId,
                (string) $payload['identificacion'],
            );

            try {
                if ($existenteId !== null) {
                    $resultado = $this->resolverExistente($existenteId, $payload, $modo);
                    $fila->estado = $resultado['estado'];
                    $fila->entidad_id = $existenteId;
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

                $out = $this->registrarPersona->execute(new RegistrarPersonaInput(
                    publicId: (string) Str::ulid(),
                    proyectoId: $proyectoId,
                    tipoPersona: TipoPersona::from((string) $payload['tipo_persona']),
                    tipoIdentificacionId: (int) $tipoIdentId,
                    identificacion: new Identificacion((string) $payload['identificacion']),
                    nombres: $this->valorOpcional($payload, 'nombres'),
                    apellidos: $this->valorOpcional($payload, 'apellidos'),
                    razonSocial: $this->valorOpcional($payload, 'razon_social'),
                    fechaNacimiento: $this->fechaOpcional($payload, 'fecha_nacimiento'),
                    creadaEn: new DateTimeImmutable,
                ));
                $fila->estado = 'procesada';
                $fila->entidad_id = $out->id;
                $fila->mensaje_error = null;
                $fila->save();
                $procesadas++;
            } catch (IdentificacionYaRegistradaEnProyecto) {
                $fila->estado = 'duplicada';
                $fila->razon_omision = 'ya existe en proyecto (carrera con import paralelo)';
                $fila->save();
                $duplicadas++;
            } catch (DatosPersonaInvalidos $e) {
                $fila->estado = 'invalida';
                $fila->mensaje_error = $e->getMessage();
                $fila->save();
                $invalidas++;
            } catch (Throwable $e) {
                $fila->estado = 'invalida';
                $fila->mensaje_error = 'Error: '.mb_substr($e->getMessage(), 0, 400);
                $fila->save();
                $invalidas++;
            }
        }

        return new ResumenChunk(
            procesadas: $procesadas,
            validas: $validas,
            invalidas: $invalidas,
            omitidas: $omitidas,
            duplicadas: $duplicadas,
            filasEnChunk: $filas->count(),
            ultimaFilaId: $ultimaFilaId,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{estado: string, razon: ?string}
     */
    private function resolverExistente(int $personaId, array $payload, ModoImportacion $modo): array
    {
        if ($modo === ModoImportacion::SKIP_DUPLICADOS) {
            return ['estado' => 'duplicada', 'razon' => 'ya existe en proyecto'];
        }

        $existente = DB::table('personas')->where('id', $personaId)->first();
        if ($existente === null) {
            return ['estado' => 'duplicada', 'razon' => 'persona desaparecida'];
        }

        $update = [];
        foreach (self::COLUMNAS_MUTABLES as $col) {
            $valorNuevo = $this->valorOpcional($payload, $col);
            if ($valorNuevo === null) {
                continue;
            }
            if ($modo === ModoImportacion::MERGE) {
                $valorActual = $existente->{$col} ?? null;
                if ($valorActual !== null && $valorActual !== '') {
                    continue;
                }
            }
            $update[$col] = $valorNuevo;
        }

        if ($update !== []) {
            $update['actualizada_en'] = CarbonImmutable::now();
            DB::table('personas')->where('id', $personaId)->update($update);
        }

        return ['estado' => 'procesada', 'razon' => null];
    }

    private function buscarPersonaExistente(int $proyectoId, int $tipoIdentId, string $identificacion): ?int
    {
        $id = DB::table('personas')
            ->where('proyecto_id', $proyectoId)
            ->where('tipo_identificacion_id', $tipoIdentId)
            ->where('identificacion', $identificacion)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function validarPayload(array $payload, ?int $tipoIdentificacionId): array
    {
        $errores = [];

        $tipo = strtolower((string) ($payload['tipo_persona'] ?? ''));
        if (! in_array($tipo, ['fisica', 'juridica'], true)) {
            $errores[] = 'tipo_persona debe ser "fisica" o "juridica".';
        }

        if ($tipoIdentificacionId === null) {
            $errores[] = 'tipo_identificacion_codigo inválido (CED/RUC/DNI/PAS).';
        }

        $ident = trim((string) ($payload['identificacion'] ?? ''));
        if ($ident === '') {
            $errores[] = 'identificacion es obligatoria.';
        }

        if ($tipo === 'fisica') {
            $nombres = trim((string) ($payload['nombres'] ?? ''));
            if ($nombres === '') {
                $errores[] = 'nombres es obligatorio para persona física.';
            }
        } elseif ($tipo === 'juridica') {
            $razon = trim((string) ($payload['razon_social'] ?? ''));
            if ($razon === '') {
                $errores[] = 'razon_social es obligatoria para persona jurídica.';
            }
        }

        return $errores;
    }

    /** @param array<string, mixed> $payload */
    private function valorOpcional(array $payload, string $key): ?string
    {
        $v = $payload[$key] ?? null;
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    /** @param array<string, mixed> $payload */
    private function fechaOpcional(array $payload, string $key): ?DateTimeImmutable
    {
        $v = $this->valorOpcional($payload, $key);
        if ($v === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($v);
        } catch (Throwable) {
            return null;
        }
    }
}
