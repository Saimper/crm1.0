<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Http\Livewire;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Application\Services\LectorCsv;
use App\Modules\Importaciones\Application\Services\LectorXlsx;
use App\Modules\Importaciones\Application\UseCases\CancelarImportacion;
use App\Modules\Importaciones\Application\UseCases\ConsultarProgresoImportacion;
use App\Modules\Importaciones\Application\UseCases\EncolarImportacion;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionInput;
use App\Modules\Importaciones\Application\UseCases\InferirEsquemaDesdeHeaders;
use App\Modules\Importaciones\Application\UseCases\InferirEsquemaInput;
use App\Modules\Importaciones\Application\UseCases\InferirEsquemaOutput;
use App\Modules\Importaciones\Application\UseCases\PrepararImportacionDinamica;
use App\Modules\Importaciones\Application\UseCases\PrepararImportacionInput;
use App\Modules\Importaciones\Domain\Catalogo\CatalogoCamposSistema;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionSinPermisoCamposException;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ResultadoDryRun;
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
 * Wizard unificado de importación dinámica.
 *
 * Flujo:
 *   1. subir   — selector target + cartera + upload → infiere esquema.
 *   2. mapeo   — usuario ajusta acción por columna (sistema/CP/ignorar)
 *                y elige identificador de persona.
 *   3. confirmar — valida esquema, crea CPs, prepara importación.
 *   4. procesar — despacha job, polling progreso 2s.
 */
final class Importar extends Component
{
    use WithFileUploads;

    public int $paso = 1;

    public ?string $targetValor = null;

    public ?int $carteraId = null;

    public string $modo = 'upsert';

    public $archivo = null;

    public bool $archivoListo = false;

    /** @var list<array{nombre_original: string, tipo_inferido: string, campo_sistema_mapeado: ?string, es_identificador_persona: bool, accion: string}> */
    public array $columnas = [];

    public ?string $columnaIdentificadorNombre = null;

    public ?int $importacionId = null;

    public ?array $resultadoDryRun = null;

    /** @var list<string> */
    public array $advertencias = [];

