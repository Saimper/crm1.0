<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Infrastructure\Http\Controllers;

use App\Models\User;
use App\Modules\Integracion\Application\DTOs\ConsumirTokenSsoInput;
use App\Modules\Integracion\Application\DTOs\ConsumirTokenSsoOutput;
use App\Modules\Integracion\Application\DTOs\EmitirTokenSsoInput;
use App\Modules\Integracion\Application\UseCases\ConsumirTokenSso;
use App\Modules\Integracion\Application\UseCases\EmitirTokenSso;
use App\Modules\Integracion\Domain\Exceptions\TokenSsoExpiradoException;
use App\Modules\Integracion\Domain\Exceptions\TokenSsoInvalidoException;
use App\Modules\Integracion\Domain\Exceptions\TokenSsoYaConsumidoException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class SsoHandshakeController
{
    public function __construct(
        private readonly EmitirTokenSso $emitirTokenSso,
        private readonly ConsumirTokenSso $consumirTokenSso,
    ) {}

    public function emitir(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'proyecto_id' => ['nullable', 'integer'],
            'identificacion' => ['nullable', 'string', 'max:50'],
            'tipo_identificacion_codigo' => ['nullable', 'string', 'max:20'],
            'redirect_path' => ['nullable', 'string', 'max:500'],
        ]);

        $credentials = [
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ];

        if (! Auth::validate($credentials)) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        /** @var User $usuario */
        $usuario = User::where('email', $request->input('email'))
            ->where('activo', true)
            ->first();

        if ($usuario === null) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        $output = $this->emitirTokenSso->execute(new EmitirTokenSsoInput(
            usuarioId: (int) $usuario->id,
            proyectoId: $request->input('proyecto_id') !== null ? (int) $request->input('proyecto_id') : null,
            identificacion: $request->input('identificacion'),
            tipoIdentificacionCodigo: $request->input('tipo_identificacion_codigo'),
            redirectPath: $request->input('redirect_path'),
            ipOrigen: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        return response()->json([
            'handshake_url' => $output->handshakeUrl,
            'expira_en' => $output->expiraEn->format('Y-m-d\TH:i:sP'),
        ]);
    }

    public function consumir(Request $request): RedirectResponse
    {
        $tokenClaro = (string) $request->query('token', '');

        if ($tokenClaro === '') {
            abort(410, 'Token inválido o expirado.');
        }

        try {
            $output = $this->consumirTokenSso->execute(new ConsumirTokenSsoInput($tokenClaro));
        } catch (TokenSsoInvalidoException|TokenSsoExpiradoException|TokenSsoYaConsumidoException) {
            abort(410, 'Token inválido o expirado.');
        }

        Auth::loginUsingId($output->usuarioId);
        $request->session()->regenerate();

        return redirect()->to($this->resolverDestino($output));
    }

    private function resolverDestino(ConsumirTokenSsoOutput $output): string
    {
        if ($output->redirectPath !== null && $output->redirectPath !== '') {
            // Rechazar paths absolutos
            if (str_starts_with($output->redirectPath, 'http://') || str_starts_with($output->redirectPath, 'https://')) {
                return '/proyectos';
            }

            return $output->redirectPath;
        }

        if ($output->proyectoId !== null && $output->personaPublicId !== null) {
            return "/proyectos/{$output->proyectoId}/trabajo/{$output->personaPublicId}";
        }

        if ($output->proyectoId !== null) {
            return "/proyectos/{$output->proyectoId}/bandeja";
        }

        return '/proyectos';
    }
}
