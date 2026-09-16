<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Http\Livewire;

use App\Models\User;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Application\Services\CamposNuevosDeImportacion;
use App\Modules\Importaciones\Application\Services\ConsultaCoincidenciasImportacion;
use App\Modules\Importaciones\Application\Services\DescriptorDeFalloImportacion;
use App\Modules\Importaciones\Application\Services\FormatoDeImportacion;
use App\Modules\Importaciones\Application\Services\LectorCsv;
use App\Modules\Importaciones\Application\Services\LectorXlsx;
use App\Modules\Importaciones\Application\UseCases\CancelarImportacion;
use App\Modules\Importaciones\Application\UseCases\ConsultarProgresoImportacion;
use App\Modules\Importaciones\Application\UseCases\EncolarImportacion;
use App\Modules\Importaciones\Application\UseCases\InferirEsquemaDesdeHeaders;
use App\Modules\Importaciones\Application\UseCases\InferirEsquemaInput;
use App\Modules\Importaciones\Application\UseCases\PrepararImportacionDinamica;
use App\Modules\Importaciones\Application\UseCases\PrepararImportacionInput;
use App\Modules\Importaciones\Domain\Catalogo\CatalogoCamposSistema;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\RolContacto;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionEnCursoNoEditable;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionNoEncontrada;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionNoProcesable;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionSinPermisoCamposException;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionFilaModel;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use App\Support\Livewire\AutorizaEnProyectoActivo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
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
    use AutorizaEnProyectoActivo;
    use WithFileUploads;

    public int $paso = 1;

    public ?string $targetValor = null;

    public ?int $carteraId = null;

    public string $modo = 'upsert';

    public string $formatoEntrada = 'proyecto';

    public $archivo = null;

    public bool $archivoListo = false;

    /**
     * Las tres últimas claves son opcionales a propósito: llegaron después, y
     * una sesión de Livewire abierta durante un despliegue puede traer todavía
     * el array sin ellas.
     *
     * @var list<array{nombre_original: string, tipo_inferido: string, campo_sistema_mapeado: ?string, es_identificador_persona: bool, accion: string, es_identificador_caso?: bool, etiqueta_personalizada?: ?string, rol_contacto?: string}>
     */
    public array $columnas = [];

    public ?string $columnaIdentificadorNombre = null;

    public ?string $columnaCasoIdentificadorNombre = null;

    /**
     * `#[Locked]` porque lo fija el propio wizard al preparar la importación y
     * nada de la vista lo bindea: sin esto, un `$wire.set('importacionId', N)`
     * desde la consola apuntaba `ejecutar()` y `cancelar()` a la importación de
     * cualquier proyecto, porque los dos casos de uso la buscan con
     * `sinScopeProyecto()`.
     *
     * `carteraId` no puede llevarlo —es un `wire:model.live` de la vista, el
     * usuario la elige—, así que ahí la guarda es de pertenencia: se comprueba
     * contra el proyecto activo antes de usarla.
     */
    #[Locked]
    public ?int $importacionId = null;

    public ?array $resultadoDryRun = null;

    /** @var list<string> */
    public array $advertencias = [];

    public string $filtroFilas = 'todas';

    public function boot(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);
    }

    public function mount(): void
    {
        $this->autorizarEn('importaciones.crear');

        $disponibles = $this->targetsDisponibles();
        if (count($disponibles) === 1) {
            $this->targetValor = $disponibles[0]->value;
        }
    }

    public function updatedTargetValor(): void
    {
        $this->reset(['archivo', 'columnas', 'columnaIdentificadorNombre', 'columnaCasoIdentificadorNombre', 'carteraId']);
        $this->archivoListo = false;
        $this->paso = 1;
    }

    public function updatedArchivo(): void
    {
        $this->archivoListo = ($this->archivo instanceof UploadedFile);
    }

    public function subirArchivo(): void
    {
        $this->autorizarEn('importaciones.crear');

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

        $this->exigirCarteraDelProyecto();

        $this->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt,xlsx,xlsm', 'max:16384'],
        ], [
            'archivo.required' => 'Selecciona un archivo para continuar.',
        ], ['archivo' => 'archivo']);

        /** @var UploadedFile $file */
        $file = $this->archivo;
        $this->formatoEntrada = in_array(strtolower($file->getClientOriginalExtension()), ['xlsx', 'xlsm'], true) ? 'estandar' : 'proyecto';

        try {
            [$headers, $muestra] = $this->leerArchivo($file);
        } catch (\Throwable $e) {
            $this->addError('archivo', 'No se pudo leer el archivo: '.$this->motivoParaPantalla($e));

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
        if ($output->sugerenciaIdentificador !== null) {
            $this->marcarComoIdentificador($output->sugerenciaIdentificador);
        }
        if ($output->sugerenciaIdentificadorCaso !== null) {
            $this->marcarComoIdentificadorCaso($output->sugerenciaIdentificadorCaso);
        }
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

    public function seleccionarDestino(int $index, string $destino): void
    {
        $this->autorizarEn('importaciones.crear');
        if (! isset($this->columnas[$index])) {
            return;
        }

        $target = $this->target();
        if ($target === null) {
            return;
        }

        $field = null;
        $role = RolContacto::NINGUNO;
        if (str_starts_with($destino, 'sistema:')) {
            $field = substr($destino, 8);
            $available = array_column(CatalogoCamposSistema::paraTarget($target), 'codigo');
            if (! in_array($field, $available, true)) {
                $this->addError('columnas', 'Selecciona un campo disponible para esta operación.');

                return;
            }
            foreach ($this->columnas as $otherIndex => $column) {
                if ($otherIndex !== $index && $column['accion'] === AccionColumna::MAPEAR_SISTEMA->value
                    && $column['campo_sistema_mapeado'] === $field) {
                    $this->addError('columnas', 'Ese campo ya recibe otra columna. Cambia primero su destino.');

                    return;
                }
            }
            $action = AccionColumna::MAPEAR_SISTEMA;
        } elseif (str_starts_with($destino, 'contacto:')) {
            $role = RolContacto::tryFrom(substr($destino, 9)) ?? RolContacto::NINGUNO;
            if ($role === RolContacto::NINGUNO) {
                return;
            }
            $action = AccionColumna::IGNORAR;
        } else {
            $action = AccionColumna::tryFrom($destino);
            if ($action === null || $action === AccionColumna::MAPEAR_SISTEMA) {
                return;
            }
        }

        $this->resetErrorBag('columnas');
        $this->columnas[$index]['accion'] = $action->value;
        $this->columnas[$index]['campo_sistema_mapeado'] = $field;
        $this->columnas[$index]['rol_contacto'] = $role->value;
        if ($field !== 'identificacion' && $this->columnas[$index]['es_identificador_persona']) {
            $this->columnas[$index]['es_identificador_persona'] = false;
            $this->columnaIdentificadorNombre = '';
        }
        if ($field === 'identificacion') {
            $this->marcarComoIdentificador($this->columnas[$index]['nombre_original']);
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

    public function marcarComoIdentificadorCaso(string $nombreOriginal): void
    {
        foreach ($this->columnas as $i => $col) {
            if ($col['nombre_original'] === $nombreOriginal) {
                $this->columnas[$i]['es_identificador_caso'] = true;
                $this->columnaCasoIdentificadorNombre = $nombreOriginal;
            } else {
                $this->columnas[$i]['es_identificador_caso'] = false;
            }
        }
    }

    /**
     * Marca que una columna, además de lo que se haga con ella, genere contactos
     * de la persona.
     *
     * Es ortogonal a la acción: una columna de teléfonos suele guardarse también
     * como campo personalizado para que siga viéndose en la ficha. Lo que cambia
     * es que ahora, además, sus valores se parten y se dan de alta en
     * `contactos`, que es de donde sale el selector «Contacto usado».
     */
    public function marcarRolContacto(string $nombreOriginal, string $rol): void
    {
        $rolEnum = RolContacto::tryFrom($rol);

        if ($rolEnum === null) {
            return;
        }

        foreach ($this->columnas as $i => $col) {
            if ($col['nombre_original'] === $nombreOriginal) {
                $this->columnas[$i]['rol_contacto'] = $rolEnum->value;
                break;
            }
        }
    }

    public function actualizarEtiquetaPersonalizada(string $nombreOriginal, string $etiqueta): void
    {
        foreach ($this->columnas as $i => $col) {
            if ($col['nombre_original'] === $nombreOriginal) {
                $this->columnas[$i]['etiqueta_personalizada'] = trim($etiqueta) !== '' ? trim($etiqueta) : null;
                break;
            }
        }
    }

    public function confirmarMapeo(): void
    {
        // Fuera del try: el catch de abajo es un `\Throwable` que convierte
        // cualquier fallo en un mensaje de formulario, y se tragaría el 403 del
        // permiso y el 404 de la pertenencia dejando pasar el commit como si
        // sólo hubiera habido un error de mapeo.
        $this->autorizarEn('importaciones.crear');
        $this->exigirCarteraDelProyecto();

        try {
            $this->confirmarMapeoInterno();
        } catch (\Throwable $e) {
            // Un fallo de base de datos al preparar pintaba el INSERT con los
            // datos del archivo en el formulario. El descriptor resume, y es él
            // quien deja el detalle en el log bajo la referencia.
            $this->addError('columnas', $this->motivoParaPantalla($e));
        }
    }

    /**
     * Abre el paso 4 de una importación pasada del historial.
     *
     * `importacionId` es `#[Locked]` para que el cliente no lo reapunte, pero
     * asignarlo desde el servidor —tras comprobar permiso y pertenencia— es
     * exactamente para lo que existe el candado.
     */
    public function verImportacion(int $id): void
    {
        $this->autorizarEn('importaciones.crear');
        $this->exigirDelProyecto('importaciones', $id);

        $this->reset(['resultadoDryRun', 'filtroFilas', 'advertencias']);
        $this->importacionId = $id;
        $this->paso = 4;
    }

    private function confirmarMapeoInterno(): void
    {
        $this->autorizarEn('importaciones.crear');

        $target = $this->target();
        if ($target === null) {
            return;
        }

        // La cartera llega del cliente. Sin esta comprobación se creaban casos
        // del proyecto activo colgados de la cartera de otro proyecto.
        $this->exigirCarteraDelProyecto();

        $columnas = $this->deserializarColumnas();

        if ($columnas === []) {
            $this->addError('columnas', 'No hay columnas configuradas.');

            return;
        }

        $esquema = new EsquemaImportacion(
            target: $target,
            proyectoId: $this->proyectoId(),
            carteraId: $target === TargetImportacion::PERSONA ? null : $this->carteraId,
            modo: ModoImportacion::from($this->modo),
            columnas: $columnas,
            formatoEntrada: $this->formatoEntrada,
        );

        // `campos.definir` protege CREAR campos, no usar los que ya existen:
        // sin él se puede cargar un archivo cuyas columnas ya son campos de la
        // cartera. Se pregunta antes de persistir las filas para que un «no»
        // no deje una importación huérfana con miles de filas pendientes.
        $tienePermisoCampos = auth()->user()?->tienePermiso('campos.definir') === true;

        try {
            $esquema->validar();
            app(CamposNuevosDeImportacion::class)->exigirPermisoParaCrear($esquema, $tienePermisoCampos);
        } catch (\DomainException $e) {
            $this->addError('columnas', $this->motivoParaPantalla($e));

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
            $this->addError('archivo', 'No se pudo leer el archivo: '.$this->motivoParaPantalla($e));

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
            $this->addError('columnas', $this->motivoParaPantalla($e));

            return;
        }

        $this->paso = 3;
    }

    /**
     * Encola la importación en la cola `imports` (worker dedicado) y pasa al paso
     * de progreso, que hace polling cada 2s mientras el estado sea `procesando`.
     * Nunca se ejecuta dentro del request HTTP: un archivo grande superaría los
     * timeouts de PHP-FPM y nginx.
     */
    public function ejecutar(EncolarImportacion $encolar): void
    {
        $this->autorizarEn('importaciones.procesar');

        if ($this->importacionId === null) {
            return;
        }

        // `EncolarImportacion` busca la fila sin scope de proyecto.
        $this->exigirDelProyecto('importaciones', $this->importacionId);

        $modo = ModoImportacion::tryFrom($this->modo);
        if ($modo === null) {
            $this->addError('columnas', 'Modo de importación inválido.');

            return;
        }

        try {
            $encolar->execute($this->importacionId, $modo, (int) auth()->id(), $this->formatoEntrada);
        } catch (ImportacionEnCursoNoEditable|ImportacionNoEncontrada|ImportacionNoProcesable $e) {
            $this->addError('columnas', $this->motivoParaPantalla($e));

            return;
        }

        $this->paso = 4;
    }

    /**
     * Cancelar es la otra cara de procesar: detiene un lote en curso, así que
     * exige `importaciones.procesar` —no hay permiso propio de cancelación— y
     * que el lote sea del proyecto activo, porque `CancelarImportacion` lo
     * busca con `sinScopeProyecto()`.
     */
    public function cancelar(CancelarImportacion $cancelarUC): void
    {
        $this->autorizarEn('importaciones.procesar');

        if ($this->importacionId === null) {
            return;
        }

        $this->exigirDelProyecto('importaciones', $this->importacionId);

        $cancelarUC->execute($this->importacionId);
    }

    public function cerrar(): void
    {
        $this->reset(['archivo', 'columnas', 'columnaIdentificadorNombre', 'columnaCasoIdentificadorNombre', 'importacionId', 'filtroFilas', 'resultadoDryRun', 'advertencias', 'carteraId']);
        $this->archivoListo = false;
        $this->paso = 1;
        $this->modo = 'upsert';
        $this->formatoEntrada = 'proyecto';
    }

    public function render(): View
    {
        $proyecto = app('tenancy.proyecto_activo');
        $proyectoId = (int) $proyecto->id;
        $tipoOperacion = (string) $proyecto->tipo_operacion;

        $disponibles = CatalogoCamposSistema::targetsDisponibles($tipoOperacion);
        $target = $this->target();
        $usuario = auth()->user();
        $carterasPermitidas = $usuario instanceof User
            ? $usuario->carterasPermitidasParaPermiso('importaciones.procesar', $proyectoId) : [];

        $carteras = $target !== TargetImportacion::PERSONA
            ? DB::table('carteras')->where('proyecto_id', $proyectoId)->where('activo', true)->whereNull('eliminada_en')
                ->when($carterasPermitidas !== null, fn ($q) => $q->whereIn('id', $carterasPermitidas))
                ->orderBy('nombre')->get()
            : collect();

        $progreso = null;
        $importacionActual = null;
        $preview = collect();

        if ($this->importacionId !== null) {
            // El progreso y las filas se leen sin scope de proyecto en el caso
            // de uso, así que la pertenencia se comprueba aquí antes de mirar.
            $this->exigirDelProyecto('importaciones', $this->importacionId);

            $progreso = app(ConsultarProgresoImportacion::class)->execute($this->importacionId);
            $importacionActual = DB::table('importaciones')
                ->where('id', $this->importacionId)
                ->where('proyecto_id', $proyectoId)
                ->first();

            $q = DB::table('importacion_filas')
                ->where('importacion_id', $this->importacionId)
                ->where('proyecto_id', $proyectoId);
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
                'i.creada_en', 'i.error_global', 'i.payload_purgado_en', 'u.name as usuario_nombre',
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
            'proyectoId' => $proyectoId,
            'coincidencias' => $this->paso === 3 && $this->importacionId !== null
                ? app(ConsultaCoincidenciasImportacion::class)->execute($proyectoId, $this->importacionId) : null,
            'vistaRegional' => $this->paso === 3 ? app(FormatoDeImportacion::class)->vistaPrevia($preview, $importacionActual?->esquema, $this->formatoEntrada) : [],
        ]);
    }

    /**
     * Lo que se le dice al usuario cuando algo falla en el wizard.
     *
     * Siempre a través del descriptor: los cinco `catch` de este componente
     * pintaban `getMessage()` en el formulario, y un fallo de base de datos al
     * preparar enseñaba el INSERT con los datos del archivo en pantalla.
     */
    private function motivoParaPantalla(\Throwable $e): string
    {
        return app(DescriptorDeFalloImportacion::class)->describir($e, [
            'importacion_id' => $this->importacionId,
            'proyecto_id' => $this->proyectoId(),
        ])->motivo;
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
     * @param  list<string>  $headers
     * @param  list<string>  $filaCruda
     * @param  list<ColumnaExcel>  $columnas
     * @return array<string, string>
     */
    private function construirPayload(array $headers, array $filaCruda, array $columnas): array
    {
        $indicePorHeader = array_flip($headers);
        $payload = [];

        foreach ($columnas as $columna) {
            if (! $columna->debePersistirse()) {
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

            $payload[$columna->clavePayload()] = $valor;

            if ($columna->esIdentificadorCaso) {
                $payload['id_cpelegido'] = $valor;
            }
        }

        return $payload;
    }

    /**
     * @param  list<ColumnaExcel>  $columnas
     * @return list<array{nombre_original: string, tipo_inferido: string, campo_sistema_mapeado: ?string, es_identificador_persona: bool, es_identificador_caso: bool, accion: string, etiqueta_personalizada: ?string, rol_contacto: string}>
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
                'es_identificador_caso' => $col->esIdentificadorCaso,
                'accion' => $col->accion->value,
                'etiqueta_personalizada' => $col->etiquetaPersonalizada,
                'rol_contacto' => $col->rolContacto->value,
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
                esIdentificadorCaso: (bool) ($col['es_identificador_caso'] ?? false),
                accion: AccionColumna::from($col['accion']),
                etiquetaPersonalizada: $col['etiqueta_personalizada'] ?? null,
                rolContacto: RolContacto::from($col['rol_contacto'] ?? RolContacto::NINGUNO->value),
            );
        }

        return $resultado;
    }

    private function proyectoId(): int
    {
        return $this->proyectoActivoId();
    }

    /**
     * La cartera es lo único que el cliente elige y que después viaja al
     * esquema como id: si no es del proyecto activo, 404.
     */
    private function exigirCarteraDelProyecto(): void
    {
        if ($this->carteraId !== null) {
            $this->exigirDelProyecto('carteras', $this->carteraId);
            abort_unless(DB::table('carteras')->where('id', $this->carteraId)
                ->where('proyecto_id', $this->proyectoId())->where('activo', true)->whereNull('eliminada_en')->exists(),
                422, 'Selecciona una cartera activa.');
        }
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
