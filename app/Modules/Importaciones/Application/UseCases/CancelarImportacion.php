<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionNoEncontrada;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Marca la importación como cancelada. El Job revisa estado entre chunks
 * y abandona en la siguiente iteración.
 */
final readonly class CancelarImportacion
{
    public function execute(int $importacionId): void
    {
        DB::transaction(function () use ($importacionId): void {
            /** @var ImportacionModel|null $i */
            $i = ImportacionModel::query()
                ->sinScopeProyecto()
                ->where('id', $importacionId)
                ->lockForUpdate()
                ->first();

            if ($i === null) {
                throw ImportacionNoEncontrada::conId($importacionId);
            }

            $estado = EstadoImportacion::from((string) $i->estado);
            if ($estado->esTerminal()) {
                return;
            }

            $i->estado = EstadoImportacion::CANCELADA->value;
            $i->terminado_en = CarbonImmutable::now();
            $i->save();
        });
    }
}
