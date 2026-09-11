<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Domain\Contracts\ImportacionRepository;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\Events\ImportacionEncolada;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionEnCursoNoEditable;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionNoEncontrada;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionNoProcesable;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Importaciones\Infrastructure\Jobs\EjecutarImportacionJob;
use App\Modules\Usuarios\Domain\Contracts\AccesoACuenta;
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
        private AccesoACuenta $acceso,
    ) {}

    public function execute(int $importacionId, ModoImportacion $modo, ?int $usuarioId = null, ?string $formatoEntrada = null): void
    {
        $proyectoId = $this->db->transaction(function () use ($importacionId, $modo, $usuarioId, $formatoEntrada): int {
            $row = $this->repositorio->buscarPorIdConLock($importacionId);

            if ($row === null) {
                throw ImportacionNoEncontrada::conId($importacionId);
            }

            $estado = EstadoImportacion::from($row['estado']);
            if (! $estado->puedeEncolarse()) {
                throw ImportacionEnCursoNoEditable::estado($estado);
            }

            $importacion = $this->db->table('importaciones')->where('proyecto_id', $row['proyecto_id'])->where('id', $importacionId)->first(['esquema', 'tipo_entidad', 'usuario_id']);
            $json = $importacion?->esquema;
            $actorId = $usuarioId ?? (int) ($importacion->usuario_id ?? 0);
            if (! $this->acceso->puedeImportar($actorId, $row['proyecto_id'])) {
                throw new ImportacionNoProcesable('No tienes permiso para procesar esta importación en el proyecto.');
            }
            if (is_string($json)) {
                $esquema = EsquemaImportacion::deserializar($json);
                $tipoOperacion = (string) $this->db->table('proyectos')->where('id', $row['proyecto_id'])->value('tipo_operacion');
                if ($formatoEntrada !== null && ! in_array($formatoEntrada, ['proyecto', 'estandar'], true)) {
                    throw new ImportacionNoProcesable('Selecciona un formato de entrada válido.');
                }
                // Returning an archived debt is part of importing accounts. The
                // persisted flag is processing authorization, not a user option.
                $reincorporarArchivadas = $esquema->target !== TargetImportacion::PERSONA;
                if ($reincorporarArchivadas) {
                    if ($actorId <= 0 || $esquema->carteraId === null) {
                        throw new ImportacionNoProcesable('La importación requiere un usuario y una cartera de destino válidos.');
                    }
                    if (! $this->acceso->puedeImportar($actorId, $row['proyecto_id'], $esquema->carteraId)) {
                        throw new ImportacionNoProcesable('No tienes permiso para importar en la cartera de destino o ya no está activa.');
                    }
                    // Each transfer checks source/destination access while the
                    // row is locked, so one inaccessible match cannot block the file.
                }
                $esquema = new EsquemaImportacion($esquema->target, $esquema->proyectoId, $esquema->carteraId, $modo,
                    $esquema->columnas, $reincorporarArchivadas, $reincorporarArchivadas ? $actorId : null, $formatoEntrada ?? $esquema->formatoEntrada);
                $esquema->validarContexto($row['proyecto_id'], (string) $importacion->tipo_entidad, $tipoOperacion);
                $this->db->table('importaciones')->where('proyecto_id', $row['proyecto_id'])->where('id', $importacionId)->update(['esquema' => $esquema->serializar()]);
            }

            $this->repositorio->marcarComoEncolada(
                id: $importacionId,
                modo: $modo,
                nuevoEstado: EstadoImportacion::PROCESANDO,
                iniciadoEn: CarbonImmutable::now()->toDateTimeImmutable(),
            );

            return $row['proyecto_id'];
        });

        $this->bus->dispatch(new EjecutarImportacionJob($importacionId));

        $this->events->dispatch(new ImportacionEncolada(
            importacionId: $importacionId,
            proyectoId: $proyectoId,
        ));
    }
}
