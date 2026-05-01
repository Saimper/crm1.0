<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Events\ImportacionEncolada;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionEnCursoNoEditable;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionNoEncontrada;
use App\Modules\Importaciones\Infrastructure\Jobs\EjecutarImportacionJob;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Despacha el job que ejecuta la importación.
 * Solo importaciones en estado PREPARADA pueden encolarse.
 * Marca PROCESANDO + iniciado_en de inmediato (evita doble despacho aunque cola tarde).
 *
 * F34C — pendiente de refactor: este UseCase y los Livewire ImportarPersonas/
 * ImportarCasos importan ImportacionModel directamente desde Infrastructure.
 * Plan F34D+: extraer ImportacionRepository en Domain/Contracts y mover
 * orquestación de status/contadores fuera del Livewire. Por scope F34C la
 * desviación arquitectónica queda diferida con esta nota explícita.
 */
final readonly class EncolarImportacion
{
    public function __construct(
        private BusDispatcher $bus,
        private EventDispatcher $events,
    ) {}

    public function execute(int $importacionId, ModoImportacion $modo): void
    {
        DB::transaction(function () use ($importacionId, $modo): void {
            /** @var ImportacionModel|null $importacion */
            $importacion = ImportacionModel::query()
                ->sinScopeProyecto()
                ->where('id', $importacionId)
                ->lockForUpdate()
                ->first();

            if ($importacion === null) {
                throw ImportacionNoEncontrada::conId($importacionId);
            }

            $estado = EstadoImportacion::from((string) $importacion->estado);
            if (! $estado->puedeEncolarse()) {
                throw ImportacionEnCursoNoEditable::estado($estado);
            }

            $importacion->modo = $modo->value;
            $importacion->estado = EstadoImportacion::PROCESANDO->value;
            $importacion->iniciado_en = CarbonImmutable::now();
            $importacion->save();
        });

        $importacion = ImportacionModel::query()->sinScopeProyecto()->findOrFail($importacionId);

        $this->bus->dispatch(new EjecutarImportacionJob($importacionId, $modo->value));

        $this->events->dispatch(new ImportacionEncolada(
            importacionId: $importacionId,
            proyectoId: (int) $importacion->proyecto_id,
        ));
    }
}
