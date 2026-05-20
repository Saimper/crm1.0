<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Http\Livewire;

use App\Modules\Importaciones\Application\Services\LectorCsv;
use App\Modules\Importaciones\Application\Services\LectorXlsx;
use App\Modules\Importaciones\Application\Services\MapeadorPayload;
use App\Modules\Importaciones\Application\UseCases\CancelarImportacion;
use App\Modules\Importaciones\Application\UseCases\ConsultarProgresoImportacion;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosCobranza;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosLeadVenta;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosServicio;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosTicketCx;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionPersonas;
use App\Modules\Importaciones\Domain\Catalogo\CampoSistema;
use App\Modules\Importaciones\Domain\Catalogo\CatalogoCamposSistema;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Infrastructure\Jobs\EjecutarImportacionJob;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Wizard unificado de importación con mapeo libre de columnas CSV.
 *
 * Flujo:
 *   1. subir   — selector target + upload CSV → lee headers + 5 filas muestra.
 *   2. mapeo   — usuario asocia cada campo del sistema a una columna del CSV.
 *                Auto-mapeo por nombre normalizado pre-llena coincidencias.
 *   3. preview — confirmado el mapeo: crea Importacion + filas (payload canónico),
 *                dry-run validación, selector modo (merge/skip_duplicados/overwrite).
 *   4. procesando — encolar EjecutarImportacionJob, polling progreso 2s.
 *
 * El payload almacenado en importacion_filas.payload usa keys CANÓNICAS (mismas
 * que esperan los UseCases procesadores). Por eso los procesadores no cambian.
 * El mapeo {campo_sistema => columna_csv} se persiste en importaciones.mapeo.
 */
final class Importar extends Component
{
    use WithFileUploads;

    public int $paso = 1;

    public ?string $targetValor = null;

    public $archivo = null;

    /** @var list<string> */
    public array $cabecerasCsv = [];

    /** @var list<list<string>> */
    public array $filasMuestra = [];

    /** @var array<string, string>  campo_sistema_codigo => columna_csv */
    public array $mapeo = [];

    public ?int $importacionId = null;

    public string $modo = 'merge';

    public string $filtroFilas = 'todas';

    public bool $mostrarAvanzados = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.crear') === true, 403);

