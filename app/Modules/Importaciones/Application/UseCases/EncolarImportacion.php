<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Domain\Contracts\ImportacionRepository;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Events\ImportacionEncolada;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionEnCursoNoEditable;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionNoEncontrada;
use App\Modules\Importaciones\Infrastructure\Jobs\EjecutarImportacionJob;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Database\ConnectionInterface;

/**
 * Despacha el job que ejecuta la importación.
 * Solo importaciones en estado PREPARADA pueden encolarse.
 * Marca PROCESANDO + iniciado_en de inmediato (evita doble despacho aunque cola tarde).
 *
 * F34D — UseCase desacoplado de ImportacionModel via ImportacionRepository.
 * La capa Application no importa modelos Eloquent (§3 CLAUDE.md). Los Livewire
 * (Infrastructure) siguen usando ImportacionModel directo (Infrastructure a
 * Infrastructure es admisible).
 */
final readonly class EncolarImportacion
{
    public function __construct(
        private ImportacionRepository $repositorio,
        private ConnectionInterface $db,
        private BusDispatcher $bus,
        private EventDispatcher $events,
    ) {}

    public function execute(int $importacionId, ModoImportacion $modo): void
    {
        $proyectoId = $this->db->transaction(function () use ($importacionId, $modo): int {
            $row = $this->repositorio->buscarPorIdConLock($importacionId);

            if ($row === null) {
                throw ImportacionNoEncontrada::conId($importacionId);
            }

            $estado = EstadoImportacion::from($row['estado']);
            if (! $estado->puedeEncolarse()) {
                throw ImportacionEnCursoNoEditable::estado($estado);
            }

            $this->repositorio->marcarComoEncolada(
                id: $importacionId,
                modo: $modo,
                nuevoEstado: EstadoImportacion::PROCESANDO,
                iniciadoEn: CarbonImmutable::now()->toDateTimeImmutable(),
            );

            return $row['proyecto_id'];
        });

        $this->bus->dispatch(new EjecutarImportacionJob($importacionId, $modo->value));

        $this->events->dispatch(new ImportacionEncolada(
            importacionId: $importacionId,
            proyectoId: $proyectoId,
        ));
    }
}
