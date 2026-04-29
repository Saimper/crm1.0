<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Http\Livewire;

use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosCobranza;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosLeadVenta;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosServicio;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosTicketCx;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Importa casos por CSV. Detecta tipo_operacion del proyecto activo y delega al UseCase
 * correcto (cobranza/cx/venta/servicio). Un proyecto solo puede importar casos de su tipo.
 */
final class ImportarCasos extends Component
{
    use WithFileUploads;

    public $archivo = null;

    public ?int $importacionId = null;

    private const COLUMNAS_POR_TIPO = [
        'cobranza' => [
            'obligatorias' => ['cartera_codigo', 'tipo_identificacion_codigo', 'identificacion', 'numero_prestamo', 'moneda', 'monto_original', 'saldo_capital', 'saldo_total', 'fecha_desembolso', 'fecha_vencimiento', 'estado_caso_codigo', 'fecha_ingreso'],
            'tipo_entidad' => 'caso_cobranza',
        ],
        'cx' => [
            'obligatorias' => ['cartera_codigo', 'tipo_identificacion_codigo', 'identificacion', 'codigo_ticket', 'asunto', 'fecha_reporte', 'estado_caso_codigo', 'fecha_ingreso'],
            'tipo_entidad' => 'caso_ticket_cx',
        ],
        'venta' => [
            'obligatorias' => ['cartera_codigo', 'tipo_identificacion_codigo', 'identificacion', 'codigo_lead', 'valor_estimado_monto', 'moneda', 'fecha_primer_contacto', 'estado_caso_codigo', 'fecha_ingreso'],
            'tipo_entidad' => 'caso_lead_venta',
        ],
        'servicio' => [
            'obligatorias' => ['cartera_codigo', 'tipo_identificacion_codigo', 'identificacion', 'codigo_servicio', 'fecha_solicitud', 'estado_caso_codigo', 'fecha_ingreso'],
            'tipo_entidad' => 'caso_servicio',
        ],
    ];

    public function guardarArchivo(): void
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.crear') === true, 403);

        $this->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ], [], ['archivo' => 'archivo CSV']);

        $tipoOperacion = (string) app('tenancy.proyecto_activo')->tipo_operacion;
        if (! isset(self::COLUMNAS_POR_TIPO[$tipoOperacion])) {
            $this->addError('archivo', 'Tipo de operación no soportado.');
            return;
        }

        /** @var UploadedFile $file */
        $file = $this->archivo;
        $contenido = (string) file_get_contents($file->getRealPath());
        $filas = $this->parsearCsv($contenido, self::COLUMNAS_POR_TIPO[$tipoOperacion]['obligatorias']);

        if ($filas === []) {
            $this->addError('archivo', 'El CSV está vacío o faltan columnas obligatorias para '.$tipoOperacion.'.');
            return;
        }

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $importacion = new ImportacionModel();
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyectoId;
        $importacion->tipo_entidad = self::COLUMNAS_POR_TIPO[$tipoOperacion]['tipo_entidad'];
        $importacion->estado = 'borrador';
        $importacion->usuario_id = (int) auth()->id();
        $importacion->nombre_archivo = $file->getClientOriginalName();
        $importacion->total_filas = count($filas);
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
        $this->ejecutarProcesamiento($tipoOperacion, commit: false);
    }

    public function confirmar(): void
    {
        abort_unless(auth()->user()?->tienePermiso('importaciones.procesar') === true, 403);

        if ($this->importacionId === null) {
            return;
        }

        $tipoOperacion = (string) app('tenancy.proyecto_activo')->tipo_operacion;
        $this->ejecutarProcesamiento($tipoOperacion, commit: true);
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
        $tipoOperacion = (string) app('tenancy.proyecto_activo')->tipo_operacion;

        $tiposEntidadCasos = array_column(self::COLUMNAS_POR_TIPO, 'tipo_entidad');

        $historial = DB::table('importaciones as i')
            ->leftJoin('users as u', 'u.id', '=', 'i.usuario_id')
            ->where('i.proyecto_id', $proyectoId)
            ->whereIn('i.tipo_entidad', $tiposEntidadCasos)
            ->select([
                'i.id', 'i.public_id', 'i.estado', 'i.nombre_archivo', 'i.tipo_entidad',
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

        return view('importaciones::livewire.importar-casos', [
            'historial'         => $historial,
            'preview'           => $preview,
            'importacionActual' => $importacionActual,
            'tipoOperacion'     => $tipoOperacion,
            'columnasEsperadas' => self::COLUMNAS_POR_TIPO[$tipoOperacion]['obligatorias'] ?? [],
        ]);
    }

    private function ejecutarProcesamiento(string $tipoOperacion, bool $commit): void
    {
        if ($this->importacionId === null) {
            return;
        }

        match ($tipoOperacion) {
            'cobranza' => app(ProcesarImportacionCasosCobranza::class)->ejecutar($this->importacionId, $commit),
            'cx'       => app(ProcesarImportacionCasosTicketCx::class)->ejecutar($this->importacionId, $commit),
            'venta'    => app(ProcesarImportacionCasosLeadVenta::class)->ejecutar($this->importacionId, $commit),
            'servicio' => app(ProcesarImportacionCasosServicio::class)->ejecutar($this->importacionId, $commit),
            default    => null,
        };
    }

    /**
     * @param list<string> $columnasObligatorias
     * @return list<array<string, string>>
     */
    private function parsearCsv(string $contenido, array $columnasObligatorias): array
    {
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido) ?? $contenido;
        $lineas = preg_split('/\r\n|\r|\n/', trim($contenido));
        if ($lineas === false || count($lineas) < 2) {
            return [];
        }

        $cabeceras = str_getcsv($lineas[0]);
        $cabeceras = array_map(fn (string $c): string => strtolower(trim($c)), $cabeceras);

        foreach ($columnasObligatorias as $col) {
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
