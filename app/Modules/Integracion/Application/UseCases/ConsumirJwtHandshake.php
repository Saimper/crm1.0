<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\UseCases;

use App\Modules\Integracion\Application\DTOs\ConsumirJwtHandshakeInput;
use App\Modules\Integracion\Application\DTOs\ConsumirJwtHandshakeOutput;
use App\Modules\Integracion\Application\Services\AutenticadorPorJwt;
use App\Modules\Integracion\Domain\ValueObjects\PayloadJwt;
use Illuminate\Database\ConnectionInterface;

final class ConsumirJwtHandshake
{
    /** @var array{personaPublicId: null, casoPublicId: null, identificacionNoResuelta: null, ambigua: false} */
    private const SIN_FICHA = [
        'personaPublicId' => null,
        'casoPublicId' => null,
        'identificacionNoResuelta' => null,
        'ambigua' => false,
    ];

    public function __construct(
        private readonly AutenticadorPorJwt $autenticador,
        private readonly ConnectionInterface $db,
    ) {}

    public function execute(ConsumirJwtHandshakeInput $input): ConsumirJwtHandshakeOutput
    {
        $resultado = $this->autenticador->autenticar($input->jwt);
        $payload = $resultado->payload;
        $ficha = $this->localizarFicha($payload);

        return new ConsumirJwtHandshakeOutput(
            usuarioId: (int) $resultado->usuario->id,
            mandanteId: $payload->mandanteId,
            proyectoId: $payload->proyectoId,
            redirectPath: $payload->redirectPath,
            personaPublicId: $ficha['personaPublicId'],
            casoPublicId: $ficha['casoPublicId'],
            syncRef: $payload->syncRef,
            identificacionNoResuelta: $ficha['identificacionNoResuelta'],
            tipoIdentificacionCodigo: $ficha['identificacionNoResuelta'] !== null
                ? $payload->tipoIdentificacionCodigo
                : null,
            identificacionAmbigua: $ficha['ambigua'],
        );
    }

    /**
     * Orden fijo: clave del caso (numero_prestamo) y, si no resuelve, la
     * identificación de la persona. Sin proyecto no hay dónde buscar.
     *
     * @return array{personaPublicId: ?string, casoPublicId: ?string, identificacionNoResuelta: ?string, ambigua: bool}
     */
    private function localizarFicha(PayloadJwt $payload): array
    {
        if ($payload->proyectoId === null) {
            return self::SIN_FICHA;
        }

        if ($payload->numeroPrestamo !== null) {
            $caso = $this->resolverCasoPorNumeroPrestamo($payload->proyectoId, $payload->numeroPrestamo);
            if ($caso !== null) {
                return [...self::SIN_FICHA, ...$caso];
            }
        }

        if ($payload->identificacion === null) {
            return self::SIN_FICHA;
        }

        return $this->resolverPorIdentificacion(
            $payload->proyectoId,
            $payload->identificacion,
            $payload->tipoIdentificacionCodigo,
        );
    }

    /**
     * @return array{personaPublicId: string, casoPublicId: string}|null
     */
    private function resolverCasoPorNumeroPrestamo(int $proyectoId, string $numeroPrestamo): ?array
    {
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

    /**
     * Una sola persona abre la ficha. Más de una (misma cadena bajo dos tipos
     * de identificación, cuando el wrapper no manda el tipo) no abre ninguna:
     * elegir «la primera» era abrirle al gestor la ficha de otro cliente.
     *
     * @return array{personaPublicId: ?string, casoPublicId: null, identificacionNoResuelta: ?string, ambigua: bool}
     */
    private function resolverPorIdentificacion(int $proyectoId, string $identificacion, ?string $tipoCodigo): array
    {
        $publicIds = $this->db->table('personas as p')
            ->join('tipos_identificacion as ti', 'ti.id', '=', 'p.tipo_identificacion_id')
            ->where('p.proyecto_id', $proyectoId)
            ->where('p.identificacion', $identificacion)
            ->whereNull('p.eliminada_en')
            ->when($tipoCodigo !== null, fn ($q) => $q->where('ti.codigo', $tipoCodigo))
            ->limit(2)
            ->pluck('p.public_id');

        if ($publicIds->count() === 1) {
            return [...self::SIN_FICHA, 'personaPublicId' => (string) $publicIds->first()];
        }

        return [
            ...self::SIN_FICHA,
            'identificacionNoResuelta' => $identificacion,
            'ambigua' => $publicIds->count() > 1,
        ];
    }
}
