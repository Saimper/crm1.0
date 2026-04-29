<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\UseCases;

use App\Modules\Integracion\Application\DTOs\EmitirTokenSsoInput;
use App\Modules\Integracion\Application\DTOs\EmitirTokenSsoOutput;
use App\Modules\Integracion\Domain\Contracts\RepositorioTokenSso;
use App\Modules\Integracion\Domain\Entities\TokenSso;
use App\Modules\Integracion\Domain\ValueObjects\TokenClaroHash;
use DateTimeImmutable;
use Illuminate\Support\Str;

final class EmitirTokenSso
{
    public function __construct(
        private readonly RepositorioTokenSso $repositorio,
    ) {}

    public function execute(EmitirTokenSsoInput $input): EmitirTokenSsoOutput
    {
        $ttl = (int) config('integracion.token_sso_ttl_segundos', 300);

        $tokenClaro = Str::random(64);
        $tokenClaroHash = TokenClaroHash::generar($tokenClaro);
        $publicId = Str::ulid()->toRfc4122();
        $expiraEn = new DateTimeImmutable("+{$ttl} seconds");

        $token = TokenSso::crear(
            publicId: $publicId,
            usuarioId: $input->usuarioId,
            tokenClaroHash: $tokenClaroHash,
            expiraEn: $expiraEn,
            proyectoId: $input->proyectoId,
            identificacion: $input->identificacion,
            tipoIdentificacionCodigo: $input->tipoIdentificacionCodigo,
            redirectPath: $input->redirectPath,
            ipOrigen: $input->ipOrigen,
            userAgent: $input->userAgent,
        );

        $this->repositorio->guardar($token);

        $handshakeUrl = rtrim((string) config('app.url'), '/').'/integracion/handshake?token='.urlencode($tokenClaro);

        return new EmitirTokenSsoOutput(
            handshakeUrl: $handshakeUrl,
            expiraEn: $expiraEn,
        );
    }
}