        $disponibles = $this->targetsDisponibles();
        if (count($disponibles) === 1) {
            $this->targetValor = $disponibles[0]->value;
        }
    }

    public function updatedTargetValor(): void
    {
        $this->reset(['archivo', 'cabecerasCsv', 'filasMuestra', 'mapeo']);
    }

    public function subirArchivo(): void
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.crear') === true, 403);

        if ($this->target() === null) {
            $this->addError('targetValor', 'Selecciona qué deseas importar.');

            return;
        }

        $this->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:16384'],
        ], [
            'archivo.required' => 'Selecciona un archivo para continuar.',
        ], ['archivo' => 'archivo']);

        /** @var UploadedFile $file */
        $file = $this->archivo;

        try {
            [$headers, $muestra, $totalFilas] = $this->leerArchivo($file);
        } catch (\Throwable $e) {
            $this->addError('archivo', 'No se pudo leer el archivo: '.$e->getMessage());

            return;
        }

        if ($headers === []) {
            $this->addError('archivo', 'El archivo no contiene cabecera reconocible.');

            return;
        }
        if ($totalFilas === 0) {
            $this->addError('archivo', 'El archivo no contiene filas de datos.');

            return;
        }

        $maxFilas = (int) config('imports.max_filas_por_archivo', 200000);
        if ($totalFilas > $maxFilas) {
            $this->addError('archivo', "El archivo supera el máximo permitido ({$maxFilas} filas).");

            return;
        }

        $this->cabecerasCsv = $headers;
        $this->filasMuestra = $muestra;
        $this->mapeo = (new MapeadorPayload)->autoMapear($this->codigosCampoSistema(), $headers);
        $this->paso = 2;
    }

    public function autoMapear(): void
    {
        if ($this->cabecerasCsv === []) {
            return;
        }
        $this->mapeo = (new MapeadorPayload)->autoMapear($this->codigosCampoSistema(), $this->cabecerasCsv);
    }

    public function volverASubir(): void
    {
        $this->paso = 1;
        $this->reset(['cabecerasCsv', 'filasMuestra', 'mapeo']);
    }

    public function confirmarMapeo(): void
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.crear') === true, 403);

        $target = $this->target();
        if ($target === null || $this->cabecerasCsv === []) {
            return;
        }

        $faltantes = $this->camposRequeridosSinMapear();
        if ($faltantes !== []) {
            foreach ($faltantes as $codigo => $etiqueta) {
                $this->addError("mapeo.{$codigo}", "Falta mapear: {$etiqueta}.");
            }

            return;
        }

        /** @var UploadedFile|null $file */
        $file = $this->archivo;
        if ($file === null) {
            $this->addError('archivo', 'Sube el archivo nuevamente.');
            $this->paso = 1;

            return;
        }

        try {
            [$headers, , , $filas] = $this->leerArchivo($file, leerTodas: true);
        } catch (\Throwable $e) {
            $this->addError('archivo', 'No se pudo leer el archivo: '.$e->getMessage());

            return;
        }
        $mapeador = new MapeadorPayload;

        if ($filas === []) {
            $this->addError('archivo', 'El archivo no contiene filas.');

            return;
        }

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $defaults = $this->defaultsParaTarget($target, $proyectoId);

        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyectoId;
        $importacion->tipo_entidad = $target->tipoEntidadDb();
        $importacion->mapeo = $this->mapeo;
        $importacion->modo = $this->modo;
        $importacion->estado = EstadoImportacion::PENDIENTE->value;
        $importacion->usuario_id = (int) auth()->id();
        $importacion->nombre_archivo = $file->getClientOriginalName();
        $importacion->total_filas = count($filas);
        $importacion->save();

        foreach ($filas as $i => $filaCruda) {
            $payload = $mapeador->aPayloadCanonico($headers, $filaCruda, $this->mapeo);
            $payload = $this->aplicarAutoFill($target, $payload, $defaults);

            ImportacionFilaModel::query()->create([
                'importacion_id' => $importacion->id,
                'proyecto_id' => $proyectoId,
                'numero_fila' => $i + 1,
                'estado' => 'pendiente',
                'payload' => $payload,
            ]);
        }

        $this->importacionId = (int) $importacion->id;
        $this->ejecutarDryRun($target);

        $invalidas = (int) ImportacionFilaModel::query()->sinScopeProyecto()
            ->where('importacion_id', $this->importacionId)->where('estado', 'invalida')->count();
        $validas = (int) ImportacionFilaModel::query()->sinScopeProyecto()
            ->where('importacion_id', $this->importacionId)->where('estado', 'pendiente')->count();

        ImportacionModel::query()->sinScopeProyecto()->where('id', $this->importacionId)->update([
            'estado' => EstadoImportacion::PREPARADA->value,
            'validas' => $validas,
            'invalidas' => $invalidas,
        ]);

        $this->paso = 3;
    }

    public function procesar(): void
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.procesar') === true, 403);

        if ($this->importacionId === null) {
            return;
        }

        ImportacionModel::query()->sinScopeProyecto()
            ->where('id', $this->importacionId)
            ->update(['modo' => $this->modo]);

        // F35-B fix: ejecución sync — evita depender de un worker `queue:work --queue=imports`
        // corriendo en producción. El Job tiene lock advisory propio que previene doble ejecución.
        // Para volúmenes grandes (cliente quiere async real), volver a `EncolarImportacion`.
        EjecutarImportacionJob::dispatchSync($this->importacionId, $this->modo);
        $this->paso = 4;
    }

    public function cancelar(CancelarImportacion $cancelarUC): void
    {
        if ($this->importacionId !== null) {
            $cancelarUC->execute($this->importacionId);
        }
    }

    public function cerrar(): void
    {
        $this->reset(['archivo', 'cabecerasCsv', 'filasMuestra', 'mapeo', 'importacionId', 'filtroFilas', 'mostrarAvanzados']);
        $this->paso = 1;
        $this->modo = 'merge';
    }

    /**
     * Lee headers + muestra + total. Si $leerTodas, devuelve también todas las filas.
     *
     * @return array{0: list<string>, 1: list<list<string>>, 2: int, 3?: list<list<string>>}
     */
    private function leerArchivo(UploadedFile $file, bool $leerTodas = false): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext === 'xlsx') {
            // Livewire upload temp path puede contener caracteres (`==`, `?`) que rompen
            // ZipArchive en Windows. Copiamos a un path limpio antes de leer con OpenSpout.
            $tmpPath = tempnam(sys_get_temp_dir(), 'imp_').'.xlsx';
            copy($file->getRealPath(), $tmpPath);

            try {
                $lector = new LectorXlsx;
                $headers = $lector->leerHeaders($tmpPath);
                $muestra = $lector->leerFilas($tmpPath, 5);
                $total = $lector->contarFilas($tmpPath);

                if ($leerTodas) {
                    return [$headers, $muestra, $total, $lector->leerFilas($tmpPath)];
                }

                return [$headers, $muestra, $total];
            } finally {
                @unlink($tmpPath);
            }
        }

        $contenido = (string) file_get_contents($file->getRealPath());
        $lector = new LectorCsv;
        $headers = $lector->leerHeaders($contenido);
        $muestra = $lector->leerFilas($contenido, 5);
        $total = $lector->contarFilas($contenido);

        if ($leerTodas) {
            return [$headers, $muestra, $total, $lector->leerFilas($contenido)];
        }

        return [$headers, $muestra, $total];
    }

    public function render(): View
    {
        $proyecto = app('tenancy.proyecto_activo');
        $proyectoId = (int) $proyecto->id;
        $tipoOperacion = (string) $proyecto->tipo_operacion;

        $disponibles = CatalogoCamposSistema::targetsDisponibles($tipoOperacion);
        $target = $this->target();
        $camposSistema = $target !== null ? CatalogoCamposSistema::paraTarget($target) : [];

        $progreso = null;
        $importacionActual = null;
        $preview = collect();

        if ($this->importacionId !== null) {
            $progreso = app(ConsultarProgresoImportacion::class)->execute($this->importacionId);
            $importacionActual = DB::table('importaciones')->where('id', $this->importacionId)->first();

            $q = DB::table('importacion_filas')->where('importacion_id', $this->importacionId);
            if ($this->filtroFilas !== 'todas') {
                $q->where('estado', $this->filtroFilas);
            }
            $preview = $q->orderBy('numero_fila')->limit(200)->get();
        }

        $historial = DB::table('importaciones as i')
            ->leftJoin('users as u', 'u.id', '=', 'i.usuario_id')
            ->where('i.proyecto_id', $proyectoId)
            ->select([
                'i.id', 'i.public_id', 'i.estado', 'i.modo', 'i.nombre_archivo', 'i.tipo_entidad',
                'i.total_filas', 'i.procesadas', 'i.validas', 'i.invalidas', 'i.omitidas', 'i.duplicadas',
                'i.creada_en', 'u.name as usuario_nombre',
            ])
            ->orderByDesc('i.creada_en')
            ->limit(30)
            ->get();

        return view('importaciones::livewire.importar', [
            'targetsDisponibles' => $disponibles,
            'target' => $target,
            'camposSistema' => $camposSistema,
            'progreso' => $progreso,
            'importacionActual' => $importacionActual,
            'preview' => $preview,
            'historial' => $historial,
            'tipoOperacion' => $tipoOperacion,
        ]);
    }

    /**
     * Defaults aplicados cuando el campo no fue mapeado al payload canónico.
     *
     * @return array<string, mixed>
     */
    private function defaultsParaTarget(TargetImportacion $target, int $proyectoId): array
    {
        if ($target === TargetImportacion::PERSONA) {
            return [];
        }

        $primerEstado = (string) DB::table('estados_caso')
            ->where('proyecto_id', $proyectoId)
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('id')
            ->value('codigo');

        return [
            'estado_caso_codigo' => $primerEstado !== '' ? $primerEstado : null,
            'fecha_ingreso' => Carbon::now()->toDateString(),
        ];
    }

    /**
     * @param  array<string, string>  $payload
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function aplicarAutoFill(TargetImportacion $target, array $payload, array $defaults): array
    {
        if ($target === TargetImportacion::PERSONA) {
            if (! isset($payload['tipo_persona']) || $payload['tipo_persona'] === '') {
                $tieneRazon = isset($payload['razon_social']) && $payload['razon_social'] !== '';
                $tieneNombres = isset($payload['nombres']) && $payload['nombres'] !== '';
                if ($tieneRazon && ! $tieneNombres) {
                    $payload['tipo_persona'] = 'juridica';
                } elseif ($tieneNombres) {
                    $payload['tipo_persona'] = 'fisica';
                }
            }

            return $payload;
        }

        foreach ($defaults as $key => $valor) {
            if ($valor === null) {
                continue;
            }
            if (! isset($payload[$key]) || $payload[$key] === '') {
                $payload[$key] = (string) $valor;
            }
        }

        return $payload;
    }

    private function ejecutarDryRun(TargetImportacion $target): void
    {
        if ($this->importacionId === null) {
            return;
        }
        $modo = ModoImportacion::from($this->modo);

        match ($target) {
            TargetImportacion::PERSONA => app(ProcesarImportacionPersonas::class)->ejecutar($this->importacionId, false, $modo),
            TargetImportacion::CASO_COBRANZA => app(ProcesarImportacionCasosCobranza::class)->ejecutar($this->importacionId, false, $modo),
            TargetImportacion::CASO_TICKET_CX => app(ProcesarImportacionCasosTicketCx::class)->ejecutar($this->importacionId, false, $modo),
            TargetImportacion::CASO_LEAD_VENTA => app(ProcesarImportacionCasosLeadVenta::class)->ejecutar($this->importacionId, false, $modo),
            TargetImportacion::CASO_SERVICIO => app(ProcesarImportacionCasosServicio::class)->ejecutar($this->importacionId, false, $modo),
        };
    }

    /**
     * @return array<string, string> codigo => etiqueta
     */
    private function camposRequeridosSinMapear(): array
    {
        $faltantes = [];
        foreach ($this->camposSistemaActivos() as $campo) {
            if (! $campo->requerido) {
                continue;
            }
            $col = $this->mapeo[$campo->codigo] ?? '';
            if ($col === '' || ! in_array($col, $this->cabecerasCsv, true)) {
                $faltantes[$campo->codigo] = $campo->etiqueta;
            }
        }

        return $faltantes;
    }

    /** @return list<CampoSistema> */
    private function camposSistemaActivos(): array
    {
        $target = $this->target();

        return $target !== null ? CatalogoCamposSistema::paraTarget($target) : [];
    }

    /** @return list<string> */
    private function codigosCampoSistema(): array
    {
        return array_map(static fn (CampoSistema $c): string => $c->codigo, $this->camposSistemaActivos());
    }

    /** @return list<TargetImportacion> */
    private function targetsDisponibles(): array
    {
        $tipoOperacion = (string) app('tenancy.proyecto_activo')->tipo_operacion;

        return CatalogoCamposSistema::targetsDisponibles($tipoOperacion);
    }

    private function target(): ?TargetImportacion
    {
        if ($this->targetValor === null || $this->targetValor === '') {
            return null;
        }

        return TargetImportacion::tryFrom($this->targetValor);
    }
}
