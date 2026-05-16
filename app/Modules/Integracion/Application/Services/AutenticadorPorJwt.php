<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\Services;

use App\Models\User;
use App\Modules\Integracion\Domain\Contracts\RepositorioTokensConsumidos;
use App\Modules\Integracion\Domain\Exceptions\JwtFirmaInvalida;
use App\Modules\Integracion\Domain\Exceptions\JwtMalFormado;
use App\Modules\Integracion\Domain\Exceptions\JwtTokenYaConsumido;
use App\Modules\Integracion\Domain\Exceptions\MandanteProyectoMismatch;
use App\Modules\Integracion\Domain\Exceptions\MandanteSsoNoConfigurado;
use App\Modules\Integracion\Domain\ValueObjects\MapeoRolWrapper;
use App\Modules\Integracion\Domain\ValueObjects\PayloadJwt;
use DateTimeImmutable;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * F37: secret vive en mandantes.sso_secret. JWT trae mandante_id (obligatorio)
 * y proyecto_id (opcional). Si proyecto_id ausente, se autentica al usuario
 * pero no se le asigna pivot — el handshake redirige a SelectorProyecto del
 * mandante. Soporta doble-secret 24h: si firma con secret actual falla,
 * intenta con sso_secret_old si está vigente.
 */
final class AutenticadorPorJwt
{
    private const ALGORITMO = 'HS256';

    private const LEEWAY_SEGUNDOS = 30;

    public function __construct(
        private readonly RepositorioTokensConsumidos $repositorioConsumidos,
        private readonly ConnectionInterface $db,
    ) {}

    public function autenticar(string $jwt): ResultadoAutenticacionJwt
    {
        $mandanteIdAviso = $this->extraerMandanteIdInseguro($jwt);

        $mandante = $this->db->table('mandantes')
            ->where('id', $mandanteIdAviso)
            ->whereNull('eliminada_en')
            ->where('activo', true)
            ->first([
                'id',
                'sso_secret',
                'sso_secret_old',
                'sso_secret_old_expires_at',
            ]);

        if ($mandante === null) {
            throw JwtFirmaInvalida::crear();
        }

        $secret = (string) ($mandante->sso_secret ?? '');
        if ($secret === '') {
            throw MandanteSsoNoConfigurado::crear((int) $mandante->id);
        }

        JWT::$leeway = self::LEEWAY_SEGUNDOS;

        $claims = $this->decodificarConSecretVigente($jwt, $mandante);

        $ahora = new DateTimeImmutable('now');
        $payload = PayloadJwt::desdeClaims($claims, $ahora);

        if ($payload->mandanteId !== (int) $mandante->id) {
            throw JwtFirmaInvalida::crear();
        }

        if ($payload->proyectoId !== null) {
            $perteneceAlMandante = $this->db->table('proyectos')
                ->where('id', $payload->proyectoId)
                ->where('mandante_id', $mandante->id)
                ->whereNull('eliminada_en')
                ->where('activo', true)
                ->exists();

            if (! $perteneceAlMandante) {
                throw MandanteProyectoMismatch::crear((int) $mandante->id, $payload->proyectoId);
            }
        }

        if ($this->repositorioConsumidos->fueConsumido($payload->jti)) {
            throw JwtTokenYaConsumido::crear();
        }

        return $this->db->transaction(function () use ($payload, $ahora, $mandante): ResultadoAutenticacionJwt {
            $this->repositorioConsumidos->registrarConsumo(
                $payload->jti,
                (int) $mandante->id,
                $payload->proyectoId,
                $payload->expiraEn,
            );

            $usuario = $this->provisionarUsuario($payload->email, $payload->name);

            $codigoRol = MapeoRolWrapper::aCodigoRolBase($payload->wrapperRole);

            if (MapeoRolWrapper::esRolMandante($codigoRol)) {
                // F38: rol mandante-scoped. Pivot en usuario_mandante_rol cubre
                // todos los proyectos del mandante; no se crea pivot por proyecto.
                $this->garantizarPivotMandante($usuario->id, (int) $mandante->id, $codigoRol);
            } elseif ($payload->proyectoId !== null) {
                $this->garantizarPivotProyecto($usuario->id, $payload->proyectoId, $codigoRol);
            }

            $this->db->table('users')
                ->where('id', $usuario->id)
                ->update(['ultimo_sso_en' => $ahora->format('Y-m-d H:i:s')]);

            return new ResultadoAutenticacionJwt(
                usuario: $usuario,
                payload: $payload,
            );
        });
    }

