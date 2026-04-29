<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Http\Livewire;

use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionPersonas;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Flujo de importación de personas por CSV:
 *   1. Subir archivo → se crean Importacion + ImportacionFila (estado pendiente) y se corre validación (dry-run).
 *   2. Ver preview con filas válidas / inválidas.
 *   3. Confirmar → commit: inserta personas vía UseCase RegistrarPersona.
 *   4. Ver resumen y volver a la lista.
 */
final class ImportarPersonas extends Component
{
    use WithFileUploads;

    public $archivo = null;

    public ?int $importacionId = null;

    public function guardarArchivo(): void
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.crear') === true, 403);

        $this->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [], ['archivo' => 'archivo CSV']);

        /** @var UploadedFile $file */
        $file = $this->archivo;
        $contenido = (string) file_get_contents($file->getRealPath());
        $filas = $this->parsearCsv($contenido);

        if ($filas === []) {
            $this->addError('archivo', 'El CSV está vacío o no tiene las columnas esperadas.');
            return;
        }

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $importacion = new ImportacionModel();
        $importacion->public_id      = (string) Str::ulid();
        $importacion->proyecto_id    = $proyectoId;
        $importacion->tipo_entidad   = 'persona';
        $importacion->estado         = 'borrador';
        $importacion->usuario_id     = (int) auth()->id();
        $importacion->nombre_archivo = $file->getClientOriginalName();
        $importacion->total_filas    = count($filas);
        $importacion->save();

        foreach ($filas as $i => $payload) {
            ImportacionFilaModel::query()->create([
                'importacion_id' => $importacion->id,
                'proyecto_id'    => $proyectoId,
                'numero_fila'    => $i + 1,
                'estado'         => 'pendiente',
                'payload'        => $payload,
            ]);
        }

        $this->importacionId = (int) $importacion->id;

        // Dry-run: valida todas las filas sin insertar.
        app(ProcesarImportacionPersonas::class)->ejecutar($this->importacionId, commit: false);
    }

    public function confirmar(ProcesarImportacionPersonas $useCase): void
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.procesar') === true, 403);

        if ($this->importacionId === null) {
            return;
        }

        $useCase->ejecutar($this->importacionId, commit: true);
        session()->flash('importacion-ok', 'Importación procesada.');
    }

    public function cancelar(): void
    {
        if ($this->importacionId !== null) {
            ImportacionModel::query()->sinScopeProyecto()
                ->where('id', $this->importacionId)
                ->update(['estado' => 'cancelada']);
        }

        $this->reset(['archivo', 'importacionId']);
    }

    public function render(): View
    {
        $proyectoId = (int) app('tenancy.proyecto_activo')->id;

        $historial = DB::table('importaciones as i')
            ->leftJoin('users as u', 'u.id', '=', 'i.usuario_id')
            ->where('i.proyecto_id', $proyectoId)
            ->select([
                'i.id', 'i.public_id', 'i.estado', 'i.nombre_archivo',
                'i.total_filas', 'i.filas_ok', 'i.filas_error', 'i.filas_importadas',
                'i.creada_en', 'u.name as usuario_nombre',
            ])
            ->orderByDesc('i.creada_en')
            ->limit(30)
            ->get();

        $preview = collect();
        $importacionActual = null;
        if ($this->importacionId !== null) {
            $importacionActual = DB::table('importaciones')->where('id', $this->importacionId)->first();
            $preview = DB::table('importacion_filas')
                ->where('importacion_id', $this->importacionId)
                ->orderBy('numero_fila')
                ->limit(200)
                ->get();
        }

        return view('importaciones::livewire.importar-personas', [
            'historial'         => $historial,
            'preview'           => $preview,
            'importacionActual' => $importacionActual,
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
