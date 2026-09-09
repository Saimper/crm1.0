<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire;

use App\Models\User;
use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\Exceptions\CambioDeTipoNoPermitido;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AutoFill;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Tenancy\Application\Services\ResolutorMandanteActivo;
use App\Modules\Tenancy\Infrastructure\Http\Middleware\ResolverMandanteActivo;
use App\Support\Codigo\GeneradorCodigo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use stdClass;
use Throwable;

/**
 * CRUD administrativo de campos personalizados. Solo ADMIN_GLOBAL (protegido por middleware de ruta).
 * Cubre ámbitos `caso` (× cartera) y `gestion` (× tipo_gestion). `compromiso` pendiente (enum de tipos).
 *
 * F3 · Acotado al MANDANTE — la empresa cliente. Antes esta pantalla corría sin
 * ningún contexto de tenant: `render()` volcaba `campos_personalizados` entera
 * (las definiciones de todos los clientes, agrupadas por proyecto) y el selector
 * del drawer listaba `proyectos` entera. Las acciones recibían un id crudo del
 * cliente y lo aplicaban tal cual, así que se podía editar, reasignar, apagar o
 * encender la definición de cualquier otro cliente.
 *
 * El contexto se resuelve en `mandanteEnPantalla()` y todo lo demás cuelga de
 * ahí. Cuando no hay contexto la pantalla se queda VACÍA: un tenant adivinado es
 * peor que no tenerlo, y enseñar el catálogo entero «mientras tanto» es
 * exactamente la fuga que se está cerrando.
 */
final class AdminCamposPersonalizados extends Component
{
    /**
     * El proyecto sobre el que trabaja la pantalla. NO lleva #[Locked] a
     * propósito: es una elección legítima del usuario (el `<select>` del drawer
     * escribe aquí), no un identificador de fila. Lo que lo hace seguro no es
     * que el cliente no pueda escribirlo, sino que `proyectoEnPantalla()` lo
     * revalida contra el alcance en CADA lectura y en CADA escritura.
     */
    public ?int $proyectoSeleccionadoId = null;

    public bool $formVisible = false;

    /**
     * Identificador de la fila que gobierna el UPDATE de `guardar()`.
     *
     * Tampoco lleva #[Locked], y la razón es explícita: el test de Fase 0
     * `test_guardar_no_debe_reescribir_un_campo_de_otro_proyecto` lo fija desde
     * el payload a propósito, para comprobar que la defensa real está en la
     * escritura y no en que el atacante «no pueda» proponer un id. Con #[Locked]
     * ese test reventaría con CannotUpdateLockedPropertyException en lugar de
     * pasar por `guardar()`. Quien protege esto es el guard de ORIGEN de
     * `guardar()`, que comprueba de quién es la fila ANTES de tocarla.
     */
    public ?int $campoEditandoId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /**
     * Memo del alcance dentro de UNA petición. Livewire reconstruye el
     * componente en cada request, así que esto nunca sobrevive a la siguiente.
     *
     * Se cachea porque el alcance se pregunta muchas veces por petición —
     * `render()` lo consulta, y `proyectoEnPantalla()` y `campoEnAlcance()` lo
     * vuelven a consultar cada uno — y cada consulta son dos queries
     * (`permitidos()` + los proyectos del mandante). La clave es el valor de
     * `proyectoSeleccionadoId`, que es LA única entrada mutable dentro de una
     * misma petición (`mount()` y `cambiarProyecto()` lo mueven): cachear sin
     * esa clave devolvería el alcance del proyecto anterior tras un cambio.
     *
     * @var array<string, list<int>>
     */
    private array $memoAlcance = [];

    public function mount(): void
    {
        $this->autorizar();

        // El proyecto inicial sale del cliente en el que se está trabajando, no
        // del primero de la instalación: `DB::table('proyectos')->value('id')`
        // abría la pantalla sobre el proyecto de un cliente cualquiera.
        $enAlcance = $this->proyectosEnAlcance();
        $this->proyectoSeleccionadoId = $enAlcance === [] ? null : $enAlcance[0];

        $this->reiniciarForm();
    }

