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

        $casoResult = $resultado->payload->proyectoId !== null
            && $resultado->payload->numeroPrestamo !== null
                ? $this->resolverCasoPorNumeroPrestamo(
                    $resultado->payload->proyectoId,
                    $resultado->payload->numeroPrestamo,
                )
                : null;

        $personaPublicId = $casoResult !== null
            ? $casoResult['personaPublicId']
            : ($resultado->payload->proyectoId === null
                ? null
                : $this->resolverPersonaPublicId(
                    $resultado->payload->proyectoId,
                    $resultado->payload->identificacion,
                    $resultado->payload->tipoIdentificacionCodigo,
                ));

        return new ConsumirJwtHandshakeOutput(
            usuarioId: (int) $resultado->usuario->id,
            mandanteId: $resultado->payload->mandanteId,
            proyectoId: $resultado->payload->proyectoId,
            redirectPath: $resultado->payload->redirectPath,
            personaPublicId: $personaPublicId,
            casoPublicId: $casoResult['casoPublicId'] ?? null,
        );
    }

    private function resolverCasoPorNumeroPrestamo(
        int $proyectoId,
        string $numeroPrestamo,
    ): ?array {
        $row = $this->db->table('casos_cobranza as cc')
            ->join('casos as c', 'c.id', '=', 'cc.caso_id')
            ->join('personas as p', 'p.id', '=', 'c.persona_id')
            ->where('cc.proyecto_id', $proyectoId)
            ->where('cc.numero_prestamo', $numeroPrestamo)
            ->whereNull('c.eliminada_en')
            ->select('p.public_id as persona_public_id', 'c.public_id as caso_public_id')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'personaPublicId' => (string) $row->persona_public_id,
            'casoPublicId' => (string) $row->caso_public_id,
        ];
    }

    private function resolverPersonaPublicId(
        int $proyectoId,
        ?string $identificacion,
        ?string $tipoIdentificacionCodigo,
    ): ?string {
        if ($identificacion === null) {
            return null;
        }

        $query = $this->db->table('personas as p')
            ->join('tipos_identificacion as ti', 'ti.id', '=', 'p.tipo_identificacion_id')
            ->where('p.proyecto_id', $proyectoId)
            ->where('p.identificacion', $identificacion)
            ->whereNull('p.eliminada_en')
            ->select('p.public_id');

        if ($tipoIdentificacionCodigo !== null) {
            $query->where('ti.codigo', $tipoIdentificacionCodigo);
        }

        $row = $query->first();

        return $row?->public_id !== null ? (string) $row->public_id : null;
    }
}