    /**
     * Intenta decodificar con sso_secret actual; si firma falla y existe
     * sso_secret_old vigente (no expirado), reintenta con el viejo. Esto
     * permite rotación sin downtime de tokens en vuelo durante 24h.
     */
    private function decodificarConSecretVigente(string $jwt, object $mandante): object
    {
        try {
            return JWT::decode($jwt, new Key((string) $mandante->sso_secret, self::ALGORITMO));
        } catch (SignatureInvalidException) {
            // Reintento con secret viejo si está vigente.
        } catch (ExpiredException $e) {
            \Log::warning('jwt decode failed', ['ex' => get_class($e), 'msg' => $e->getMessage()]);
            throw JwtFirmaInvalida::crear();
        } catch (\UnexpectedValueException|\DomainException $e) {
            \Log::warning('jwt decode failed', ['ex' => get_class($e), 'msg' => $e->getMessage()]);
            throw JwtFirmaInvalida::crear();
        }

        $secretOld = (string) ($mandante->sso_secret_old ?? '');
        $expiresAt = $mandante->sso_secret_old_expires_at ?? null;

        if ($secretOld === '' || $expiresAt === null) {
            throw JwtFirmaInvalida::crear();
        }

        $expiresCarbon = Carbon::parse((string) $expiresAt);
        if ($expiresCarbon->isPast()) {
            throw JwtFirmaInvalida::crear();
        }

        try {
            return JWT::decode($jwt, new Key($secretOld, self::ALGORITMO));
        } catch (ExpiredException|SignatureInvalidException $e) {
            \Log::warning('jwt decode failed con secret old', ['ex' => get_class($e), 'msg' => $e->getMessage()]);
            throw JwtFirmaInvalida::crear();
        } catch (\UnexpectedValueException|\DomainException $e) {
            \Log::warning('jwt decode failed con secret old', ['ex' => get_class($e), 'msg' => $e->getMessage()]);
            throw JwtFirmaInvalida::crear();
        }
    }

    private function extraerMandanteIdInseguro(string $jwt): int
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
        if (! is_object($obj) || ! isset($obj->mandante_id)) {
            throw JwtMalFormado::crear();
        }

        $mandanteId = (int) $obj->mandante_id;
        if ($mandanteId <= 0) {
            throw JwtMalFormado::crear();
        }

        return $mandanteId;
    }

    private function provisionarUsuario(string $email, string $name): User
    {
        $emailNormalizado = strtolower(trim($email));

        $existente = User::query()->where('email', $emailNormalizado)->first();

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
            'email' => $emailNormalizado,
            'password' => bcrypt(Str::random(40)),
            'activo' => true,
            'sso_provisioned' => true,
        ])->save();

        return $usuario;
    }

    private function garantizarPivotProyecto(int $usuarioId, int $proyectoId, string $codigoRol): void
    {
        $rolId = $this->resolverRolId($codigoRol);

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

    private function garantizarPivotMandante(int $usuarioId, int $mandanteId, string $codigoRol): void
    {
        $rolId = $this->resolverRolId($codigoRol);

        $existePivotActivo = $this->db->table('usuario_mandante_rol')
            ->where('usuario_id', $usuarioId)
            ->where('mandante_id', $mandanteId)
            ->where('rol_id', $rolId)
            ->where('activo', true)
            ->exists();

        if ($existePivotActivo) {
            return;
        }

        $this->db->table('usuario_mandante_rol')->insert([
            'usuario_id' => $usuarioId,
            'mandante_id' => $mandanteId,
            'rol_id' => $rolId,
            'activo' => true,
        ]);
    }

    private function resolverRolId(string $codigoRol): int
    {
        $rolId = (int) $this->db->table('roles')
            ->where('codigo', $codigoRol)
            ->where('activo', true)
            ->value('id');

        if ($rolId === 0) {
            $rolId = (int) $this->db->table('roles')
                ->where('codigo', 'GESTOR')
                ->where('activo', true)
                ->value('id');
        }

        return $rolId;
    }
}
