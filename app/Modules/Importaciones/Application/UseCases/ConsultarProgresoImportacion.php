<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Application\DTOs\ProgresoImportacion;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionNoEncontrada;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;

final readonly class ConsultarProgresoImportacion
{
    public function execute(int $importacionId): ProgresoImportacion
    {
        /** @var ImportacionModel|null $i */
        $i = ImportacionModel::query()->sinScopeProyecto()->find($importacionId);
        if ($i === null) {
            throw ImportacionNoEncontrada::conId($importacionId);
        }

        return new ProgresoImportacion(
            id: (int) $i->id,
            publicId: (string) $i->public_id,
            estado: EstadoImportacion::from((string) $i->estado),
            modo: ModoImportacion::from((string) $i->modo),
            totalFilas: (int) $i->total_filas,
            procesadas: (int) $i->procesadas,
            insertadas: (int) ($i->insertadas ?? 0),
            actualizadas: (int) ($i->actualizadas ?? 0),
            invalidas: (int) $i->invalidas,
            omitidas: (int) $i->omitidas,
            duplicadas: (int) $i->duplicadas,
            iniciadoEn: $i->iniciado_en !== null ? (string) $i->iniciado_en : null,
            terminadoEn: $i->terminado_en !== null ? (string) $i->terminado_en : null,
            errorGlobal: $i->error_global !== null ? (string) $i->error_global : null,
        );
    }
}
