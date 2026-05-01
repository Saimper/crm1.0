<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Controllers;

use App\Modules\Integracion\Application\DTOs\EmitirSanctumTokenInput;
use App\Modules\Integracion\Application\UseCases\EmitirSanctumTokenDesdeJwt;
use App\Modules\Integracion\Domain\Exceptions\JwtClaimsIncompletos;
use App\Modules\Integracion\Domain\Exceptions\JwtFirmaInvalida;
use App\Modules\Integracion\Domain\Exceptions\JwtMalFormado;
use App\Modules\Integracion\Domain\Exceptions\JwtTokenYaConsumido;
use App\Modules\Integracion\Domain\Exceptions\JwtTtlExcedido;
use App\Modules\Integracion\Domain\Exceptions\ProyectoSsoNoConfigurado;
use App\Modules\Integracion\Domain\Exceptions\WrapperRoleNoPermitido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class EmitirSanctumTokenController
{
    public function __construct(
        private readonly EmitirSanctumTokenDesdeJwt $emitir,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $jwt = (string) $request->input('token');

        try {
            $output = $this->emitir->execute(new EmitirSanctumTokenInput($jwt));
        } catch (JwtMalFormado|JwtClaimsIncompletos|WrapperRoleNoPermitido $e) {
            Log::warning('sanctum-token: payload inválido', ['error' => $e->getMessage()]);
            throw new HttpException(400, $e->getMessage());
        } catch (JwtFirmaInvalida $e) {
            Log::warning('sanctum-token: firma inválida', ['error' => $e->getMessage()]);
            throw new HttpException(401, 'Token inválido.');
        } catch (JwtTtlExcedido $e) {
            throw new HttpException(400, $e->getMessage());
        } catch (JwtTokenYaConsumido) {
            throw new HttpException(410, 'Token ya consumido.');
        } catch (ProyectoSsoNoConfigurado $e) {
            Log::warning('sanctum-token: proyecto sin sso_secret', ['error' => $e->getMessage()]);
            throw new HttpException(404, 'Proyecto inexistente o sin SSO configurado.');
        }

        return response()->json([
            'access_token' => $output->accessToken,
            'token_type' => 'Bearer',
            'usuario_id' => $output->usuarioId,
            'proyecto_id' => $output->proyectoId,
        ], 201);
    }
}
