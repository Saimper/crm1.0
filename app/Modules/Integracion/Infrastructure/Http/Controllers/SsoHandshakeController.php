<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Controllers;

use App\Modules\Integracion\Application\DTOs\ConsumirJwtHandshakeInput;
use App\Modules\Integracion\Application\DTOs\ConsumirJwtHandshakeOutput;
use App\Modules\Integracion\Application\UseCases\ConsumirJwtHandshake;
use App\Modules\Integracion\Domain\Exceptions\JwtClaimsIncompletos;
use App\Modules\Integracion\Domain\Exceptions\JwtFirmaInvalida;
use App\Modules\Integracion\Domain\Exceptions\JwtMalFormado;
use App\Modules\Integracion\Domain\Exceptions\JwtTokenYaConsumido;
use App\Modules\Integracion\Domain\Exceptions\JwtTtlExcedido;
use App\Modules\Integracion\Domain\Exceptions\ProyectoSsoNoConfigurado;
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

        try {
            $output = $this->consumirJwtHandshake->execute(new ConsumirJwtHandshakeInput($jwt));
        } catch (JwtMalFormado|JwtClaimsIncompletos|WrapperRoleNoPermitido $e) {
            Log::warning('handshake jwt: payload inválido', ['error' => $e->getMessage()]);
            throw new HttpException(400, $e->getMessage());
        } catch (JwtFirmaInvalida $e) {
            Log::warning('handshake jwt: firma inválida', ['error' => $e->getMessage()]);
            throw new HttpException(401, 'Token inválido.');
        } catch (JwtTtlExcedido $e) {
            throw new HttpException(400, $e->getMessage());
        } catch (JwtTokenYaConsumido) {
            throw new HttpException(410, 'Token ya consumido.');
        } catch (ProyectoSsoNoConfigurado $e) {
            Log::warning('handshake jwt: proyecto sin sso_secret', ['error' => $e->getMessage()]);
            throw new HttpException(404, 'Proyecto inexistente o sin SSO configurado.');
        }

        Auth::loginUsingId($output->usuarioId);
        $request->session()->regenerate();

        return redirect()->to($this->resolverDestino($output));
    }

    private function resolverDestino(ConsumirJwtHandshakeOutput $output): string
    {
        $path = $output->redirectPath;
        if (is_string($path) && $path !== '' && str_starts_with($path, '/') && ! str_contains($path, '://')) {
            return $path;
        }

        if ($output->personaPublicId !== null) {
            return "/proyectos/{$output->proyectoId}/trabajo/{$output->personaPublicId}";
        }

        return "/proyectos/{$output->proyectoId}/bandeja";
    }
}
