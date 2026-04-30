<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use App\Modules\Personas\Application\DTOs\RegistrarPersonaInput;
use App\Modules\Personas\Application\UseCases\RegistrarPersona;
use App\Modules\Personas\Domain\Exceptions\DatosPersonaInvalidos;
use App\Modules\Personas\Domain\Exceptions\IdentificacionYaRegistradaEnProyecto;
use App\Modules\Personas\Domain\ValueObjects\Identificacion;
use App\Modules\Personas\Domain\ValueObjects\TipoPersona;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Procesa una importación de personas en modo dry-run (validación) o commit (inserción real).
 * Lee filas pendientes de la importación, las valida y, si `$commit`, las inserta vía UseCase
 * `RegistrarPersona` para respetar invariantes del dominio.
 */
final readonly class ProcesarImportacionPersonas
{
    public function __construct(
        private RegistrarPersona $registrarPersona,
        private ConnectionInterface $db,
    ) {}

    public function ejecutar(int $importacionId, bool $commit): void
    {
        /** @var ImportacionModel $importacion */
        $importacion = ImportacionModel::query()->sinScopeProyecto()->findOrFail($importacionId);
        $proyectoId = (int) $importacion->proyecto_id;

        $tiposIdentificacion = DB::table('tipos_identificacion')->pluck('id', 'codigo')->all();

        $okCount = 0;
        $errorCount = 0;
        $importadasCount = 0;

        $filas = ImportacionFilaModel::query()
            ->sinScopeProyecto()
            ->where('importacion_id', $importacionId)
            ->orderBy('numero_fila')
            ->get();

        foreach ($filas as $fila) {
            $payload = is_array($fila->payload) ? $fila->payload : [];
            $codigoTipoId = strtoupper((string) ($payload['tipo_identificacion_codigo'] ?? ''));
            $tipoIdentificacionId = $tiposIdentificacion[$codigoTipoId] ?? null;

            $errores = $this->validarPayload($payload, $tipoIdentificacionId);

            if ($errores !== []) {
                $fila->estado = 'invalida';
                $fila->mensaje_error = implode(' | ', $errores);
                $fila->save();
                $errorCount++;

                continue;
            }

            if (! $commit) {
                $fila->estado = 'valida';
                $fila->mensaje_error = null;
                $fila->save();
                $okCount++;

                continue;
            }

            try {
                $out = $this->registrarPersona->execute(new RegistrarPersonaInput(
                    publicId: (string) Str::ulid(),
                    proyectoId: $proyectoId,
                    tipoPersona: TipoPersona::from((string) $payload['tipo_persona']),
                    tipoIdentificacionId: (int) $tipoIdentificacionId,
                    identificacion: new Identificacion((string) $payload['identificacion']),
                    nombres: $this->valorOpcional($payload, 'nombres'),
                    apellidos: $this->valorOpcional($payload, 'apellidos'),
                    razonSocial: $this->valorOpcional($payload, 'razon_social'),
                    fechaNacimiento: $this->fechaOpcional($payload, 'fecha_nacimiento'),
                    creadaEn: new DateTimeImmutable,
                ));

                $fila->estado = 'importada';
                $fila->entidad_id = $out->id;
                $fila->mensaje_error = null;
                $fila->save();
                $okCount++;
                $importadasCount++;
            } catch (IdentificacionYaRegistradaEnProyecto $e) {
                $fila->estado = 'omitida';
                $fila->mensaje_error = 'Identificación ya registrada en el proyecto.';
                $fila->save();
                $errorCount++;
            } catch (DatosPersonaInvalidos $e) {
                $fila->estado = 'invalida';
                $fila->mensaje_error = $e->getMessage();
                $fila->save();
                $errorCount++;
            } catch (Throwable $e) {
                $fila->estado = 'invalida';
                $fila->mensaje_error = 'Error: '.mb_substr($e->getMessage(), 0, 400);
                $fila->save();
                $errorCount++;
            }
        }

        $importacion->total_filas = (int) $filas->count();
        $importacion->filas_ok = $okCount;
        $importacion->filas_error = $errorCount;
        $importacion->filas_importadas = $importadasCount;
        $importacion->estado = $commit
            ? ($errorCount === $filas->count() ? 'fallida' : 'completada')
            : 'validada';
        $importacion->save();
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
