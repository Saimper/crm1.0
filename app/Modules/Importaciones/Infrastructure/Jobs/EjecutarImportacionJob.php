<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Jobs;

use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosCobranza;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosLeadVenta;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosServicio;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosTicketCx;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionPersonas;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Events\ImportacionFallada;
use App\Modules\Importaciones\Domain\Events\ImportacionIniciada;
use App\Modules\Importaciones\Domain\Events\ImportacionTerminada;
use App\Modules\Importaciones\Domain\ValueObjects\ResumenChunk;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * Job que ejecuta una importación en chunks.
 * - tries=3, backoff escalonado.
 * - uniqueId=importacion_id evita re-encolado del mismo batch.
 * - Lock advisory MySQL `GET_LOCK("import:{id}")`: si otro worker tiene la importación, sale silencioso.
 * - Detecta cancelación tras cada chunk: si estado=cancelada, abandona.
 */
final class EjecutarImportacionJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int,int> */
    public array $backoff = [60, 180, 600];

    public function __construct(
        public readonly int $importacionId,
        public readonly string $modo,
    ) {
        $this->onQueue((string) config('imports.queue', 'imports'));
    }

    public function uniqueId(): string
    {
        return (string) $this->importacionId;
    }

    public function uniqueFor(): int
    {
        return (int) config('imports.job_timeout', 3600);
    }

    public function timeout(): int
    {
        return (int) config('imports.job_timeout', 3600);
    }

    public function handle(): void
    {
        $lockKey = 'import:'.$this->importacionId;
        $obtenido = (int) DB::selectOne('SELECT GET_LOCK(?, 0) AS got', [$lockKey])->got;
        if ($obtenido !== 1) {
            return;
        }

        try {
            $this->procesar();
        } finally {
            DB::statement('SELECT RELEASE_LOCK(?)', [$lockKey]);
        }
    }

    private function procesar(): void
    {
        /** @var ImportacionModel|null $importacion */
        $importacion = ImportacionModel::query()->sinScopeProyecto()->find($this->importacionId);
        if ($importacion === null) {
            return;
        }

        $estado = EstadoImportacion::from((string) $importacion->estado);
        if ($estado->esTerminal()) {
            return;
        }

        if ($estado === EstadoImportacion::PREPARADA) {
            $importacion->estado = EstadoImportacion::PROCESANDO->value;
            $importacion->iniciado_en = CarbonImmutable::now();
            $importacion->save();
            Event::dispatch(new ImportacionIniciada(
                importacionId: (int) $importacion->id,
                proyectoId: (int) $importacion->proyecto_id,
            ));
        }

        $modo = ModoImportacion::from($this->modo);
        $batchSize = (int) config('imports.batch_size', 1000);
        $tipo = (string) $importacion->tipo_entidad;

        try {
            $totalFilas = (int) ImportacionFilaModel::query()
                ->sinScopeProyecto()
                ->where('importacion_id', $this->importacionId)
                ->count();

            $procesadasIds = (int) ImportacionFilaModel::query()
                ->sinScopeProyecto()
                ->where('importacion_id', $this->importacionId)
                ->where('estado', '!=', 'pendiente')
                ->count();

            $offset = $procesadasIds;

            while ($offset < $totalFilas) {
                $fresca = ImportacionModel::query()->sinScopeProyecto()->find($this->importacionId);
                if ($fresca === null || (string) $fresca->estado === EstadoImportacion::CANCELADA->value) {
                    return;
                }

                $resumen = $this->ejecutarChunk($tipo, $modo, $offset, $batchSize);

                ImportacionModel::query()->sinScopeProyecto()
                    ->where('id', $this->importacionId)
                    ->update([
                        'procesadas' => DB::raw('procesadas + '.$resumen->procesadas),
                        'validas' => DB::raw('validas + '.$resumen->validas),
                        'invalidas' => DB::raw('invalidas + '.$resumen->invalidas),
                        'omitidas' => DB::raw('omitidas + '.$resumen->omitidas),
                        'duplicadas' => DB::raw('duplicadas + '.$resumen->duplicadas),
                    ]);

                if ($resumen->filasEnChunk === 0) {
                    break;
                }

                $offset += $resumen->filasEnChunk;
            }

            $importacion->refresh();
            $importacion->estado = EstadoImportacion::COMPLETADA->value;
            $importacion->terminado_en = CarbonImmutable::now();
            $importacion->save();

            Event::dispatch(new ImportacionTerminada(
                importacionId: (int) $importacion->id,
                proyectoId: (int) $importacion->proyecto_id,
                procesadas: (int) $importacion->procesadas,
                validas: (int) $importacion->validas,
                invalidas: (int) $importacion->invalidas,
                omitidas: (int) $importacion->omitidas,
                duplicadas: (int) $importacion->duplicadas,
            ));
        } catch (Throwable $e) {
            ImportacionModel::query()->sinScopeProyecto()
                ->where('id', $this->importacionId)
                ->update([
                    'estado' => EstadoImportacion::FALLIDA->value,
                    'error_global' => mb_substr($e->getMessage(), 0, 4000),
                    'terminado_en' => CarbonImmutable::now(),
                ]);

            Event::dispatch(new ImportacionFallada(
                importacionId: $this->importacionId,
                proyectoId: (int) $importacion->proyecto_id,
                error: $e->getMessage(),
            ));

            throw $e;
        }
    }

    private function ejecutarChunk(string $tipo, ModoImportacion $modo, int $offset, int $limit): ResumenChunk
    {
        return DB::transaction(fn (): ResumenChunk => match ($tipo) {
            'persona' => app(ProcesarImportacionPersonas::class)->ejecutar($this->importacionId, true, $modo, $offset, $limit),
            'caso_cobranza' => app(ProcesarImportacionCasosCobranza::class)->ejecutar($this->importacionId, true, $modo, $offset, $limit),
            'caso_ticket_cx' => app(ProcesarImportacionCasosTicketCx::class)->ejecutar($this->importacionId, true, $modo, $offset, $limit),
            'caso_lead_venta' => app(ProcesarImportacionCasosLeadVenta::class)->ejecutar($this->importacionId, true, $modo, $offset, $limit),
            'caso_servicio' => app(ProcesarImportacionCasosServicio::class)->ejecutar($this->importacionId, true, $modo, $offset, $limit),
            default => ResumenChunk::vacio(),
        });
    }
}
