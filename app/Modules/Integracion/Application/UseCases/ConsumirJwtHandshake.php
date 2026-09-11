<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Application\UseCases;

use App\Modules\Integracion\Application\DTOs\ConsumirJwtHandshakeInput;
use App\Modules\Integracion\Application\DTOs\ConsumirJwtHandshakeOutput;
use App\Modules\Integracion\Application\Services\AutenticadorPorJwt;
use App\Modules\Integracion\Domain\ValueObjects\PayloadJwt;
use App\Support\Database\CarterasOperativas;
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
        $ficha = $payload->proyectoId !== null && $resultado->usuario->tienePermiso('casos.ver', $payload->proyectoId)
            ? $this->localizarFicha($payload, $resultado->usuario->carterasPermitidasParaPermiso('casos.ver', $payload->proyectoId))
            : self::SIN_FICHA;

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
     * @param  list<int>|null  $carterasPermitidas
     * @return array{personaPublicId: ?string, casoPublicId: ?string, identificacionNoResuelta: ?string, ambigua: bool}
     */
    private function localizarFicha(PayloadJwt $payload, ?array $carterasPermitidas): array
    {
        if ($payload->proyectoId === null) {
            return self::SIN_FICHA;
        }

        if ($payload->numeroPrestamo !== null) {
            $caso = $this->resolverCasoPorNumeroPrestamo($payload->proyectoId, $payload->numeroPrestamo, $carterasPermitidas);
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
            $carterasPermitidas,
        );
    }

    /**
     * @param  list<int>|null  $carterasPermitidas
     * @return array{personaPublicId: string, casoPublicId: string}|null
     */
    private function resolverCasoPorNumeroPrestamo(int $proyectoId, string $numeroPrestamo, ?array $carterasPermitidas): ?array
    {
        $row = CarterasOperativas::casos($this->db, $proyectoId, $carterasPermitidas)
            ->join('casos_cobranza as cc', fn ($j) => $j->on('cc.caso_id', '=', 'c.id')->on('cc.proyecto_id', '=', 'c.proyecto_id'))
            ->join('personas as p', fn ($j) => $j->on('p.id', '=', 'c.persona_id')->on('p.proyecto_id', '=', 'c.proyecto_id'))
            ->where('c.tipo_caso', 'cobranza')
            ->where('cc.numero_prestamo', $numeroPrestamo)
            ->whereNull('p.eliminada_en')
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
     * @param  list<int>|null  $carterasPermitidas
     * @return array{personaPublicId: ?string, casoPublicId: null, identificacionNoResuelta: ?string, ambigua: bool}
     */
    private function resolverPorIdentificacion(int $proyectoId, string $identificacion, ?string $tipoCodigo, ?array $carterasPermitidas): array
    {
        $personas = $this->db->table('personas as p')
            ->join('tipos_identificacion as ti', 'ti.id', '=', 'p.tipo_identificacion_id')
            ->where('p.proyecto_id', $proyectoId)
            ->where('p.identificacion', $identificacion)
            ->whereNull('p.eliminada_en')
            ->when($tipoCodigo !== null, fn ($q) => $q->where('ti.codigo', $tipoCodigo))
            ->limit(2)
            ->get(['p.id', 'p.public_id']);

        if ($personas->count() === 1) {
            $persona = $personas->first();
            $disponible = CarterasOperativas::casos($this->db, $proyectoId, $carterasPermitidas)
                ->where('c.persona_id', $persona->id)->exists();
            if (! $disponible && $this->db->table('casos')->where('proyecto_id', $proyectoId)->where('persona_id', $persona->id)->exists()) {
                // The identity exists: do not suggest creating it again or anchor an archived-only record.
                return self::SIN_FICHA;
            }

            return [...self::SIN_FICHA, 'personaPublicId' => (string) $persona->public_id];
        }

        return [
            ...self::SIN_FICHA,
            'identificacionNoResuelta' => $identificacion,
            'ambigua' => $personas->count() > 1,
        ];
    }
}