    public string $filtroFilas = 'todas';

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
        $this->reset(['archivo', 'columnas', 'columnaIdentificadorNombre', 'carteraId']);
        $this->archivoListo = false;
        $this->paso = 1;
    }

    public function updatedArchivo(): void
    {
        $this->archivoListo = ($this->archivo instanceof UploadedFile);
    }

    public function subirArchivo(): void
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.crear') === true, 403);

        if (! $this->archivoListo || ! ($this->archivo instanceof UploadedFile)) {
            $this->addError('archivo', 'El archivo aún no terminó de cargarse. Espera un momento e intenta de nuevo.');

            return;
        }

        $target = $this->target();
        if ($target === null) {
            $this->addError('targetValor', 'Selecciona qué deseas importar.');

            return;
        }

        if ($target !== TargetImportacion::PERSONA && $this->carteraId === null) {
            $this->addError('carteraId', 'Selecciona una cartera.');

            return;
        }

        $this->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt,xlsx,xlsm', 'max:16384'],
        ], [
            'archivo.required' => 'Selecciona un archivo para continuar.',
        ], ['archivo' => 'archivo']);

        /** @var UploadedFile $file */
        $file = $this->archivo;

        try {
            [$headers, $muestra] = $this->leerArchivo($file);
        } catch (\Throwable $e) {
            $this->addError('archivo', 'No se pudo leer el archivo: '.$e->getMessage());

            return;
        }

        if ($headers === []) {
            $this->addError('archivo', 'El archivo no contiene cabecera reconocible.');

            return;
        }

        if (empty($muestra)) {
            $this->addError('archivo', 'El archivo no contiene filas de datos.');

            return;
        }

        $proyectoId = $this->proyectoId();

        $output = app(InferirEsquemaDesdeHeaders::class)->execute(new InferirEsquemaInput(
            headers: $headers,
            filasMuestra: $muestra,
            target: $target,
            proyectoId: $proyectoId,
            carteraId: $this->carteraId,
        ));

        $this->columnas = $this->serializarColumnas($output->columnas);
        $this->columnaIdentificadorNombre = $output->sugerenciaIdentificador;
        $this->advertencias = $output->advertencias;
        $this->paso = 2;
    }

    public function actualizarAccionColumna(string $nombreOriginal, string $accion): void
    {
        $accionEnum = AccionColumna::tryFrom($accion);
        if ($accionEnum === null) {
            return;
        }

        foreach ($this->columnas as $i => $col) {
            if ($col['nombre_original'] === $nombreOriginal) {
                $this->columnas[$i]['accion'] = $accion;

                if ($accionEnum === AccionColumna::MAPEAR_SISTEMA) {
                    $this->columnas[$i]['es_identificador_persona'] = false;
                }

                break;
            }
        }
    }

    public function marcarComoIdentificador(string $nombreOriginal): void
    {
        foreach ($this->columnas as $i => $col) {
            if ($col['nombre_original'] === $nombreOriginal) {
                $this->columnas[$i]['es_identificador_persona'] = true;
                $this->columnaIdentificadorNombre = $nombreOriginal;
            } else {
                $this->columnas[$i]['es_identificador_persona'] = false;
            }
        }
    }

    public function confirmarMapeo(): void
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.crear') === true, 403);

        $target = $this->target();
        if ($target === null) {
            return;
        }

        $columnas = $this->deserializarColumnas();

        if ($columnas === []) {
            $this->addError('columnas', 'No hay columnas configuradas.');

            return;
        }

        $tienePermisoCampos = auth()->user()?->tienePermiso('campos.definir') === true;
        $tieneColumnasCP = false;

        foreach ($columnas as $col) {
            if ($col->accion === AccionColumna::CREAR_CP) {
                $tieneColumnasCP = true;

                break;
            }
        }

        if ($tieneColumnasCP && ! $tienePermisoCampos) {
            $this->addError('columnas', 'No tienes permiso para crear campos personalizados. Solicita acceso a un administrador.');

            return;
        }

        $esquema = new EsquemaImportacion(
            target: $target,
            proyectoId: $this->proyectoId(),
            carteraId: $target === TargetImportacion::PERSONA ? null : $this->carteraId,
            modo: ModoImportacion::from($this->modo),
            columnas: $columnas,
        );

        try {
            $esquema->validar();
        } catch (\DomainException $e) {
            $this->addError('columnas', $e->getMessage());

            return;
        }

        $proyectoId = $this->proyectoId();
        /** @var UploadedFile|null $file */
        $file = $this->archivo;

        if ($file === null) {
            $this->addError('archivo', 'Sube el archivo nuevamente.');
            $this->paso = 1;

            return;
        }

        try {
            [$headers, , $totalFilas, $filas] = $this->leerArchivo($file, leerTodas: true);
        } catch (\Throwable $e) {
            $this->addError('archivo', 'No se pudo leer el archivo: '.$e->getMessage());

            return;
        }

        if ($filas === []) {
            $this->addError('archivo', 'El archivo no contiene filas.');

            return;
        }

        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyectoId;
        $importacion->tipo_entidad = $target->tipoEntidadDb();
        $importacion->modo = $this->modo;
        $importacion->estado = EstadoImportacion::PENDIENTE->value;
        $importacion->usuario_id = (int) auth()->id();
        $importacion->nombre_archivo = $file->getClientOriginalName();
        $importacion->total_filas = count($filas);
        $importacion->save();

        foreach ($filas as $i => $filaCruda) {
            $payload = $this->construirPayload($headers, $filaCruda, $columnas);

            ImportacionFilaModel::query()->create([
                'importacion_id' => $importacion->id,
                'proyecto_id' => $proyectoId,
                'numero_fila' => $i + 1,
                'estado' => 'pendiente',
                'payload' => $payload,
            ]);
        }

        $this->importacionId = (int) $importacion->id;

        try {
            $resultado = app(PrepararImportacionDinamica::class)->execute(new PrepararImportacionInput(
                importacionId: $this->importacionId,
                esquema: $esquema,
                usuarioId: (int) auth()->id(),
                tienePermisoCampos: $tienePermisoCampos,
            ));

            $this->resultadoDryRun = [
                'esValido' => $resultado->resultadoDryRun->esValido,
                'filasTotales' => count($filas),
                'filasValidas' => count($filas),
                'filasConAdvertencia' => 0,
                'filasInvalidas' => 0,
                'camposPersonalizadosACrear' => $resultado->resultadoDryRun->camposPersonalizadosACrear,
                'advertencias' => $resultado->resultadoDryRun->advertencias,
                'camposCreados' => $resultado->camposCreados,
                'camposReutilizados' => $resultado->camposReutilizados,
            ];
        } catch (ImportacionSinPermisoCamposException $e) {
            $this->addError('columnas', $e->getMessage());

            return;
        }

        $this->paso = 3;
    }

    public function ejecutar(): void
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.procesar') === true, 403);

        if ($this->importacionId === null) {
            return;
        }

        ImportacionModel::query()->sinScopeProyecto()
            ->where('id', $this->importacionId)
            ->update(['modo' => $this->modo]);

        EjecutarImportacionJob::dispatchSync($this->importacionId);
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
        $this->reset(['archivo', 'columnas', 'columnaIdentificadorNombre', 'importacionId', 'filtroFilas', 'resultadoDryRun', 'advertencias', 'carteraId']);
        $this->archivoListo = false;
        $this->paso = 1;
        $this->modo = 'upsert';
    }

    public function render(): View
    {
        $proyecto = app('tenancy.proyecto_activo');
        $proyectoId = (int) $proyecto->id;
        $tipoOperacion = (string) $proyecto->tipo_operacion;

        $disponibles = CatalogoCamposSistema::targetsDisponibles($tipoOperacion);
        $target = $this->target();

        $carteras = $target !== TargetImportacion::PERSONA
            ? DB::table('carteras')->where('proyecto_id', $proyectoId)->where('activo', true)->orderBy('nombre')->get()
            : collect();

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
                'i.total_filas', 'i.procesadas', 'i.insertadas', 'i.actualizadas',
                'i.validas', 'i.invalidas', 'i.omitidas', 'i.duplicadas',
                'i.creada_en', 'u.name as usuario_nombre',
            ])
            ->orderByDesc('i.creada_en')
            ->limit(30)
            ->get();

        $camposSistema = $target !== null ? CatalogoCamposSistema::paraTarget($target) : [];

        return view('importaciones::livewire.importar', [
            'targetsDisponibles' => $disponibles,
            'target' => $target,
            'carteras' => $carteras,
            'camposSistema' => $camposSistema,
            'progreso' => $progreso,
            'importacionActual' => $importacionActual,
            'preview' => $preview,
            'historial' => $historial,
            'tipoOperacion' => $tipoOperacion,
        ]);
    }

    /**
     * Lee headers + muestra del archivo. Si $leerTodas, devuelve también todas las filas.
     *
     * @return array{0: list<string>, 1: list<list<string>>, 2?: int, 3?: list<list<string>>}
     */
    private function leerArchivo(UploadedFile $file, bool $leerTodas = false): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (in_array($ext, ['xlsx', 'xlsm'], true)) {
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

    /**
     * @param list<string> $headers
     * @param list<string> $filaCruda
     * @param list<ColumnaExcel> $columnas
     * @return array<string, string>
     */
    private function construirPayload(array $headers, array $filaCruda, array $columnas): array
    {
        $indicePorHeader = array_flip($headers);
        $payload = [];

        foreach ($columnas as $columna) {
            if ($columna->accion === AccionColumna::IGNORAR) {
                continue;
            }

            $colIdx = $indicePorHeader[$columna->nombreOriginal] ?? null;
            if ($colIdx === null) {
                continue;
            }

            $valor = trim($filaCruda[$colIdx] ?? '');
            if ($valor === '') {
                continue;
            }

            if ($columna->accion === AccionColumna::MAPEAR_SISTEMA && $columna->campoSistemaMapeado !== null) {
                $payload[$columna->campoSistemaMapeado] = $valor;
            } else {
                $payload[$columna->codigoSugerido()] = $valor;
            }
        }

        return $payload;
    }

    /**
     * @param list<ColumnaExcel> $columnas
     * @return list<array{nombre_original: string, tipo_inferido: string, campo_sistema_mapeado: ?string, es_identificador_persona: bool, accion: string}>
     */
    private function serializarColumnas(array $columnas): array
    {
        $resultado = [];

        foreach ($columnas as $col) {
            $resultado[] = [
                'nombre_original' => $col->nombreOriginal,
                'tipo_inferido' => $col->tipoInferido->value,
                'campo_sistema_mapeado' => $col->campoSistemaMapeado,
                'es_identificador_persona' => $col->esIdentificadorPersona,
                'accion' => $col->accion->value,
            ];
        }

        return $resultado;
    }

    /**
     * @return list<ColumnaExcel>
     */
    private function deserializarColumnas(): array
    {
        $resultado = [];

        foreach ($this->columnas as $col) {
            $resultado[] = new ColumnaExcel(
                nombreOriginal: $col['nombre_original'],
                tipoInferido: TipoCampo::from($col['tipo_inferido']),
                campoSistemaMapeado: $col['campo_sistema_mapeado'] ?: null,
                esIdentificadorPersona: (bool) $col['es_identificador_persona'],
                accion: AccionColumna::from($col['accion']),
            );
        }

        return $resultado;
    }

    private function proyectoId(): int
    {
        return (int) app('tenancy.proyecto_activo')->id;
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
