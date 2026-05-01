<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\UseCases;

use App\Models\User;
use App\Modules\Integracion\Application\DTOs\ConsumirJwtHandshakeInput;
use App\Modules\Integracion\Application\DTOs\ConsumirJwtHandshakeOutput;
use App\Modules\Integracion\Domain\Contracts\RepositorioTokensConsumidos;
use App\Modules\Integracion\Domain\Exceptions\JwtFirmaInvalida;
use App\Modules\Integracion\Domain\Exceptions\JwtMalFormado;
use App\Modules\Integracion\Domain\Exceptions\JwtTokenYaConsumido;
use App\Modules\Integracion\Domain\Exceptions\ProyectoSsoNoConfigurado;
use App\Modules\Integracion\Domain\ValueObjects\MapeoRolWrapper;
use App\Modules\Integracion\Domain\ValueObjects\PayloadJwt;
use DateTimeImmutable;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class ConsumirJwtHandshake
{
    private const ALGORITMO = 'HS256';

    private const LEEWAY_SEGUNDOS = 30;

    public function __construct(
        private readonly RepositorioTokensConsumidos $repositorioConsumidos,
        private readonly ConnectionInterface $db,
    ) {}

    public function execute(ConsumirJwtHandshakeInput $input): ConsumirJwtHandshakeOutput
    {
        $proyectoIdAviso = $this->extraerProyectoIdInseguro($input->jwt);

        $proyecto = $this->db->table('proyectos')
            ->where('id', $proyectoIdAviso)
            ->whereNull('eliminada_en')
            ->where('activo', true)
            ->first(['id', 'sso_secret']);

        if ($proyecto === null) {
            throw JwtFirmaInvalida::crear();
        }

        $secret = (string) ($proyecto->sso_secret ?? '');
        if ($secret === '') {
            throw ProyectoSsoNoConfigurado::crear((int) $proyecto->id);
        }

        JWT::$leeway = self::LEEWAY_SEGUNDOS;

        try {
            $claims = JWT::decode($input->jwt, new Key($secret, self::ALGORITMO));
        } catch (ExpiredException|SignatureInvalidException) {
            throw JwtFirmaInvalida::crear();
        } catch (\UnexpectedValueException|\DomainException) {
            throw JwtFirmaInvalida::crear();
        }

        $ahora = new DateTimeImmutable('now');
        $payload = PayloadJwt::desdeClaims($claims, $ahora);

        if ($payload->proyectoId !== (int) $proyecto->id) {
            throw JwtFirmaInvalida::crear();
        }

        if ($this->repositorioConsumidos->fueConsumido($payload->jti)) {
            throw JwtTokenYaConsumido::crear();
        }

        return $this->db->transaction(function () use ($payload, $ahora): ConsumirJwtHandshakeOutput {
            $this->repositorioConsumidos->registrarConsumo(
                $payload->jti,
                $payload->proyectoId,
                $payload->expiraEn,
            );

            $usuario = $this->provisionarUsuario($payload->email, $payload->name);
            $this->garantizarPivotProyecto($usuario->id, $payload->proyectoId, $payload->wrapperRole);

            $this->db->table('users')
                ->where('id', $usuario->id)
                ->update(['ultimo_sso_en' => $ahora->format('Y-m-d H:i:s')]);

            $personaPublicId = $this->resolverPersonaPublicId(
                $payload->proyectoId,
                $payload->identificacion,
                $payload->tipoIdentificacionCodigo,
            );

            return new ConsumirJwtHandshakeOutput(
                usuarioId: (int) $usuario->id,
                proyectoId: $payload->proyectoId,
                redirectPath: $payload->redirectPath,
                personaPublicId: $personaPublicId,
            );
        });
    }

    private function extraerProyectoIdInseguro(string $jwt): int
    {
        $partes = explode('.', $jwt);
        if (count($partes) !== 3) {
            throw JwtMalFormado::crear();
        }

        $payloadRaw = base64_decode(strtr($partes[1], '-_', '+/'), true);
        if ($payloadRaw === false) {
            throw JwtMalFormado::crear();
        }

        $obj = json_decode($payloadRaw);
        if (! is_object($obj) || ! isset($obj->proyecto_id)) {
            throw JwtMalFormado::crear();
        }

        $proyectoId = (int) $obj->proyecto_id;
        if ($proyectoId <= 0) {
            throw JwtMalFormado::crear();
        }

        return $proyectoId;
    }

    private function provisionarUsuario(string $email, string $name): User
    {
        $existente = User::query()->where('email', $email)->first();

        if ($existente !== null) {
            $cambios = [];
            if ($existente->name !== $name) {
                $cambios['name'] = $name;
            }
            if ((bool) $existente->activo !== true) {
                $cambios['activo'] = true;
            }

            if ($cambios !== []) {
                $existente->forceFill($cambios)->save();
            }

            return $existente;
        }

        $usuario = new User;
        $usuario->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt(Str::random(40)),
            'activo' => true,
            'sso_provisioned' => true,
        ])->save();

        return $usuario;
    }

    private function garantizarPivotProyecto(int $usuarioId, int $proyectoId, ?string $wrapperRole): void
    {
        $codigoRol = MapeoRolWrapper::aCodigoRolBase($wrapperRole);
        $rolId = (int) $this->db->table('roles')
            ->where('codigo', $codigoRol)
            ->where('activo', true)
            ->value('id');

        if ($rolId === 0) {
            // Fallback hard: si por alguna razón el rol mapeado no existe, intenta GESTOR.
            $rolId = (int) $this->db->table('roles')
                ->where('codigo', 'GESTOR')
                ->where('activo', true)
                ->value('id');
        }

        $existePivotActivo = $this->db->table('usuario_proyecto_rol')
            ->where('usuario_id', $usuarioId)
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->exists();

        if ($existePivotActivo) {
            return;
        }

        $this->db->table('usuario_proyecto_rol')->insert([
            'usuario_id' => $usuarioId,
            'proyecto_id' => $proyectoId,
            'rol_id' => $rolId,
            'activo' => true,
        ]);
    }

    private function resolverPersonaPublicId(
        int $proyectoId,
        ?string $identificacion,
        ?string $tipoIdentificacionCodigo,
    ): ?string {
        if ($identificacion === null || $tipoIdentificacionCodigo === null) {
            return null;
        }

        $row = $this->db->table('personas as p')
            ->join('tipos_identificacion as ti', 'ti.id', '=', 'p.tipo_identificacion_id')
            ->where('p.proyecto_id', $proyectoId)
            ->where('ti.codigo', $tipoIdentificacionCodigo)
            ->where('p.identificacion', $identificacion)
            ->whereNull('p.eliminada_en')
            ->select('p.public_id')
            ->first();

        return $row?->public_id !== null ? (string) $row->public_id : null;
    }
}
