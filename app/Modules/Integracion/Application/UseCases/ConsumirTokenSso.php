<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\UseCases;

use App\Modules\Integracion\Application\DTOs\ConsumirTokenSsoInput;
use App\Modules\Integracion\Application\DTOs\ConsumirTokenSsoOutput;
use App\Modules\Integracion\Domain\Contracts\RepositorioTokenSso;
use App\Modules\Integracion\Domain\Exceptions\TokenSsoInvalidoException;
use App\Modules\Integracion\Domain\ValueObjects\TokenClaroHash;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class ConsumirTokenSso
{
    public function __construct(
        private readonly RepositorioTokenSso $repositorio,
    ) {}

    public function execute(ConsumirTokenSsoInput $input): ConsumirTokenSsoOutput
    {
        $hash = TokenClaroHash::soloHash($input->tokenClaro)->hash;
        $token = $this->repositorio->buscarPorHash($hash);

        if ($token === null) {
            throw TokenSsoInvalidoException::noEncontrado();
        }

        $ahora = new DateTimeImmutable('now');
        $token->consumir($ahora);

        $this->repositorio->marcarConsumido($token->publicId, $ahora);

        $personaPublicId = $this->resolverPersonaPublicId(
            $token->proyectoId,
            $token->identificacion,
            $token->tipoIdentificacionCodigo,
        );

        return new ConsumirTokenSsoOutput(
            usuarioId: $token->usuarioId,
            proyectoId: $token->proyectoId,
            redirectPath: $token->redirectPath,
            personaPublicId: $personaPublicId,
        );
    }

    private function resolverPersonaPublicId(
        ?int $proyectoId,
        ?string $identificacion,
        ?string $tipoIdentificacionCodigo,
    ): ?string {
        if ($proyectoId === null || $identificacion === null || $tipoIdentificacionCodigo === null) {
            return null;
        }

        $row = DB::table('personas as p')
            ->join('tipos_identificacion as ti', 'ti.id', '=', 'p.tipo_identificacion_id')
            ->where('p.proyecto_id', $proyectoId)
            ->where('ti.codigo', $tipoIdentificacionCodigo)
            ->where('p.identificacion', $identificacion)
            ->whereNull('p.eliminada_en')
            ->select('p.public_id')
            ->first();

        return $row?->public_id;
    }
}
