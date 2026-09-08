<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Jobs;

use App\Modules\Importaciones\Application\Services\DescriptorDeFalloImportacion;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionDinamica;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionInput;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Events\ImportacionFallada;
use App\Modules\Importaciones\Domain\Events\ImportacionIniciada;
use App\Modules\Importaciones\Domain\Events\ImportacionTerminada;
use App\Modules\Importaciones\Domain\Exceptions\FalloDeImportacion;
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
 * - uniqueId=importacion_id evita re-encolado del mismo batch.
 * - Lock advisory MySQL `GET_LOCK("import:{id}")`: si otro worker tiene la importación, sale silencioso.
 * - Delega todo el procesamiento a EjecutarImportacionDinamica (esquema dinámico).
 *
 * Ya no recibe el modo: nadie lo leía. El modo vive en `importaciones.modo`,
 * que es de donde lo toma el motor.
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

    public function handle(EjecutarImportacionDinamica $ejecutarDinamica, DescriptorDeFalloImportacion $descriptor): void
    {
        $lockKey = 'import:'.$this->importacionId;
        $obtenido = (int) DB::selectOne('SELECT GET_LOCK(?, 0) AS got', [$lockKey])->got;
        if ($obtenido !== 1) {
            return;
        }

        try {
            $this->procesar($ejecutarDinamica, $descriptor);
        } finally {
            DB::statement('SELECT RELEASE_LOCK(?)', [$lockKey]);
        }
    }

    private function procesar(EjecutarImportacionDinamica $ejecutarDinamica, DescriptorDeFalloImportacion $descriptor): void
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

        $proyectoId = (int) $importacion->proyecto_id;
        $batchSize = (int) config('imports.batch_size', 1000);

        try {
            $ejecutarDinamica->execute(new EjecutarImportacionInput(
                importacionId: $this->importacionId,
                chunkSize: $batchSize,
            ));

            $importacion->refresh();

            Event::dispatch(new ImportacionIniciada(
                importacionId: (int) $importacion->id,
                proyectoId: $proyectoId,
            ));

            Event::dispatch(new ImportacionTerminada(
                importacionId: (int) $importacion->id,
                proyectoId: $proyectoId,
                procesadas: (int) $importacion->procesadas,
                validas: (int) $importacion->validas,
                invalidas: (int) $importacion->invalidas,
                omitidas: (int) $importacion->omitidas,
                duplicadas: (int) $importacion->duplicadas,
            ));
        } catch (Throwable $e) {
            // El motor ya describió y marcó lo suyo; lo que llega de fuera del
            // caso de uso (el refresh, un dispatch) se describe aquí.
            $fallo = $e instanceof FalloDeImportacion
                ? $e->descrito()
                : $descriptor->describir($e, ['importacion_id' => $this->importacionId, 'proyecto_id' => $proyectoId]);

            // Sólo donde `error_global` siga vacío: no se pisa lo que escribió
            // el motor, y ningún fallo queda mudo aunque ocurra fuera de él.
            ImportacionModel::query()->sinScopeProyecto()
                ->where('id', $this->importacionId)
                ->whereNull('error_global')
                ->update([
                    'estado' => EstadoImportacion::FALLIDA->value,
                    'error_global' => $fallo->motivo,
                    'terminado_en' => CarbonImmutable::now(),
                ]);

            Event::dispatch(new ImportacionFallada(
                importacionId: $this->importacionId,
                proyectoId: $proyectoId,
                motivo: $fallo->motivo,
                referencia: $fallo->referencia,
            ));

            // `fail()` y no `throw`: la importación ya es terminal, y reintentar
            // tres veces con backoff no arregla nada. Y siempre con la versión
            // descrita, sin `previous`: lo que se le pasa acaba en `failed_jobs`
            // y en el log del worker, y ahí tampoco tiene que ir el SQL.
            $this->fail($e instanceof FalloDeImportacion ? $e : FalloDeImportacion::desde($fallo));
        }
    }
}
