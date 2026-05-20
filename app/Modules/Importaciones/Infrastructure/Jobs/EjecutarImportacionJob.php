<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Jobs;

use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionDinamica;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionInput;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Events\ImportacionFallada;
use App\Modules\Importaciones\Domain\Events\ImportacionIniciada;
use App\Modules\Importaciones\Domain\Events\ImportacionTerminada;
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
 * Job que ejecuta una importación dinámica en chunks.
 *
 * - tries=3, backoff escalonado.
 * - uniqueId=importacion_id evita re-encolado del mismo batch.
 * - Lock advisory MySQL `GET_LOCK("import:{id}")`: si otro worker tiene la importación, sale silencioso.
 * - Delega todo el procesamiento a EjecutarImportacionDinamica (esquema dinámico).
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
        public readonly string $modo = 'upsert',
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

    public function handle(EjecutarImportacionDinamica $ejecutarDinamica): void
    {
        $lockKey = 'import:'.$this->importacionId;
        $obtenido = (int) DB::selectOne('SELECT GET_LOCK(?, 0) AS got', [$lockKey])->got;
        if ($obtenido !== 1) {
            return;
        }

        try {
            $this->procesar($ejecutarDinamica);
        } finally {
            DB::statement('SELECT RELEASE_LOCK(?)', [$lockKey]);
        }
    }

    private function procesar(EjecutarImportacionDinamica $ejecutarDinamica): void
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

        $batchSize = (int) config('imports.batch_size', 1000);

        try {
            $resultado = $ejecutarDinamica->execute(new EjecutarImportacionInput(
                importacionId: $this->importacionId,
                chunkSize: $batchSize,
            ));

            $importacion->refresh();

            Event::dispatch(new ImportacionIniciada(
                importacionId: (int) $importacion->id,
                proyectoId: (int) $importacion->proyecto_id,
            ));

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
}