    /**
     * Defensa en profundidad: `campos.definir` es exclusivo de ADMIN_GLOBAL.
     * Aunque la ruta tenga `admin.global`, re-valida en cada acción para bloquear
     * invocaciones directas de Livewire desde contextos no protegidos.
     */
    private function autorizar(): void
    {
        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }
        if ($user->esAdminGlobal()) {
            return;
        }
        if (! $user->tienePermiso('campos.definir')) {
            abort(403, 'No autorizado para definir campos personalizados.');
        }
    }

    /**
     * Cambiar el proyecto sobre el que trabaja la pantalla.
     *
     * Existe porque el listado dejó de volcar todos los proyectos a la vez: sin
     * un selector propio, un admin cuyo cliente tiene varios proyectos solo
     * podría ver el que `mount()` eligió, o tendría que abrir el drawer de
     * «nuevo campo» para moverse — que era la única forma de tocar
     * `proyectoSeleccionadoId` desde la UI.
     *
     * El id llega del cliente y se revalida contra el alcance ANTES de fijarlo.
     * Si no es del cliente activo la pantalla se queda donde estaba: no se sigue
     * al identificador que manden, que es justo como esta pantalla se arrastraba
     * sola al proyecto ajeno en `abrirFormEditar()`.
     */
    public function cambiarProyecto(mixed $proyectoId): void
    {
        $this->autorizar();

        $nuevo = $proyectoId === null || $proyectoId === '' ? null : (int) $proyectoId;

        if ($nuevo !== null && ! in_array($nuevo, $this->proyectosEnAlcance(), true)) {
            return;
        }

        $this->proyectoSeleccionadoId = $nuevo;

        // Cambiar de proyecto invalida cualquier edición en curso: el drawer
        // podría estar apuntando a un campo que ya no está en pantalla.
        $this->cerrarForm();
    }

    public function abrirFormCrear(): void
    {
        $this->autorizar();
        $this->campoEditandoId = null;
        $this->reiniciarForm();
        $this->formVisible = true;
    }

    public function abrirFormEditar(int $campoId): void
    {
        $this->autorizar();

        // El id llega del cliente, así que la fila se busca DENTRO del alcance.
        // Antes era `where('id', $campoId)` a secas: bastaba el id para leer la
        // definición de otro cliente.
        $row = $this->campoEnAlcance($campoId);
        if ($row === null) {
            return;
        }

        $reglas = is_string($row->reglas) ? (array) json_decode($row->reglas, true) : [];
        [$fechaMinPreset, $fechaMinCustom] = $this->descomponerMarcador($reglas['fecha_minima'] ?? null);
        [$fechaMaxPreset, $fechaMaxCustom] = $this->descomponerMarcador($reglas['fecha_maxima'] ?? null);

        $this->form = [
            'proyecto_id' => (int) $row->proyecto_id,
            'ambito' => (string) $row->ambito,
            'ambito_id' => (int) $row->ambito_id,
            'codigo' => (string) $row->codigo,
            'etiqueta' => (string) $row->etiqueta,
            'tipo' => (string) $row->tipo,
            'obligatorio' => (bool) $row->obligatorio,
            'activo' => (bool) $row->activo,
            'orden' => (int) $row->orden,
            'visible_en_gestion' => (bool) $row->visible_en_gestion,
            'grupo_campo_id' => $row->grupo_campo_id === null ? null : (int) $row->grupo_campo_id,
            'longitud_max' => isset($reglas['longitud_max']) ? (int) $reglas['longitud_max'] : null,
            'fecha_minima_preset' => $fechaMinPreset,
            'fecha_minima_custom' => $fechaMinCustom,
            'fecha_maxima_preset' => $fechaMaxPreset,
            'fecha_maxima_custom' => $fechaMaxCustom,
            'auto_fill' => isset($reglas['auto_fill']) ? (string) $reglas['auto_fill'] : '',
            'solo_lectura_tras_guardar' => ! empty($reglas['solo_lectura_tras_guardar']),
        ];

        // `$this->proyectoSeleccionadoId = $row->proyecto_id` estaba aquí y era
        // media fuga por sí solo: la pantalla se arrastraba al proyecto del id
        // recibido. El contexto no se mueve porque llegue un identificador; la
        // fila ya se comprobó contra el proyecto en pantalla, así que no hay
        // nada que reasignar.
        $this->campoEditandoId = $campoId;
        $this->formVisible = true;
    }

    public function updatedFormProyectoId(mixed $value): void
    {
        $nuevo = $value === null || $value === '' ? null : (int) $value;

        // El proyecto también llega del cliente: solo se acepta si está en el
        // alcance. Si no, la pantalla se queda donde estaba en lugar de seguir
        // al id que le manden.
        if ($nuevo !== null && ! in_array($nuevo, $this->proyectosEnAlcance(), true)) {
            // Se revierte al proyecto EN PANTALLA, no a `proyectoSeleccionadoId`
            // en crudo: ese último también puede venir del cliente y estar fuera
            // de alcance, y devolver el formulario a un id que no se puede tocar
            // solo aplaza el 403 hasta `guardar()`.
            $this->form['proyecto_id'] = $this->proyectoEnPantalla();

            return;
        }

        $this->proyectoSeleccionadoId = $nuevo;
        $this->form['ambito_id'] = null;
    }

    public function cerrarForm(): void
    {
        $this->formVisible = false;
        $this->campoEditandoId = null;
        $this->reiniciarForm();
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $this->autorizar();

        // 1 · El ORIGEN, antes que nada. `campoEditandoId` gobierna un UPDATE y
        //     viene del cliente. Validar solo el destino (`form.proyecto_id`)
        //     dejaba pasar justo el caso que importa: apuntar a la definición de
        //     otro cliente dejando el proyecto propio en el formulario, y
        //     traérsela con código y etiqueta reescritos.
        if ($this->campoEditandoId !== null) {
            $origen = $this->campoEnAlcance($this->campoEditandoId);
            if ($origen === null) {
                abort(403, 'Ese campo personalizado no pertenece a tu alcance.');
            }

            // 2 · El proyecto identifica al dueño de la definición y no es un
            //     campo editable: al editar se impone el de la fila y el select
            //     del drawer se pinta bloqueado. Mover un campo de proyecto
            //     dejaría huérfanos sus valores en `valores_campo_personalizado`.
            $this->form['proyecto_id'] = (int) $origen->proyecto_id;
        }

        $this->validate([
            'form.proyecto_id' => ['required', 'integer', 'exists:proyectos,id'],
            'form.ambito' => ['required', 'in:caso,gestion'],
            'form.ambito_id' => ['required', 'integer'],
            'form.codigo' => GeneradorCodigo::reglaValidacion(80),
            'form.etiqueta' => ['required', 'string', 'max:200'],
            'form.tipo' => ['required', 'in:texto_corto,texto_largo,numero_entero,numero_decimal,fecha,fecha_hora,booleano,moneda'],
            'form.obligatorio' => ['boolean'],
            'form.activo' => ['boolean'],
            'form.orden' => ['integer', 'min:0'],
            'form.visible_en_gestion' => ['boolean'],
            'form.grupo_campo_id' => ['nullable', 'integer', Rule::exists('grupos_campo', 'id')
                ->where('proyecto_id', (int) ($this->form['proyecto_id'] ?? 0))],
            'form.longitud_max' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'form.fecha_minima_preset' => ['nullable', 'in:,hoy,ahora,+1d,+7d,custom'],
            'form.fecha_maxima_preset' => ['nullable', 'in:,hoy,ahora,+1d,+7d,custom'],
            'form.fecha_minima_custom' => ['nullable', 'string', 'max:40'],
            'form.fecha_maxima_custom' => ['nullable', 'string', 'max:40'],
            'form.auto_fill' => ['nullable', 'in:,now,today,usuario_nombre,usuario_email,proyecto_codigo'],
            'form.solo_lectura_tras_guardar' => ['boolean'],
        ], [], [
            'form.proyecto_id' => 'proyecto',
            'form.ambito' => 'ámbito',
            'form.ambito_id' => 'ámbito_id',
            'form.codigo' => 'código',
            'form.etiqueta' => 'etiqueta',
            'form.tipo' => 'tipo',
            'form.longitud_max' => 'longitud máxima',
            'form.fecha_minima_preset' => 'fecha mínima',
            'form.fecha_maxima_preset' => 'fecha máxima',
            'form.auto_fill' => 'auto-relleno',
        ]);

        $proyectoId = (int) $this->form['proyecto_id'];

        // 3 · Y el DESTINO. Crear una definición dentro del proyecto de otro
        //     cliente es la misma fuga vista por el otro lado.
        if (! in_array($proyectoId, $this->proyectosEnAlcance(), true)) {
            abort(403, 'No puedes definir campos en un proyecto de otro cliente.');
        }

        if (! $this->validarAmbitoId()) {
            return;
        }
        if (! $this->validarReglasAvanzadas()) {
            return;
        }

        $reglas = [];
        if (! empty($this->form['longitud_max'])) {
            $reglas['longitud_max'] = (int) $this->form['longitud_max'];
        }
        $fechaMin = $this->resolverMarcador((string) ($this->form['fecha_minima_preset'] ?? ''), (string) ($this->form['fecha_minima_custom'] ?? ''));
        if ($fechaMin !== null) {
            $reglas['fecha_minima'] = $fechaMin;
        }
        $fechaMax = $this->resolverMarcador((string) ($this->form['fecha_maxima_preset'] ?? ''), (string) ($this->form['fecha_maxima_custom'] ?? ''));
        if ($fechaMax !== null) {
            $reglas['fecha_maxima'] = $fechaMax;
        }
        if (! empty($this->form['auto_fill'])) {
            $reglas['auto_fill'] = (string) $this->form['auto_fill'];
        }
        if (! empty($this->form['solo_lectura_tras_guardar'])) {
            $reglas['solo_lectura_tras_guardar'] = true;
        }

        $ambito = (string) $this->form['ambito'];
        $ambitoId = (int) $this->form['ambito_id'];

        $codigoInput = trim((string) ($this->form['codigo'] ?? ''));
        $codigoBase = $codigoInput === ''
            ? GeneradorCodigo::derivar((string) ($this->form['etiqueta'] ?? ''), 80, true)
            : GeneradorCodigo::normalizar($codigoInput, 80, true);

        $codigoFinal = GeneradorCodigo::resolverConflicto(
            $codigoBase,
            function (string $candidato) use ($proyectoId, $ambito, $ambitoId): bool {
                $q = DB::table('campos_personalizados')
                    ->where('proyecto_id', $proyectoId)
                    ->where('ambito', $ambito)
                    ->where('ambito_id', $ambitoId)
                    ->where('codigo', $candidato);
                if ($this->campoEditandoId !== null) {
                    $q->where('id', '!=', $this->campoEditandoId);
                }

                return $q->exists();
            },
            80,
        );
        $this->form['codigo'] = $codigoFinal;

        $payload = [
            'ambito' => $ambito,
            'ambito_id' => $ambitoId,
            'codigo' => $codigoFinal,
            'etiqueta' => (string) $this->form['etiqueta'],
            'tipo' => (string) $this->form['tipo'],
            'obligatorio' => (bool) $this->form['obligatorio'],
            'activo' => (bool) ($this->form['activo'] ?? true),
            'orden' => (int) ($this->form['orden'] ?? 100),
            'visible_en_gestion' => (bool) ($this->form['visible_en_gestion'] ?? true),
            'grupo_campo_id' => ($this->form['grupo_campo_id'] ?? null) === null || $this->form['grupo_campo_id'] === ''
                ? null
                : (int) $this->form['grupo_campo_id'],
            'reglas' => $reglas === [] ? null : json_encode($reglas),
        ];

        if ($this->campoEditandoId !== null) {
            try {
                app(ServicioCamposPersonalizados::class)
                    ->garantizarTipoMutable((int) $this->campoEditandoId, (string) $this->form['tipo']);
            } catch (CambioDeTipoNoPermitido $e) {
                $this->addError('form.tipo', $e->getMessage());

                return;
            }
        }

        if ($this->campoEditandoId === null) {
            DB::table('campos_personalizados')->insert($payload + ['proyecto_id' => $proyectoId]);
        } else {
            // `proyecto_id` queda FUERA del UPDATE: el dueño de la definición no
            // se reasigna desde un formulario de edición. El `where` redundante
            // sobre el proyecto es el último cinturón antes del disco.
            DB::table('campos_personalizados')
                ->where('id', $this->campoEditandoId)
                ->where('proyecto_id', $proyectoId)
                ->update($payload);
        }

        $this->cerrarForm();
        session()->flash('admin-campos-ok', 'Campo guardado.');
    }

    public function desactivar(int $campoId): void
    {
        $this->autorizar();

        // Era `UPDATE … WHERE id = ?` desnudo: cualquier id apagaba la
        // definición de cualquier cliente.
        $campo = $this->campoEnAlcance($campoId);
        if ($campo === null) {
            abort(403, 'Ese campo personalizado no pertenece a tu alcance.');
        }

        DB::table('campos_personalizados')
            ->where('id', $campoId)
            ->where('proyecto_id', (int) $campo->proyecto_id)
            ->update(['activo' => false]);
        session()->flash('admin-campos-ok', 'Campo desactivado.');
    }

    public function activar(int $campoId): void
    {
        $this->autorizar();

        $campo = $this->campoEnAlcance($campoId);
        if ($campo === null) {
            abort(403, 'Ese campo personalizado no pertenece a tu alcance.');
        }

        DB::table('campos_personalizados')
            ->where('id', $campoId)
            ->where('proyecto_id', (int) $campo->proyecto_id)
            ->update(['activo' => true]);
        session()->flash('admin-campos-ok', 'Campo activado.');
    }

    public function render(): View
    {
        $proyectosEnAlcance = $this->proyectosEnAlcance();
        $proyectoVisible = $this->proyectoEnPantalla();

        // El selector del drawer listaba `proyectos` ENTERA: era el rastro más
        // ancho de la pantalla, el catálogo de clientes de la instalación.
        $proyectos = $proyectosEnAlcance === []
            ? collect()
            : DB::table('proyectos')
                ->whereIn('id', $proyectosEnAlcance)
                ->select(['id', 'codigo', 'nombre', 'tipo_operacion'])
                ->orderBy('codigo')
                ->get();

        // Y el listado se construía sin un solo `where`, agrupando por
        // proyecto_id: la tabla enseñaba código y etiqueta de las definiciones
        // de todos los clientes a la vez. Ahora es el proyecto en pantalla o
        // nada.
        $camposPorProyecto = $proyectoVisible === null
            ? collect()
            : DB::table('campos_personalizados as c')
                ->leftJoin('carteras as ca', function ($join) use ($proyectoVisible): void {
                    $join->on('ca.id', '=', 'c.ambito_id')
                        ->where('c.ambito', 'caso')
                        // El join también se acota: un `ambito_id` heredado que
                        // apunte fuera del proyecto no debe traer el nombre de
                        // la cartera de otro cliente.
                        ->where('ca.proyecto_id', $proyectoVisible);
                })
                ->leftJoin('tipos_gestion as tg', function ($join) use ($proyectoVisible): void {
                    $join->on('tg.id', '=', 'c.ambito_id')
                        ->where('c.ambito', 'gestion')
                        ->where('tg.proyecto_id', $proyectoVisible);
                })
                ->where('c.proyecto_id', $proyectoVisible)
                ->select([
                    'c.id', 'c.proyecto_id', 'c.ambito', 'c.ambito_id', 'c.codigo', 'c.etiqueta',
                    'c.tipo', 'c.obligatorio', 'c.activo', 'c.orden',
                    'ca.nombre as cartera_nombre',
                    'tg.nombre as tipo_gestion_nombre',
                ])
                ->orderBy('c.ambito')
                ->orderBy('c.orden')
                ->get()
                ->groupBy('proyecto_id');

        $carteras = collect();
        $tiposGestion = collect();
        if ($proyectoVisible !== null) {
            $carteras = DB::table('carteras')
                ->where('proyecto_id', $proyectoVisible)
                ->where('activo', true)
                ->whereNull('eliminada_en')
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']);

            $tiposGestion = DB::table('tipos_gestion')
                ->where('proyecto_id', $proyectoVisible)
                ->where('activo', true)
                ->orderBy('orden')
                ->get(['id', 'codigo', 'nombre']);
        }

        return view('campos_personalizados::admin.lista', [
            'proyectos' => $proyectos,
            'camposPorProyecto' => $camposPorProyecto,
            'carteras' => $carteras,
            'tiposGestion' => $tiposGestion,
            'tiposCampo' => $this->tiposCampoDisponibles(),
            'grupos' => $this->gruposDelProyectoDelForm(),
        ]);

    }

    /**
     * Los grupos del proyecto que el formulario tiene seleccionado.
     *
     * Aquí no hay proyecto activo —el admin global elige uno en el propio
     * formulario—, así que la lista se resuelve desde `form.proyecto_id` y no
     * desde el binding de tenancy.
     *
     * @return Collection<int, stdClass>
     */
    private function gruposDelProyectoDelForm(): Collection
    {
        $proyectoId = (int) ($this->form['proyecto_id'] ?? 0);

        if ($proyectoId <= 0 || ! in_array($proyectoId, $this->proyectosEnAlcance(), true)) {
            return collect();
        }

        return DB::table('grupos_campo')
            ->where('proyecto_id', $proyectoId)
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'nombre']);
    }

    // =================================================================
    // Alcance — el contexto de tenant que a esta pantalla le faltaba
    // =================================================================

    /**
     * El mandante — la empresa cliente — dentro del cual transcurre la pantalla.
     *
     * Las fuentes, en este orden y ninguna más:
     *
     *  1. `tenancy.mandante_activo`, si el middleware lo publicó. Es la fuente
     *     autoritativa (sesión revalidada contra permisos en cada petición).
     *     Hoy `/admin/campos-personalizados` cuelga de `admin.global` y no de
     *     `mandante.activo`, así que normalmente no está; se consulta igual
     *     para que el día que la ruta lo gane mande él y no haga falta tocar
     *     esto.
     *  2. El cliente elegido en `/admin/cliente`, que vive en sesión con la
     *     misma clave que usa el middleware. Se REVALIDA siempre: la sesión
     *     propone, el permiso dispone.
     *  3. Derivado del proyecto en pantalla. Se DERIVA, nunca se acepta un
     *     mandante suelto junto a un proyecto: aceptar los dos y confiar en que
     *     casen es exactamente como se cruzan los tenants.
     *  4. El único que el usuario alcanza, y si alcanza varios, el primero.
     *     Elegir uno concreto mantiene la pantalla usable sin enseñar nunca dos
     *     clientes a la vez; para cambiar de cliente está `/admin/cliente`.
     */
    private function mandanteEnPantalla(): ?int
    {
        $usuario = auth()->user();
        if (! $usuario instanceof User) {
            return null;
        }

        $resolutor = app(ResolutorMandanteActivo::class);

        if (app()->bound('tenancy.mandante_activo')) {
            // El middleware publica un stdClass; se acepta también un id suelto
            // por si alguien lo bindea así desde un comando.
            $activo = app('tenancy.mandante_activo');
            $id = (int) (is_scalar($activo) ? $activo : data_get($activo, 'id', 0));

            return $id > 0 && $resolutor->puedeVer($usuario, $id) ? $id : null;
        }

        $deSesion = $this->mandanteEnSesion();
        if ($deSesion !== null && $resolutor->puedeVer($usuario, $deSesion)) {
            return $deSesion;
        }

        if ($this->proyectoSeleccionadoId !== null) {
            $delProyecto = $resolutor->delProyecto($this->proyectoSeleccionadoId);

            return $delProyecto !== null && $resolutor->puedeVer($usuario, $delProyecto)
                ? $delProyecto
                : null;
        }

        $permitidos = $resolutor->permitidos($usuario);

        return $permitidos === [] ? null : $permitidos[0];
    }

    /**
     * El cliente elegido en `/admin/cliente`. Se lee con la misma clave que
     * publica el middleware para no inventar un segundo mecanismo; si no hay
     * sesión (consola, tests sin middleware) simplemente no hay valor.
     */
    private function mandanteEnSesion(): ?int
    {
        try {
            $valor = session()->get(ResolverMandanteActivo::CLAVE_SESION);
        } catch (Throwable) {
            return null;
        }

        return is_numeric($valor) ? (int) $valor : null;
    }

    /**
     * Los proyectos que esta pantalla puede leer y escribir: los del mandante en
     * pantalla. Lista vacía = sin contexto, y entonces no se ve ni se toca nada.
     *
     * @return list<int>
     */
    private function proyectosEnAlcance(): array
    {
        $clave = (string) ($this->proyectoSeleccionadoId ?? 'sin-proyecto');

        if (array_key_exists($clave, $this->memoAlcance)) {
            return $this->memoAlcance[$clave];
        }

        $mandanteId = $this->mandanteEnPantalla();
        if ($mandanteId === null) {
            return $this->memoAlcance[$clave] = [];
        }

        return $this->memoAlcance[$clave] = DB::table('proyectos')
            ->where('mandante_id', $mandanteId)
            ->whereNull('eliminada_en')
            ->orderBy('codigo')
            ->pluck('id')
            ->map(fn (mixed $v): int => (int) $v)
            ->values()
            ->all();
    }

    /**
     * El proyecto sobre el que trabaja la pantalla, ya contrastado con el
     * alcance. `null` = fallo cerrado: se rinde vacío en vez de caer al catálogo
     * completo, que es como esta pantalla acabó enseñando a todos los clientes.
     */
    private function proyectoEnPantalla(): ?int
    {
        if ($this->proyectoSeleccionadoId === null) {
            return null;
        }

        return in_array($this->proyectoSeleccionadoId, $this->proyectosEnAlcance(), true)
            ? $this->proyectoSeleccionadoId
            : null;
    }

    /**
     * La fila del campo, pero solo si es del proyecto en pantalla. Devuelve
     * `null` tanto si no existe como si es de otro cliente: quien llama decide
     * si eso es un 403 (escrituras) o un retorno silencioso (abrir el form).
     */
    private function campoEnAlcance(int $campoId): ?stdClass
    {
        $proyectoVisible = $this->proyectoEnPantalla();
        if ($proyectoVisible === null) {
            return null;
        }

        $row = DB::table('campos_personalizados')
            ->where('id', $campoId)
            ->where('proyecto_id', $proyectoVisible)
            ->first();

        return $row === null ? null : (object) (array) $row;
    }

    /** @return Collection<int, array{valor:string, etiqueta:string}> */
    private function tiposCampoDisponibles(): Collection
    {
        return collect([
            ['valor' => TipoCampo::TEXTO_CORTO->value,    'etiqueta' => 'Texto corto'],
            ['valor' => TipoCampo::TEXTO_LARGO->value,    'etiqueta' => 'Texto largo'],
            ['valor' => TipoCampo::NUMERO_ENTERO->value,  'etiqueta' => 'Número entero'],
            ['valor' => TipoCampo::NUMERO_DECIMAL->value, 'etiqueta' => 'Número decimal'],
            ['valor' => TipoCampo::FECHA->value,          'etiqueta' => 'Fecha'],
            ['valor' => TipoCampo::FECHA_HORA->value,     'etiqueta' => 'Fecha y hora'],
            ['valor' => TipoCampo::BOOLEANO->value,       'etiqueta' => 'Sí / No'],
            ['valor' => TipoCampo::MONEDA->value,         'etiqueta' => 'Moneda (monto)'],
        ]);
    }

    private function validarAmbitoId(): bool
    {
        $proyectoId = (int) $this->form['proyecto_id'];
        $ambito = (string) $this->form['ambito'];
        $ambitoId = (int) $this->form['ambito_id'];

        $tabla = match ($ambito) {
            'caso' => 'carteras',
            'gestion' => 'tipos_gestion',
            default => null,
        };
        if ($tabla === null) {
            $this->addError('form.ambito', 'Ámbito inválido.');

            return false;
        }

        $existe = DB::table($tabla)
            ->where('id', $ambitoId)
            ->where('proyecto_id', $proyectoId)
            ->exists();
        if (! $existe) {
            $this->addError('form.ambito_id', 'El ámbito seleccionado no pertenece al proyecto elegido.');

            return false;
        }

        return true;
    }

    private function reiniciarForm(): void
    {
        $this->form = [
            'proyecto_id' => $this->proyectoEnPantalla(),
            'ambito' => 'caso',
            'ambito_id' => null,
            'codigo' => '',
            'etiqueta' => '',
            'tipo' => 'texto_corto',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 100,
            'visible_en_gestion' => true,
            'grupo_campo_id' => null,
            'longitud_max' => null,
            'fecha_minima_preset' => '',
            'fecha_minima_custom' => '',
            'fecha_maxima_preset' => '',
            'fecha_maxima_custom' => '',
            'auto_fill' => '',
            'solo_lectura_tras_guardar' => false,
        ];
    }

    /** @return array{0:string,1:string} [preset, custom] */
    private function descomponerMarcador(mixed $token): array
    {
        if (! is_string($token) || $token === '') {
            return ['', ''];
        }
        if (in_array($token, ['hoy', 'ahora', '+1d', '+7d'], true)) {
            return [$token, ''];
        }

        return ['custom', $token];
    }

    private function resolverMarcador(string $preset, string $custom): ?string
    {
        if ($preset === '') {
            return null;
        }
        if ($preset === 'custom') {
            $custom = trim($custom);

            return $custom === '' ? null : $custom;
        }

        return $preset;
    }

    private function validarReglasAvanzadas(): bool
    {
        $tipo = (string) $this->form['tipo'];

        $aplicaFecha = in_array($tipo, ['fecha', 'fecha_hora'], true);
        foreach (['fecha_minima', 'fecha_maxima'] as $clave) {
            $preset = (string) ($this->form[$clave.'_preset'] ?? '');
            if ($preset === '') {
                continue;
            }
            if (! $aplicaFecha) {
                $this->addError('form.'.$clave.'_preset', 'Solo aplica a campos de fecha o fecha-hora.');

                return false;
            }
            if ($preset === 'ahora' && $tipo !== 'fecha_hora') {
                $this->addError('form.'.$clave.'_preset', 'El token «ahora» solo aplica a fecha y hora.');

                return false;
            }
            if ($preset === 'custom') {
                $custom = trim((string) ($this->form[$clave.'_custom'] ?? ''));
                if ($custom === '') {
                    $this->addError('form.'.$clave.'_custom', 'Ingresa la fecha personalizada.');

                    return false;
                }
                if (preg_match('/^([+-]\d+d|hoy|ahora)$/', $custom) !== 1 && strtotime($custom) === false) {
                    $this->addError('form.'.$clave.'_custom', 'Formato no reconocido.');

                    return false;
                }
            }
        }

        $autoFill = (string) ($this->form['auto_fill'] ?? '');
        if ($autoFill !== '') {
            $auto = AutoFill::tryFrom($autoFill);
            $tipoEnum = TipoCampo::tryFrom($tipo);
            if ($auto === null || $tipoEnum === null || ! $auto->tipoCompatible($tipoEnum)) {
                $this->addError('form.auto_fill', 'El token de auto-relleno no aplica al tipo seleccionado.');

                return false;
            }
        }

        return true;
    }
}
