<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Http\Livewire;

use App\Modules\Importaciones\Application\UseCases\CancelarImportacion;
use App\Modules\Importaciones\Application\UseCases\ConsultarProgresoImportacion;
use App\Modules\Importaciones\Application\UseCases\EncolarImportacion;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionPersonas;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Flujo async F31:
 *   1. Subir archivo → Importacion + filas, dry-run validación, estado=preparada.
 *   2. UI muestra preview + selector modo (merge/skip_duplicados/overwrite).
 *   3. Confirmar → EncolarImportacion despacha Job → estado=procesando.
 *   4. Polling cada 2s consulta progreso. Termina al estado terminal.
 */
final class ImportarPersonas extends Component
{
    use WithFileUploads;

    public $archivo = null;

    public ?int $importacionId = null;

    public string $modo = 'merge';

    public string $filtroFilas = 'todas';

    public function guardarArchivo(): void
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.crear') === true, 403);

        $this->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:'.((int) config('imports.max_filas_por_archivo', 200000) >= 1 ? '8192' : '2048')],
        ], [], ['archivo' => 'archivo CSV']);

        /** @var UploadedFile $file */
        $file = $this->archivo;
        $contenido = (string) file_get_contents($file->getRealPath());
        $filas = $this->parsearCsv($contenido);

        if ($filas === []) {
            $this->addError('archivo', 'El CSV está vacío o no tiene las columnas esperadas.');

            return;
        }

        $maxFilas = (int) config('imports.max_filas_por_archivo', 200000);
        if (count($filas) > $maxFilas) {
            $this->addError('archivo', "El archivo supera el máximo permitido ({$maxFilas} filas).");

            return;
        }

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyectoId;
        $importacion->tipo_entidad = 'persona';
        $importacion->modo = $this->modo;
        $importacion->estado = EstadoImportacion::PENDIENTE->value;
        $importacion->usuario_id = (int) auth()->id();
        $importacion->nombre_archivo = $file->getClientOriginalName();
        $importacion->total_filas = count($filas);
        $importacion->save();

        foreach ($filas as $i => $payload) {
            ImportacionFilaModel::query()->create([
                'importacion_id' => $importacion->id,
                'proyecto_id' => $proyectoId,
                'numero_fila' => $i + 1,
                'estado' => 'pendiente',
                'payload' => $payload,
            ]);
        }

        $this->importacionId = (int) $importacion->id;

        app(ProcesarImportacionPersonas::class)->ejecutar(
            $this->importacionId,
            commit: false,
            modo: ModoImportacion::from($this->modo),
        );

        $invalidas = (int) ImportacionFilaModel::query()->sinScopeProyecto()
            ->where('importacion_id', $this->importacionId)->where('estado', 'invalida')->count();
        $validas = (int) ImportacionFilaModel::query()->sinScopeProyecto()
            ->where('importacion_id', $this->importacionId)->where('estado', 'pendiente')->count();

        ImportacionModel::query()->sinScopeProyecto()->where('id', $this->importacionId)->update([
            'estado' => EstadoImportacion::PREPARADA->value,
            'validas' => $validas,
            'invalidas' => $invalidas,
        ]);
    }

    public function confirmar(EncolarImportacion $encolar): void
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.procesar') === true, 403);

        if ($this->importacionId === null) {
            return;
        }

        $importacion = ImportacionModel::query()->sinScopeProyecto()->findOrFail($this->importacionId);
        $importacion->modo = $this->modo;
        $importacion->save();

        $encolar->execute($this->importacionId, ModoImportacion::from($this->modo));
    }

    public function cancelar(CancelarImportacion $cancelarUC): void
    {
        if ($this->importacionId !== null) {
            $cancelarUC->execute($this->importacionId);
        }
    }

    public function cerrar(): void
    {
        $this->reset(['archivo', 'importacionId', 'filtroFilas']);
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $historial = DB::table('importaciones as i')
            ->leftJoin('users as u', 'u.id', '=', 'i.usuario_id')
            ->where('i.proyecto_id', $proyectoId)
            ->where('i.tipo_entidad', 'persona')
            ->select([
                'i.id', 'i.public_id', 'i.estado', 'i.modo', 'i.nombre_archivo',
                'i.total_filas', 'i.procesadas', 'i.validas', 'i.invalidas', 'i.omitidas', 'i.duplicadas',
                'i.creada_en', 'u.name as usuario_nombre',
            ])
            ->orderByDesc('i.creada_en')
            ->limit(30)
            ->get();

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

        return view('importaciones::livewire.importar-personas', [
            'historial' => $historial,
            'preview' => $preview,
            'importacionActual' => $importacionActual,
            'progreso' => $progreso,
        ]);
    }

    /**
     * @return list<array<string, string>>
     */
    private function parsearCsv(string $contenido): array
    {
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido) ?? $contenido;
        $lineas = preg_split('/\r\n|\r|\n/', trim($contenido));
        if ($lineas === false || count($lineas) < 2) {
            return [];
        }

        $cabeceras = str_getcsv($lineas[0]);
        $cabeceras = array_map(fn (string $c): string => strtolower(trim($c)), $cabeceras);

        $columnasEsperadas = ['tipo_persona', 'tipo_identificacion_codigo', 'identificacion'];
        foreach ($columnasEsperadas as $col) {
            if (! in_array($col, $cabeceras, true)) {
                return [];
            }
        }

        $filas = [];
        for ($i = 1, $n = count($lineas); $i < $n; $i++) {
            $linea = $lineas[$i];
            if (trim($linea) === '') {
                continue;
            }
            $valores = str_getcsv($linea);
            $payload = [];
            foreach ($cabeceras as $idx => $col) {
                $payload[$col] = isset($valores[$idx]) ? (string) $valores[$idx] : '';
            }
            $filas[] = $payload;
        }

        return $filas;
    }
}
