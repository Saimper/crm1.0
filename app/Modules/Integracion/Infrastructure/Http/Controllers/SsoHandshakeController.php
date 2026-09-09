<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Controllers;

use App\Modules\Integracion\Application\DTOs\ConsumirJwtHandshakeInput;
use App\Modules\Integracion\Application\DTOs\ConsumirJwtHandshakeOutput;
use App\Modules\Integracion\Application\UseCases\ConsumirJwtHandshake;
use App\Modules\Integracion\Domain\Exceptions\JwtClaimsIncompletos;
use App\Modules\Integracion\Domain\Exceptions\JwtExpirado;
use App\Modules\Integracion\Domain\Exceptions\JwtFirmaInvalida;
use App\Modules\Integracion\Domain\Exceptions\JwtMalFormado;
use App\Modules\Integracion\Domain\Exceptions\JwtTokenYaConsumido;
use App\Modules\Integracion\Domain\Exceptions\JwtTtlExcedido;
use App\Modules\Integracion\Domain\Exceptions\MandanteProyectoMismatch;
use App\Modules\Integracion\Domain\Exceptions\MandanteSsoNoConfigurado;
use App\Modules\Integracion\Domain\Exceptions\UsuarioDesactivadoNoPuedeEntrarPorSso;
use App\Modules\Integracion\Domain\Exceptions\UsuarioGlobalNoPermitidoPorSso;
use App\Modules\Integracion\Domain\Exceptions\UsuarioNoPerteneceAlMandante;
use App\Modules\Integracion\Domain\Exceptions\WrapperRoleNoPermitido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class SsoHandshakeController
{
    public function __construct(
        private readonly ConsumirJwtHandshake $consumirJwtHandshake,
    ) {}

    public function consumir(Request $request): RedirectResponse
    {
        $jwt = (string) $request->query('token', '');

        if ($jwt === '') {
            throw new HttpException(400, 'Token requerido.');
        }

        $output = $this->consumirOAbortar($jwt);

        Auth::loginUsingId($output->usuarioId);
        $request->session()->regenerate();

        // El CRM solo entra por handshake cuando el wrapper lo embebe en su
        // iframe. Marcamos la sesión como embebida para que el nav oculte el
        // logout/perfil (la sesión la gestiona la app principal).
        $request->session()->put('crm_embedded', true);

        $this->recordarContextoDeLlamada($request, $output);

        return redirect()->to($this->resolverDestino($output));
    }

    private function consumirOAbortar(string $jwt): ConsumirJwtHandshakeOutput
    {
        try {
            return $this->consumirJwtHandshake->execute(new ConsumirJwtHandshakeInput($jwt));
        } catch (JwtMalFormado|JwtClaimsIncompletos|WrapperRoleNoPermitido $e) {
            Log::warning('handshake jwt: payload inválido', ['error' => $e->getMessage()]);
            throw new HttpException(400, $e->getMessage());
        } catch (JwtFirmaInvalida $e) {
            Log::warning('handshake jwt: firma inválida', ['error' => $e->getMessage()]);
            throw new HttpException(401, 'Token inválido.');
        } catch (JwtExpirado) {
            throw new HttpException(401, 'Token expirado.');
        } catch (JwtTtlExcedido $e) {
            throw new HttpException(400, $e->getMessage());
        } catch (JwtTokenYaConsumido) {
            throw new HttpException(410, 'Token ya consumido.');
        } catch (MandanteSsoNoConfigurado $e) {
            Log::warning('handshake jwt: mandante sin sso_secret', ['error' => $e->getMessage()]);
            throw new HttpException(404, 'Mandante inexistente o sin SSO configurado.');
        } catch (MandanteProyectoMismatch $e) {
            Log::warning('handshake jwt: proyecto no pertenece al mandante', ['error' => $e->getMessage()]);
            throw new HttpException(403, 'Proyecto no pertenece al mandante.');
        } catch (UsuarioGlobalNoPermitidoPorSso|UsuarioNoPerteneceAlMandante|UsuarioDesactivadoNoPuedeEntrarPorSso $e) {
            Log::warning('handshake jwt: identidad rechazada', ['error' => $e->getMessage()]);
            // Mensaje deliberadamente generico: distinguir "no existe" de "no es
            // tuyo" de "esta desactivado" seria un oraculo de enumeracion para
            // quien tenga un sso_secret.
            throw new HttpException(403, 'Acceso no permitido para esta identidad.');
        }
    }

    /**
     * Writeback CRM→ViciDial: si el wrapper adjuntó un sync_ref (hay lead activo),
     * lo persistimos junto al mandante_id del MISMO handshake (claim JWT). El webhook
     * de writeback usa ese mandante_id como X-Mandante-Id para que coincida con el
     * tenant que emitió el sync_ref (el wrapper falla 401/403 si no coincide).
     *
     * La persona que abrió el handshake queda anclada aparte: el writeback sólo
     * debe escribir en el lead lo que se edite en ESA ficha, no en cualquier otra a
     * la que el gestor navegue durante la misma sesión.
     */
    private function recordarContextoDeLlamada(Request $request, ConsumirJwtHandshakeOutput $output): void
    {
        if ($output->syncRef !== null) {
            $request->session()->put('crm_sync_ref', $output->syncRef);
            $request->session()->put('crm_mandante_id', $output->mandanteId);
        } else {
            $request->session()->forget(['crm_sync_ref', 'crm_mandante_id']);
        }

        if ($output->personaPublicId !== null) {
            $request->session()->put('crm_persona_public_id', $output->personaPublicId);
        } else {
            $request->session()->forget('crm_persona_public_id');
        }
    }

    private function resolverDestino(ConsumirJwtHandshakeOutput $output): string
    {
        $path = $output->redirectPath;
        if (is_string($path) && $path !== '' && str_starts_with($path, '/') && ! str_contains($path, '://')) {
            return $path;
        }

        if ($output->proyectoId === null) {
            return "/dashboard?mandante={$output->mandanteId}";
        }

        if ($output->personaPublicId !== null) {
            $url = "/proyectos/{$output->proyectoId}/trabajo/{$output->personaPublicId}";

            return $output->casoPublicId !== null ? "{$url}/{$output->casoPublicId}" : $url;
        }

        return $this->destinoSinFicha($output);
    }

    /**
     * Sin ficha, la bandeja. Si el wrapper mandó una identificación y no
     * resolvió, la bandeja la recibe para avisar y ofrecer crear la persona
     * con los datos de la llamada, en vez de aterrizar en silencio.
     */
    private function destinoSinFicha(ConsumirJwtHandshakeOutput $output): string
    {
        $bandeja = "/proyectos/{$output->proyectoId}/bandeja";

        if ($output->identificacionNoResuelta === null) {
            return $bandeja;
        }

        $query = array_filter([
            'sin_persona' => $output->identificacionNoResuelta,
            'tipo' => $output->tipoIdentificacionCodigo,
            'ambigua' => $output->identificacionAmbigua ? '1' : null,
        ], static fn (?string $v): bool => $v !== null);

        return $bandeja.'?'.http_build_query($query);
    }
}
