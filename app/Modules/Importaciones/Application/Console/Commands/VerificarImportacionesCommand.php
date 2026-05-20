<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Console\Commands;

use App\Modules\Importaciones\Domain\Contracts\CampoPersonalizadoImportacionRepository;
use App\Modules\Importaciones\Domain\Contracts\ImportacionRepository;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\Importar;
use App\Modules\Importaciones\Infrastructure\Jobs\EjecutarImportacionJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class VerificarImportacionesCommand extends Command
{
    protected $signature = 'importaciones:verificar';

    protected $description = 'Verifica bindings, migraciones, enums y componentes del módulo de importaciones dinámicas.';

    /** @var list<array{nombre: string, ok: bool, detalle: string}> */
    private array $resultados = [];

    public function handle(): int
    {
        $this->info('Verificando módulo de importaciones dinámicas...');
        $this->newLine();

        $this->verificarBindings();
        $this->verificarColumnasMigracion();
        $this->verificarEnumModo();
        $this->verificarTablaAuditoria();
        $this->verificarJobSerializacion();
        $this->verificarLivewireRegistrado();
        $this->verificarConfigImports();

        $this->imprimirTabla();

        $fallos = array_filter($this->resultados, static fn ($r) => ! $r['ok']);

        if ($fallos !== []) {
            $this->error(count($fallos).' verificación(es) fallaron.');

            return self::FAILURE;
        }

        $this->info('Todas las verificaciones pasaron correctamente.');

        return self::SUCCESS;
    }

    private function verificarBindings(): void
    {
        $this->verificar(
            'Binding: ImportacionRepository',
            static fn (): bool => app(ImportacionRepository::class) !== null,
        );

        $this->verificar(
            'Binding: CampoPersonalizadoImportacionRepository',
            static fn (): bool => app(CampoPersonalizadoImportacionRepository::class) !== null,
        );
    }

    private function verificarColumnasMigracion(): void
    {
        $this->verificar(
            'Columna importaciones.esquema (JSON)',
            static fn (): bool => Schema::hasColumn('importaciones', 'esquema'),
        );

        $this->verificar(
            'Columna importaciones.insertadas (int)',
            static fn (): bool => Schema::hasColumn('importaciones', 'insertadas'),
        );

        $this->verificar(
            'Columna importaciones.actualizadas (int)',
            static fn (): bool => Schema::hasColumn('importaciones', 'actualizadas'),
        );
    }

    private function verificarEnumModo(): void
    {
        $this->verificar(
            'Enum modo incluye insert/update/upsert',
            function (): bool {
                try {
                    $row = DB::selectOne(
                        "SELECT COLUMN_TYPE as tipo FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'importaciones' AND COLUMN_NAME = 'modo'"
                    );

                    if ($row === null) {
                        return false;
                    }

                    $tipo = (string) $row->tipo;

                    return str_contains($tipo, 'insert')
                        && str_contains($tipo, 'update')
                        && str_contains($tipo, 'upsert');
                } catch (Throwable) {
                    return false;
                }
            },
        );
    }

    private function verificarTablaAuditoria(): void
    {
        $this->verificar(
            'Tabla importacion_campos_personalizados existe',
            static fn (): bool => Schema::hasTable('importacion_campos_personalizados'),
        );
    }

    private function verificarJobSerializacion(): void
    {
        $this->verificar(
            'EjecutarImportacionJob puede instanciarse y serializarse',
            static function (): bool {
                try {
                    $job = new EjecutarImportacionJob(1, 'upsert');
                    $serialized = serialize($job);
                    $unserialized = unserialize($serialized);

                    return $unserialized instanceof EjecutarImportacionJob
                        && $unserialized->importacionId === 1;
                } catch (Throwable) {
                    return false;
                }
            },
        );
    }

    private function verificarLivewireRegistrado(): void
    {
        $this->verificar(
            'Clase Livewire Importar existe y es válida',
            static function (): bool {
                return class_exists(Importar::class)
                    && is_subclass_of(Importar::class, \Livewire\Component::class);
            },
        );
    }

    private function verificarConfigImports(): void
    {
        $this->verificar(
            'Config imports existe (queue, batch_size, job_timeout)',
            static fn (): bool => config('imports.queue') !== null
                && config('imports.batch_size') !== null
                && config('imports.job_timeout') !== null,
        );
    }

    private function verificar(string $nombre, callable $check): void
    {
        try {
            $ok = $check();
        } catch (Throwable $e) {
            $ok = false;
        }

        $this->resultados[] = [
            'nombre' => $nombre,
            'ok' => (bool) $ok,
            'detalle' => $ok ? 'OK' : 'FALLO',
        ];
    }

    private function imprimirTabla(): void
    {
        $headers = ['Verificación', 'Estado'];
        $rows = array_map(
            static fn ($r) => [$r['nombre'], $r['detalle']],
            $this->resultados,
        );

        $this->table($headers, $rows);
    }
}
