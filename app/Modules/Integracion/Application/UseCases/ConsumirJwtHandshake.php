<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\UseCases;

use App\Modules\Integracion\Application\DTOs\ConsumirJwtHandshakeInput;
use App\Modules\Integracion\Application\DTOs\ConsumirJwtHandshakeOutput;
use App\Modules\Integracion\Application\Services\AutenticadorPorJwt;
use Illuminate\Database\ConnectionInterface;

final class ConsumirJwtHandshake
{
    public function __construct(
        private readonly AutenticadorPorJwt $autenticador,
        private readonly ConnectionInterface $db,
    ) {}

    public function execute(ConsumirJwtHandshakeInput $input): ConsumirJwtHandshakeOutput
    {
        $resultado = $this->autenticador->autenticar($input->jwt);

        $personaPublicId = $resultado->payload->proyectoId === null
            ? null
            : $this->resolverPersonaPublicId(
                $resultado->payload->proyectoId,
                $resultado->payload->identificacion,
                $resultado->payload->tipoIdentificacionCodigo,
            );

        return new ConsumirJwtHandshakeOutput(
            usuarioId: (int) $resultado->usuario->id,
            mandanteId: $resultado->payload->mandanteId,
            proyectoId: $resultado->payload->proyectoId,
            redirectPath: $resultado->payload->redirectPath,
            personaPublicId: $personaPublicId,
        );
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
