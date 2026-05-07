<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Services;

use App\Modules\Personas\Application\DTOs\RegistrarPersonaInput;
use App\Modules\Personas\Application\UseCases\RegistrarPersona;
use App\Modules\Personas\Domain\Exceptions\IdentificacionYaRegistradaEnProyecto;
use App\Modules\Personas\Domain\ValueObjects\Identificacion;
use App\Modules\Personas\Domain\ValueObjects\TipoPersona;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * F35-C: importación unificada — el CSV de cualquier caso trae datos de persona.
 * Si la persona no existe en el proyecto, se crea automáticamente con los datos
 * del payload (mismo flujo que Crear Persona manual). Si existe, se reutiliza.
 *
 * Encapsula el lookup + creación + manejo de race con imports paralelos
 * (IdentificacionYaRegistradaEnProyecto → re-lookup).
 */
final readonly class ResolverPersonaImportacion
{
    public function __construct(
        private RegistrarPersona $registrar,
    ) {}

    /**
     * Búsqueda silenciosa. Devuelve id si existe, null si no. No crea ni valida.
     */
    public function lookup(int $proyectoId, int $tipoIdentificacionId, string $identificacion): ?int
    {
        $id = DB::table('personas')
            ->where('proyecto_id', $proyectoId)
            ->where('tipo_identificacion_id', $tipoIdentificacionId)
            ->where('identificacion', $identificacion)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Valida que el payload tenga datos suficientes para CREAR persona si no existe.
     * Si ya existe persona en el proyecto, esta validación no es necesaria (se reusa).
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public function validarParaCrear(array $payload): array
    {
        $errores = [];

        $ident = trim((string) ($payload['identificacion'] ?? ''));
        if ($ident === '') {
            $errores[] = 'identificacion es obligatoria.';
        }

        $tipo = self::inferirTipoPersona($payload);
        if ($tipo === 'fisica') {
            $nombres = trim((string) ($payload['nombres'] ?? ''));
            if ($nombres === '') {
                $errores[] = 'nombres es obligatorio para crear persona física nueva.';
            }
        } elseif ($tipo === 'juridica') {
            $razon = trim((string) ($payload['razon_social'] ?? ''));
            if ($razon === '') {
                $errores[] = 'razon_social es obligatoria para crear persona jurídica nueva.';
            }
        }

        return $errores;
    }

    /**
     * Crea o reutiliza persona. Solo llamar en fase commit.
     *
     * @param  array<string, mixed>  $payload
     */
    public function resolverOCrear(int $proyectoId, int $tipoIdentificacionId, array $payload): int
    {
        $identificacion = trim((string) ($payload['identificacion'] ?? ''));
        if ($identificacion === '') {
            throw new \RuntimeException('identificacion vacía: no se puede resolver persona.');
        }

        $existente = $this->lookup($proyectoId, $tipoIdentificacionId, $identificacion);
        if ($existente !== null) {
            return $existente;
        }

        $tipoPersona = self::inferirTipoPersona($payload);

        try {
            $output = $this->registrar->execute(new RegistrarPersonaInput(
                publicId: (string) Str::ulid(),
                proyectoId: $proyectoId,
                tipoPersona: TipoPersona::from($tipoPersona),
                tipoIdentificacionId: $tipoIdentificacionId,
                identificacion: new Identificacion($identificacion),
                nombres: self::valorOpcional($payload, 'nombres'),
                apellidos: self::valorOpcional($payload, 'apellidos'),
                razonSocial: self::valorOpcional($payload, 'razon_social'),
                fechaNacimiento: self::fechaOpcional($payload, 'fecha_nacimiento'),
                creadaEn: new DateTimeImmutable,
            ));

            return $output->id;
        } catch (IdentificacionYaRegistradaEnProyecto $e) {
            $existente = $this->lookup($proyectoId, $tipoIdentificacionId, $identificacion);
            if ($existente !== null) {
                return $existente;
            }
            throw $e;
        }
    }

    /** @param array<string, mixed> $payload */
    private static function inferirTipoPersona(array $payload): string
    {
        $explicito = strtolower(trim((string) ($payload['tipo_persona'] ?? '')));
        if (in_array($explicito, ['fisica', 'juridica'], true)) {
            return $explicito;
        }
        $razon = trim((string) ($payload['razon_social'] ?? ''));
        $nombres = trim((string) ($payload['nombres'] ?? ''));
        if ($razon !== '' && $nombres === '') {
            return 'juridica';
        }

        return 'fisica';
    }

    /** @param array<string, mixed> $payload */
    private static function valorOpcional(array $payload, string $key): ?string
    {
        $v = trim((string) ($payload[$key] ?? ''));

        return $v === '' ? null : $v;
    }

    /** @param array<string, mixed> $payload */
    private static function fechaOpcional(array $payload, string $key): ?DateTimeImmutable
    {
        $v = self::valorOpcional($payload, $key);
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
