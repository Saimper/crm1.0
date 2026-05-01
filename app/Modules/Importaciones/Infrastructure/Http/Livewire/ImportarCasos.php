<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Http\Livewire;

use App\Modules\Importaciones\Application\UseCases\CancelarImportacion;
use App\Modules\Importaciones\Application\UseCases\ConsultarProgresoImportacion;
use App\Modules\Importaciones\Application\UseCases\EncolarImportacion;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosCobranza;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosLeadVenta;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosServicio;
use App\Modules\Importaciones\Application\UseCases\ProcesarImportacionCasosTicketCx;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Flujo async F31 para casos. Detecta tipo_operacion + delega al UseCase correcto. */
final class ImportarCasos extends Component
{
    use WithFileUploads;

    public $archivo = null;

    public ?int $importacionId = null;

    public string $modo = 'merge';

    public string $filtroFilas = 'todas';

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
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:8192'],
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

        $maxFilas = (int) config('imports.max_filas_por_archivo', 200000);
        if (count($filas) > $maxFilas) {
            $this->addError('archivo', "El archivo supera el máximo permitido ({$maxFilas} filas).");

            return;
        }

        $proyectoId = (int) app('tenancy.proyecto_activo')->id;
        $importacion = new ImportacionModel;
        $importacion->public_id = (string) Str::ulid();
        $importacion->proyecto_id = $proyectoId;
        $importacion->tipo_entidad = self::COLUMNAS_POR_TIPO[$tipoOperacion]['tipo_entidad'];
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
        $this->ejecutarDryRun($tipoOperacion);

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
        $tipoOperacion = (string) app('tenancy.proyecto_activo')->tipo_operacion;

        $tiposEntidadCasos = array_column(self::COLUMNAS_POR_TIPO, 'tipo_entidad');

        $historial = DB::table('importaciones as i')
            ->leftJoin('users as u', 'u.id', '=', 'i.usuario_id')
            ->where('i.proyecto_id', $proyectoId)
            ->whereIn('i.tipo_entidad', $tiposEntidadCasos)
            ->select([
                'i.id', 'i.public_id', 'i.estado', 'i.modo', 'i.nombre_archivo', 'i.tipo_entidad',
                'i.total_filas', 'i.procesadas', 'i.validas', 'i.invalidas', 'i.omitidas', 'i.duplicadas',
                'i.creada_en', 'u.name as usuario_nombre',
            ])
            ->orderByDesc('i.creada_en')
            ->limit(30)
            ->get();

        $progreso = null;
        $importacionActual = null;
        $preview = collect();
        $casosResueltos = [];

        if ($this->importacionId !== null) {
            $progreso = app(ConsultarProgresoImportacion::class)->execute($this->importacionId);
            $importacionActual = DB::table('importaciones')->where('id', $this->importacionId)->first();

            $q = DB::table('importacion_filas')->where('importacion_id', $this->importacionId);
            if ($this->filtroFilas !== 'todas') {
                $q->where('estado', $this->filtroFilas);
            }
            $preview = $q->orderBy('numero_fila')->limit(200)->get();

            if ($importacionActual !== null) {
                $casosResueltos = $this->resolverCasosDePreview(
                    $proyectoId,
                    (string) $importacionActual->tipo_entidad,
                    $preview,
                );
            }
        }

        return view('importaciones::livewire.importar-casos', [
            'historial' => $historial,
            'preview' => $preview,
            'importacionActual' => $importacionActual,
            'progreso' => $progreso,
            'tipoOperacion' => $tipoOperacion,
            'columnasEsperadas' => self::COLUMNAS_POR_TIPO[$tipoOperacion]['obligatorias'] ?? [],
            'casosResueltos' => $casosResueltos,
        ]);
    }

    /**
     * Mapea numero_fila → ['persona_public_id', 'caso_public_id'] para filas
     * procesadas/duplicadas, resolviendo via identificador único del CTI.
     *
     * @param  Collection<int, object>  $preview
     * @return array<int, array{persona_public_id: string, caso_public_id: string}>
     */
    private function resolverCasosDePreview(int $proyectoId, string $tipoEntidad, $preview): array
    {
        $columnaCsv = match ($tipoEntidad) {
            'caso_cobranza' => 'numero_prestamo',
            'caso_ticket_cx' => 'codigo_ticket',
            'caso_lead_venta' => 'codigo_lead',
            'caso_servicio' => 'codigo_servicio',
            default => null,
        };
        if ($columnaCsv === null) {
            return [];
        }

        $tablaCti = match ($tipoEntidad) {
            'caso_cobranza' => 'casos_cobranza',
            'caso_ticket_cx' => 'casos_ticket_cx',
            'caso_lead_venta' => 'casos_lead_venta',
            'caso_servicio' => 'casos_servicio',
        };
        $columnaCti = match ($tipoEntidad) {
            'caso_cobranza' => 'numero_prestamo',
            'caso_ticket_cx' => 'codigo_ticket',
            'caso_lead_venta' => 'codigo_lead',
            'caso_servicio' => 'codigo_servicio',
        };

        $clavesPorFila = [];
        $valores = [];
        foreach ($preview as $f) {
            if (! in_array($f->estado, ['procesada', 'duplicada'], true)) {
                continue;
            }
            $payload = is_array($f->payload) ? $f->payload : json_decode((string) $f->payload, true);
            if (! is_array($payload)) {
                continue;
            }
            $valor = trim((string) ($payload[$columnaCsv] ?? ''));
            if ($valor === '') {
                continue;
            }
            $clavesPorFila[(int) $f->numero_fila] = $valor;
            $valores[$valor] = true;
        }

        if ($clavesPorFila === []) {
            return [];
        }

        $rows = DB::table($tablaCti.' as cti')
            ->join('casos as c', 'c.id', '=', 'cti.caso_id')
            ->join('personas as p', 'p.id', '=', 'c.persona_id')
            ->where('cti.proyecto_id', $proyectoId)
            ->whereIn('cti.'.$columnaCti, array_keys($valores))
            ->select([
                'cti.'.$columnaCti.' as clave',
                'c.public_id as caso_public_id',
                'p.public_id as persona_public_id',
            ])
            ->get();

        $byClave = [];
        foreach ($rows as $r) {
            $byClave[(string) $r->clave] = [
                'persona_public_id' => (string) $r->persona_public_id,
                'caso_public_id' => (string) $r->caso_public_id,
            ];
        }

        $out = [];
        foreach ($clavesPorFila as $numFila => $clave) {
            if (isset($byClave[$clave])) {
                $out[$numFila] = $byClave[$clave];
            }
        }

        return $out;
    }

    private function ejecutarDryRun(string $tipoOperacion): void
    {
        if ($this->importacionId === null) {
            return;
        }

        $modo = ModoImportacion::from($this->modo);
        match ($tipoOperacion) {
            'cobranza' => app(ProcesarImportacionCasosCobranza::class)->ejecutar($this->importacionId, false, $modo),
            'cx' => app(ProcesarImportacionCasosTicketCx::class)->ejecutar($this->importacionId, false, $modo),
            'venta' => app(ProcesarImportacionCasosLeadVenta::class)->ejecutar($this->importacionId, false, $modo),
            'servicio' => app(ProcesarImportacionCasosServicio::class)->ejecutar($this->importacionId, false, $modo),
            default => null,
        };
    }

    /**
     * @param  list<string>  $columnasObligatorias
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
