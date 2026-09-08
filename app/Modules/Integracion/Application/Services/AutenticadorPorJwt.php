<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\Services;

use App\Models\User;
use App\Modules\Integracion\Domain\Contracts\RepositorioTokensConsumidos;
use App\Modules\Integracion\Domain\Exceptions\JwtExpirado;
use App\Modules\Integracion\Domain\Exceptions\JwtFirmaInvalida;
use App\Modules\Integracion\Domain\Exceptions\JwtMalFormado;
use App\Modules\Integracion\Domain\Exceptions\JwtTokenYaConsumido;
use App\Modules\Integracion\Domain\Exceptions\MandanteProyectoMismatch;
use App\Modules\Integracion\Domain\Exceptions\MandanteSsoNoConfigurado;
use App\Modules\Integracion\Domain\Exceptions\UsuarioDesactivadoNoPuedeEntrarPorSso;
use App\Modules\Integracion\Domain\Exceptions\UsuarioGlobalNoPermitidoPorSso;
use App\Modules\Integracion\Domain\Exceptions\UsuarioNoPerteneceAlMandante;
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

        if ($this->repositorioConsumidos->fueConsumido($payload->jti, (int) $mandante->id)) {
            throw JwtTokenYaConsumido::crear();
        }

        return $this->db->transaction(function () use ($payload, $ahora, $mandante): ResultadoAutenticacionJwt {
            $this->repositorioConsumidos->registrarConsumo(
                $payload->jti,
                (int) $mandante->id,
                $payload->proyectoId,
                $payload->expiraEn,
            );

            $usuario = $this->provisionarUsuario($payload->email, $payload->name, (int) $mandante->id);

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
        } catch (ExpiredException) {
            throw JwtExpirado::crear();
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
        } catch (ExpiredException) {
            throw JwtExpirado::crear();
        } catch (SignatureInvalidException $e) {
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

    /**
     * Resuelve (o crea) el usuario del handshake ligandolo SIEMPRE al mandante
     * que firmo el token.
     *
     * El email por si solo no identifica a nadie de forma segura: es un dato que
     * el emisor del JWT elige. Como cada mandante firma con su propio secret,
     * resolver solo por email permitia que un tenant reclamara la identidad de
     * un usuario de otro tenant — o la del administrador global — con un token
     * perfectamente valido. La pertenencia al mandante es la ligadura que
     * convierte el email en una identidad de verdad.
     */
    private function provisionarUsuario(string $email, string $name, int $mandanteId): User
    {
        $emailNormalizado = strtolower(trim($email));

        $existente = User::query()->where('email', $emailNormalizado)->first();

        if ($existente !== null) {
            $usuarioId = (int) $existente->id;

            // Un rol global no esta acotado a ningun mandante: concederlo por
            // SSO significaria que cualquier tenant puede alcanzarlo. La cuenta
            // global entra por /login, nunca por handshake.
            if ($this->tieneRolGlobal($usuarioId)) {
                throw UsuarioGlobalNoPermitidoPorSso::crear($usuarioId);
            }

            // Desactivar a alguien es como se le retira el acceso. Antes el SSO
            // lo reactivaba en silencio, asi que el wrapper podia resucitar una
            // cuenta dada de baja en el CRM.
            if ((bool) $existente->activo !== true) {
                throw UsuarioDesactivadoNoPuedeEntrarPorSso::crear($usuarioId);
            }

            if (! $this->perteneceAlMandante($usuarioId, $mandanteId)) {
                throw UsuarioNoPerteneceAlMandante::crear($usuarioId, $mandanteId);
            }

            $cambios = [];
            if ($existente->name !== $name) {
                $cambios['name'] = $name;
            }
            // Adopcion suave: el usuario ya quedo verificado como perteneciente
            // a este mandante, asi que se le fija el origen si venia vacio
            // (cuentas anteriores a esta columna).
            if ($existente->mandante_origen_id === null) {
                $cambios['mandante_origen_id'] = $mandanteId;
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
            'mandante_origen_id' => $mandanteId,
        ])->save();

        return $usuario;
    }

    /** Rol sin scope de proyecto (ADMIN_GLOBAL y cualquier otro global). */
    private function tieneRolGlobal(int $usuarioId): bool
    {
        return $this->db->table('usuario_global_rol')
            ->where('usuario_id', $usuarioId)
            ->exists();
    }

    /**
     * El usuario esta ligado al mandante si lo provisiono ese mandante, o si
     * tiene un pivot activo con el — sea a nivel de mandante o de cualquiera de
     * sus proyectos (rol base o rol custom).
     */
    private function perteneceAlMandante(int $usuarioId, int $mandanteId): bool
    {
        $origen = $this->db->table('users')
            ->where('id', $usuarioId)
            ->value('mandante_origen_id');

        if ($origen !== null && (int) $origen === $mandanteId) {
            return true;
        }

        $porMandante = $this->db->table('usuario_mandante_rol')
            ->where('usuario_id', $usuarioId)
            ->where('mandante_id', $mandanteId)
            ->where('activo', true)
            ->exists();

        if ($porMandante) {
            return true;
        }

        $porProyecto = $this->db->table('usuario_proyecto_rol as upr')
            ->join('proyectos as p', 'p.id', '=', 'upr.proyecto_id')
            ->where('upr.usuario_id', $usuarioId)
            ->where('p.mandante_id', $mandanteId)
            ->where('upr.activo', true)
            ->exists();

        if ($porProyecto) {
            return true;
        }

        return $this->db->table('usuario_proyecto_rol_custom as uprc')
            ->join('proyectos as p', 'p.id', '=', 'uprc.proyecto_id')
            ->where('uprc.usuario_id', $usuarioId)
            ->where('p.mandante_id', $mandanteId)
            ->where('uprc.activo', true)
            ->exists();
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
