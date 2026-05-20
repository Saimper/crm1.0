<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;

/**
 * Input DTO para ProcesarFilaDinamica.
 *
 * Los lookups de tipos_identificacion, personas y casos se cargan una vez
 * por chunk en EjecutarImportacionDinamica y se pasan aquí para evitar
 * N+1 queries por fila.
 */
final readonly class ProcesarFilaInput
{
    /**
     * @param array<string, string> $fila
     * @param array<string, array{id: int, tipo: string}> $mapaCampos
     * @param array<string, int> $tiposIdentificacion codigo → id
     * @param array<string, int> $personasExistentes "tipoIdentId:identificacion" → personaId
     * @param array<string, int> $casosExistentes "valorUnique" → casoId
     */
    public function __construct(
        public array $fila,
        public EsquemaImportacion $esquema,
        public int $importacionFilaId,
        public array $mapaCampos,
        public array $tiposIdentificacion = [],
        public array $personasExistentes = [],
        public array $casosExistentes = [],
    ) {}
}
