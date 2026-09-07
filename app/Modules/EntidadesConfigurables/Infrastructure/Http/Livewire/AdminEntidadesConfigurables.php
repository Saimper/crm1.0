<?php

declare(strict_types=1);

namespace App\Modules\EntidadesConfigurables\Infrastructure\Http\Livewire;

use App\Models\User;
use App\Modules\EntidadesConfigurables\Application\Services\ServicioEntidades;
use App\Modules\EntidadesConfigurables\Domain\ValueObjects\RelacionEntidad;
use App\Modules\Tenancy\Application\Services\ResolutorMandanteActivo;
use App\Modules\Tenancy\Infrastructure\Http\Middleware\ResolverMandanteActivo;
use App\Support\Codigo\GeneradorCodigo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use stdClass;
use Throwable;

/**
 * Administra definiciones de entidades configurables + sus campos (que viven en `campos_personalizados`
 * con ámbito `entidad_configurable`). Permiso exclusivo: `entidades.definir` (ADMIN_GLOBAL).
 *
 * Defensa en profundidad: re-valida el permiso en cada acción vía `autorizar()`, no solo en el
 * middleware HTTP (mismo patrón que F23 para campos).
 *
 * ALCANCE POR MANDANTE (Fase 3)
 * -----------------------------
 * Esta pantalla se escribió sin contexto de tenant: cargaba `DB::table('proyectos')->get()` entero
 * en el selector y todas sus acciones aceptaban un id crudo. `entidades.definir` es exclusivo de
 * ADMIN_GLOBAL, pero "alcanzar todos los clientes" no es "verlos a la vez": se trabaja DENTRO de un
 * mandante, y un id que no pertenece a ese mandante no debe poder leerse ni escribirse desde aquí.
 *
 * Cuatro reglas, las mismas que en AdminProyectos:
 *  1. `#[Locked]` en todo identificador público: en Livewire 3 una propiedad pública la fija el
 *     payload del cliente, así que sin candado el id que gobierna una escritura lo elige quien ataca.
 *     `proyectoSeleccionadoId` es la excepción deliberada — es el `<select>` de la pantalla, lo
 *     cambia el usuario — y por eso se revalida contra el alcance en CADA uso.
 *  2. Cada acción revalida el id que recibe antes de tocar la fila. Filtrar en `render()` no protege
 *     una escritura.
 *  3. Se valida el ORIGEN, no solo el destino: al editar un campo se comprueba de qué entidad y de
 *     qué proyecto era la fila que se está reescribiendo.
 *  4. Lo que identifica al dueño (`proyecto_id`, `ambito`, `ambito_id`) no se reescribe en una
 *     edición: se fija al crear y ahí se queda.
 */
final class AdminEntidadesConfigurables extends Component
{
    /**
     * El proyecto elegido en el selector. NO lleva `#[Locked]` a propósito: es el
     * `<select>` de la pantalla y llega del cliente por diseño. Precisamente por
     * eso nunca se usa crudo — `proyectosEnAlcance()` lo valida en cada uso.
     */
    public ?int $proyectoSeleccionadoId = null;

    public bool $formVisible = false;

    #[Locked]
    public ?int $entidadEditandoId = null;

    public string $formCodigo = '';

    public string $formNombre = '';

    public string $formDescripcion = '';

    public string $formIcono = '';

    public string $formRelacion = 'ninguna';

    public ?int $formCarteraId = null;

    public bool $formActivo = true;

    /** Entidad cuyo panel de campos se está mostrando. */
    #[Locked]
    public ?int $entidadConCamposAbiertosId = null;

    public bool $formCampoVisible = false;

    #[Locked]
    public ?int $campoEditandoId = null;

    public string $formCampoCodigo = '';

    public string $formCampoEtiqueta = '';

    public string $formCampoTipo = 'texto_corto';

    public bool $formCampoObligatorio = false;

    public int $formCampoOrden = 100;

    /**
     * Cache del alcance dentro de la petición. Es privada a propósito: Livewire
     * solo serializa propiedades públicas, así que no viaja al cliente ni puede
     * volver manipulada.
     *
     * @var list<int>|null
     */
    private ?array $proyectosEnAlcanceCache = null;

    public function mount(): void
    {
        $this->autorizar();

        // El primer proyecto DEL CLIENTE ACTIVO, no el primero de la instalación.
        $enAlcance = $this->proyectosEnAlcance();
        $this->proyectoSeleccionadoId = $enAlcance === [] ? null : $enAlcance[0];
    }

    private function autorizar(): void
    {
        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }
        if ($user->esAdminGlobal()) {
            return;
        }
        if (! $user->tienePermiso('entidades.definir')) {
            abort(403, 'No autorizado para definir entidades configurables.');
        }
    }

    // ---------- Alcance (mandante activo) ----------

    private function resolutor(): ResolutorMandanteActivo
    {
        return app(ResolutorMandanteActivo::class);
    }

    /**
     * El mandante — la empresa cliente — dentro del cual transcurre esta pantalla.
     *
     * Mismo orden y mismas fuentes que la pantalla hermana
     * /admin/campos-personalizados. Es a propósito: un solo mecanismo para
     * obtener el alcance, porque la incoherencia entre pantallas es justamente
     * por donde se cuelan las fugas.
     *
     *  1. `tenancy.mandante_activo`, si el middleware lo publicó. Es la fuente
     *     autoritativa. OJO: hoy esta ruta cuelga de `admin.global` y NO de
     *     `mandante.activo` (routes/web.php), así que normalmente NO está; se
     *     consulta igual para que el día que la ruta lo gane mande él sin tocar
     *     esto. Y si está pero el usuario ya no alcanza ese cliente, se corta
     *     aquí: un contexto publicado no se «mejora» buscando otro más abajo.
     *  2. El cliente elegido en `/admin/cliente`, que vive en sesión con la
     *     misma clave que escribe el middleware. Se REVALIDA siempre: la sesión
     *     propone, el permiso dispone. Sin middleware en la ruta, ESTA es la
     *     fuente que hace que «cliente activo» signifique algo aquí.
     *  3. Derivado del proyecto en pantalla. Va DESPUÉS de la sesión a
     *     propósito: `proyectoSeleccionadoId` llega del payload del cliente, y
     *     si mandara sobre el cliente activo bastaría un payload para cambiar
     *     de tenant sin pasar por `/admin/cliente`.
     *  4. El primero que el usuario alcanza. Sin este último escalón, un
     *     ADMIN_GLOBAL con varios clientes y sin sesión se encontraba la
     *     pantalla muerta (ni selector, ni proyectos, ni forma de elegir).
     *     Enseña uno, nunca dos a la vez.
     *
     * Si no sale de ahí, no se inventa: sin mandante no hay alcance, y sin alcance
     * la pantalla se queda vacía en vez de enseñar la instalación entera.
     */
    private function mandanteEnContexto(): ?int
    {
        $usuario = auth()->user();
        if (! $usuario instanceof User) {
            return null;
        }

        $resolutor = $this->resolutor();

        if (app()->bound('tenancy.mandante_activo')) {
            // El middleware publica un stdClass; se acepta también un id suelto
            // por si alguien lo bindea así desde un comando o un test.
            $activo = app('tenancy.mandante_activo');
            $id = (int) (is_scalar($activo) ? $activo : data_get($activo, 'id', 0));

            return $id > 0 && $resolutor->puedeVer($usuario, $id) ? $id : null;
        }

        $deSesion = $this->mandanteEnSesion();
        if ($deSesion !== null && $resolutor->puedeVer($usuario, $deSesion)) {
            return $deSesion;
        }

        if ($this->proyectoSeleccionadoId !== null) {
            $delProyecto = $resolutor->delProyecto((int) $this->proyectoSeleccionadoId);

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
     * sesión (consola, jobs, tests que montan el componente suelto) simplemente
     * no hay valor.
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
     * Los proyectos que esta pantalla puede leer y escribir. Vacío = ninguno, que
     * es el estado correcto cuando no hay contexto: fallo cerrado.
     *
     * @return list<int>
     */
    private function proyectosEnAlcance(): array
    {
        if ($this->proyectosEnAlcanceCache !== null) {
            return $this->proyectosEnAlcanceCache;
        }

        $usuario = auth()->user();
        $mandanteId = $this->mandanteEnContexto();

        // `mandanteEnContexto()` ya revalida cada fuente, pero se vuelve a
        // comprobar aquí: es el único punto por el que pasa TODO lo que esta
        // pantalla lee y escribe, y el permiso de ahora es el que manda.
        if (! $usuario instanceof User || $mandanteId === null || ! $this->resolutor()->puedeVer($usuario, $mandanteId)) {
            return $this->proyectosEnAlcanceCache = [];
        }

        /** @var list<int> $ids */
        $ids = DB::table('proyectos')
            ->where('mandante_id', $mandanteId)
            ->whereNull('eliminada_en')
            ->orderBy('codigo')
            ->pluck('id')
            ->map(fn (mixed $v): int => (int) $v)
            ->values()
            ->all();

        return $this->proyectosEnAlcanceCache = $ids;
    }

    private function proyectoEnAlcance(?int $proyectoId): bool
    {
        return $proyectoId !== null && in_array($proyectoId, $this->proyectosEnAlcance(), true);
    }

    private function guardContraProyectoAjeno(?int $proyectoId): void
    {
        if (! $this->proyectoEnAlcance($proyectoId)) {
            abort(403, 'Ese proyecto no pertenece al cliente activo.');
        }
    }

    /**
     * La entidad, solo si es del proyecto que hay en pantalla (y ese proyecto es
     * del cliente activo). `null` significa «no existe para este usuario»: se
     * devuelve lo mismo para un id inexistente que para uno ajeno, para no
     * convertir la pantalla en un oráculo de qué ids hay en la instalación.
     */
    private function entidadDelProyectoSeleccionado(int $entidadId): ?stdClass
    {
        if (! $this->proyectoEnAlcance($this->proyectoSeleccionadoId)) {
            return null;
        }

        $row = DB::table('entidades_configurables')
            ->where('id', $entidadId)
            ->where('proyecto_id', $this->proyectoSeleccionadoId)
            ->whereNull('eliminada_en')
            ->first();

        return $row === null ? null : (object) (array) $row;
    }

    /**
     * El campo, solo si cuelga de la entidad abierta en pantalla. Se comprueba el
     * ámbito además del id: `campos_personalizados` mezcla las definiciones de
     * caso/gestión/compromiso con las de entidades configurables, y desde aquí
     * solo se administran estas últimas.
     */
    private function campoDeLaEntidadAbierta(int $campoId): ?stdClass
    {
        if ($this->entidadConCamposAbiertosId === null) {
            return null;
        }

        $entidad = $this->entidadDelProyectoSeleccionado($this->entidadConCamposAbiertosId);
        if ($entidad === null) {
            return null;
        }

        $row = DB::table('campos_personalizados')
            ->where('id', $campoId)
            ->where('proyecto_id', (int) $entidad->proyecto_id)
            ->where('ambito', 'entidad_configurable')
            ->where('ambito_id', (int) $entidad->id)
            ->first();

        return $row === null ? null : (object) (array) $row;
    }

    /**
     * Cambiar de proyecto deja fuera de contexto todo lo que estaba abierto: la
     * entidad del panel y el campo en edición eran del proyecto anterior. Si no
     * se limpian, la siguiente escritura llega con un id que ya no corresponde.
     */
    public function updatedProyectoSeleccionadoId(): void
    {
        $this->proyectosEnAlcanceCache = null;
        $this->entidadConCamposAbiertosId = null;
        $this->entidadEditandoId = null;
        $this->campoEditandoId = null;
        $this->formVisible = false;
        $this->formCampoVisible = false;
        $this->formCarteraId = null;
        $this->resetErrorBag();
    }

    // ---------- Entidades ----------

    public function abrirFormCrear(): void
    {
        $this->autorizar();
        $this->entidadEditandoId = null;
        $this->formCodigo = '';
        $this->formNombre = '';
        $this->formDescripcion = '';
        $this->formIcono = '';
        $this->formRelacion = 'ninguna';
        $this->formCarteraId = null;
        $this->formActivo = true;
        $this->formVisible = true;
        $this->resetErrorBag();
    }

    public function abrirFormEditar(int $entidadId): void
    {
        $this->autorizar();

        // Antes bastaba con `where('id', …)`, y encima el método arrastraba la
        // pantalla al proyecto de la fila (`proyectoSeleccionadoId = …`): pedir la
        // entidad de otro cliente cambiaba de tenant sin pasar por ningún permiso.
        // Ahora la fila tiene que ser del proyecto que ya está en pantalla, y el
        // selector no se mueve nunca desde aquí.
        $row = $this->entidadDelProyectoSeleccionado($entidadId);
        if ($row === null) {
            return;
        }

        $this->entidadEditandoId = (int) $row->id;
        $this->formCodigo = (string) $row->codigo;
        $this->formNombre = (string) $row->nombre;
        $this->formDescripcion = (string) ($row->descripcion ?? '');
        $this->formIcono = (string) ($row->icono ?? '');
        $this->formRelacion = (string) $row->relacion_con;
        $this->formCarteraId = $row->cartera_id === null ? null : (int) $row->cartera_id;
        $this->formActivo = (bool) $row->activo;
        $this->formVisible = true;
    }

    public function cerrarForm(): void
    {
        $this->formVisible = false;
        $this->entidadEditandoId = null;
        $this->resetErrorBag();
    }

    public function guardarEntidad(ServicioEntidades $servicio): void
    {
        $this->autorizar();

        $this->validate([
            'proyectoSeleccionadoId' => ['required', 'integer', 'exists:proyectos,id'],
            'formCodigo' => GeneradorCodigo::reglaValidacion(80),
            'formNombre' => ['required', 'string', 'max:150'],
            'formDescripcion' => ['nullable', 'string', 'max:500'],
            'formIcono' => ['nullable', 'string', 'max:50'],
            'formRelacion' => ['required', 'in:ninguna,caso,persona'],
            'formCarteraId' => ['nullable', 'integer', 'exists:carteras,id'],
        ]);

        // El DESTINO: el proyecto donde se va a escribir.
        $this->guardContraProyectoAjeno($this->proyectoSeleccionadoId);
        $proyectoId = (int) $this->proyectoSeleccionadoId;

        // Y el ORIGEN: de quién era la fila que `entidadEditandoId` señala. Validar
        // solo el destino es el fallo que dejaba apuntar a la entidad de otro
        // cliente y reescribirla desde el formulario propio.
        $entidadOrigen = null;
        if ($this->entidadEditandoId !== null) {
            $entidadOrigen = $this->entidadDelProyectoSeleccionado($this->entidadEditandoId);
            if ($entidadOrigen === null) {
                abort(403, 'Esa entidad configurable no pertenece al proyecto en pantalla.');
            }
        }

        // La cartera acota la entidad dentro del proyecto: si no es de ese
        // proyecto, no es una cartera válida aquí por mucho que exista.
        if ($this->formCarteraId !== null) {
            $carteraDelProyecto = DB::table('carteras')
                ->where('id', $this->formCarteraId)
                ->where('proyecto_id', $proyectoId)
                ->exists();

            if (! $carteraDelProyecto) {
                $this->addError('formCarteraId', 'La cartera no pertenece al proyecto seleccionado.');

                return;
            }
        }

        $codigoInput = trim($this->formCodigo);
        $codigoBase = $codigoInput === ''
            ? GeneradorCodigo::derivar($this->formNombre, 80)
            : GeneradorCodigo::normalizar($codigoInput, 80);

        $codigoFinal = GeneradorCodigo::resolverConflicto(
            $codigoBase,
            function (string $candidato) use ($proyectoId): bool {
                $q = DB::table('entidades_configurables')
                    ->where('proyecto_id', $proyectoId)
                    ->where('codigo', $candidato);
                if ($this->entidadEditandoId !== null) {
                    $q->where('id', '!=', $this->entidadEditandoId);
                }

                return $q->exists();
            },
            80,
        );
        $this->formCodigo = $codigoFinal;

        try {
            if ($entidadOrigen === null) {
                $servicio->crearEntidad(
                    proyectoId: $proyectoId,
                    codigo: $codigoFinal,
                    nombre: $this->formNombre,
                    relacion: RelacionEntidad::from($this->formRelacion),
                    carteraId: $this->formCarteraId,
                    descripcion: $this->formDescripcion !== '' ? $this->formDescripcion : null,
                    icono: $this->formIcono !== '' ? $this->formIcono : null,
                );
            } else {
                // El proyecto de la entidad NO se toca: es lo que identifica a su
                // dueño, y no se cambia desde un formulario de edición.
                $servicio->actualizarEntidad(
                    proyectoId: (int) $entidadOrigen->proyecto_id,
                    entidadId: (int) $entidadOrigen->id,
                    nombre: $this->formNombre,
                    descripcion: $this->formDescripcion !== '' ? $this->formDescripcion : null,
                    icono: $this->formIcono !== '' ? $this->formIcono : null,
                    activo: $this->formActivo,
                );
            }
        } catch (Throwable $e) {
            $this->addError('formCodigo', $e->getMessage());

            return;
        }

        session()->flash('entidades-ok', 'Entidad guardada.');
        $this->cerrarForm();
    }

    public function eliminarEntidad(int $entidadId, ServicioEntidades $servicio): void
    {
        $this->autorizar();

        // Antes el id crudo llegaba tal cual a `ServicioEntidades::eliminarEntidad`,
        // que lo aplicaba con `sinScopeProyecto()`: se borraba (soft) la entidad de
        // cualquier cliente de la instalación.
        $entidad = $this->entidadDelProyectoSeleccionado($entidadId);
        if ($entidad === null) {
            abort(403, 'Esa entidad configurable no pertenece al proyecto en pantalla.');
        }

        $servicio->eliminarEntidad((int) $entidad->proyecto_id, (int) $entidad->id);

        if ($this->entidadConCamposAbiertosId === (int) $entidad->id) {
            $this->cerrarCampos();
        }

        session()->flash('entidades-ok', 'Entidad desactivada.');
    }

    // ---------- Campos de la entidad ----------

    public function abrirCamposDe(int $entidadId): void
    {
        $this->autorizar();

        // El panel de campos era la fuga más silenciosa: guardaba el id sin
        // validar y `render()` listaba por `ambito_id` sin `proyecto_id`, así que
        // enseñaba el esquema de datos del otro cliente sin escribir nada.
        if ($this->entidadDelProyectoSeleccionado($entidadId) === null) {
            return;
        }

        $this->entidadConCamposAbiertosId = $entidadId;
        $this->formCampoVisible = false;
        $this->campoEditandoId = null;
    }

    public function cerrarCampos(): void
    {
        $this->entidadConCamposAbiertosId = null;
        $this->formCampoVisible = false;
        $this->campoEditandoId = null;
    }

    public function abrirFormCampoCrear(): void
    {
        $this->autorizar();
        $this->campoEditandoId = null;
        $this->formCampoCodigo = '';
        $this->formCampoEtiqueta = '';
        $this->formCampoTipo = 'texto_corto';
        $this->formCampoObligatorio = false;
        $this->formCampoOrden = 100;
        $this->formCampoVisible = true;
    }

    public function abrirFormCampoEditar(int $campoId): void
    {
        $this->autorizar();

        $row = $this->campoDeLaEntidadAbierta($campoId);
        if ($row === null) {
            return;
        }

        $this->campoEditandoId = (int) $row->id;
        $this->formCampoCodigo = (string) $row->codigo;
        $this->formCampoEtiqueta = (string) $row->etiqueta;
        $this->formCampoTipo = (string) $row->tipo;
        $this->formCampoObligatorio = (bool) $row->obligatorio;
        $this->formCampoOrden = (int) $row->orden;
        $this->formCampoVisible = true;
    }

    public function cerrarFormCampo(): void
    {
        $this->formCampoVisible = false;
        $this->campoEditandoId = null;
    }

    public function guardarCampo(): void
    {
        $this->autorizar();
        if ($this->entidadConCamposAbiertosId === null) {
            return;
        }

        $this->validate([
            'formCampoCodigo' => GeneradorCodigo::reglaValidacion(80),
            'formCampoEtiqueta' => ['required', 'string', 'max:200'],
            'formCampoTipo' => ['required', 'in:texto_corto,texto_largo,numero_entero,numero_decimal,fecha,fecha_hora,booleano,moneda'],
            'formCampoObligatorio' => ['boolean'],
            'formCampoOrden' => ['integer', 'min:0'],
        ]);

        // El `proyecto_id` y el `ambito_id` de la escritura salían de la entidad
        // abierta sin comprobar que esa entidad fuese del proyecto en pantalla:
        // bastaba abrir la entidad ajena para inyectarle una columna nueva.
        $entidad = $this->entidadDelProyectoSeleccionado($this->entidadConCamposAbiertosId);
        if ($entidad === null) {
            $this->addError('formCampoCodigo', 'Entidad no encontrada.');

            return;
        }

        // ORIGEN de la edición: la fila que `campoEditandoId` señala tiene que ser
        // ya de esta entidad. Si no, el UPDATE se traería un campo ajeno.
        $campoOrigen = null;
        if ($this->campoEditandoId !== null) {
            $campoOrigen = $this->campoDeLaEntidadAbierta($this->campoEditandoId);
            if ($campoOrigen === null) {
                abort(403, 'Ese campo no pertenece a la entidad abierta.');
            }
        }

        $codigoInput = trim($this->formCampoCodigo);
        $codigoBase = $codigoInput === ''
            ? GeneradorCodigo::derivar($this->formCampoEtiqueta, 80, true)
            : GeneradorCodigo::normalizar($codigoInput, 80, true);

        $codigoFinal = GeneradorCodigo::resolverConflicto(
            $codigoBase,
            function (string $candidato) use ($entidad): bool {
                $q = DB::table('campos_personalizados')
                    ->where('proyecto_id', (int) $entidad->proyecto_id)
                    ->where('ambito', 'entidad_configurable')
                    ->where('ambito_id', (int) $entidad->id)
                    ->where('codigo', $candidato);
                if ($this->campoEditandoId !== null) {
                    $q->where('id', '!=', $this->campoEditandoId);
                }

                return $q->exists();
            },
            80,
        );
        $this->formCampoCodigo = $codigoFinal;

        // Lo que puede cambiar en una edición. Ni `proyecto_id` ni `ambito` ni
        // `ambito_id` están aquí: identifican al dueño del campo y se fijan al
        // crearlo.
        $mutables = [
            'codigo' => $codigoFinal,
            'etiqueta' => $this->formCampoEtiqueta,
            'tipo' => $this->formCampoTipo,
            'obligatorio' => $this->formCampoObligatorio,
            'orden' => $this->formCampoOrden,
        ];

        if ($campoOrigen === null) {
            DB::table('campos_personalizados')->insert($mutables + [
                'proyecto_id' => (int) $entidad->proyecto_id,
                'ambito' => 'entidad_configurable',
                'ambito_id' => (int) $entidad->id,
                'activo' => true,
            ]);
        } else {
            DB::table('campos_personalizados')
                ->where('id', (int) $campoOrigen->id)
                ->where('proyecto_id', (int) $entidad->proyecto_id)
                ->where('ambito', 'entidad_configurable')
                ->where('ambito_id', (int) $entidad->id)
                ->update($mutables);
        }

        $this->cerrarFormCampo();
        session()->flash('entidades-ok', 'Campo guardado.');
    }

    public function desactivarCampo(int $campoId): void
    {
        $this->autorizar();
        $this->cambiarEstadoCampo($campoId, false);
    }

    public function activarCampo(int $campoId): void
    {
        $this->autorizar();
        $this->cambiarEstadoCampo($campoId, true);
    }

    /**
     * Los dos eran `UPDATE campos_personalizados SET activo = ? WHERE id = ?`
     * desnudos: apagaban o encendían la definición de cualquier cliente.
     */
    private function cambiarEstadoCampo(int $campoId, bool $activo): void
    {
        $campo = $this->campoDeLaEntidadAbierta($campoId);
        if ($campo === null) {
            abort(403, 'Ese campo no pertenece a la entidad abierta.');
        }

        // El `where` repite el alcance que `campoDeLaEntidadAbierta()` ya
        // comprobó. Es redundante y así se queda: la sentencia que escribe lleva
        // su propio filtro de dueño, para que no dependa de que quien la llame
        // haya validado antes.
        DB::table('campos_personalizados')
            ->where('id', (int) $campo->id)
            ->where('proyecto_id', (int) $campo->proyecto_id)
            ->where('ambito', 'entidad_configurable')
            ->where('ambito_id', (int) $campo->ambito_id)
            ->update(['activo' => $activo]);
    }

    public function render(): View
    {
        $enAlcance = $this->proyectosEnAlcance();

        // El selector traía `DB::table('proyectos')->get()`: el catálogo de
        // clientes completo de la instalación.
        $proyectos = $enAlcance === []
            ? collect()
            : DB::table('proyectos')
                ->whereIn('id', $enAlcance)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']);

        $proyectoValido = $this->proyectoEnAlcance($this->proyectoSeleccionadoId);

        $entidades = ! $proyectoValido
            ? collect()
            : DB::table('entidades_configurables as e')
                ->leftJoin('carteras as ca', 'ca.id', '=', 'e.cartera_id')
                ->where('e.proyecto_id', $this->proyectoSeleccionadoId)
                ->whereNull('e.eliminada_en')
                ->select(['e.*', 'ca.nombre as cartera_nombre'])
                ->orderBy('e.nombre')
                ->get();

        // El panel de campos filtraba por `ambito_id` sin `proyecto_id`: con un id
        // de entidad ajena enseñaba el esquema de datos del otro cliente.
        $campos = collect();
        $entidadAbierta = $this->entidadConCamposAbiertosId === null
            ? null
            : $this->entidadDelProyectoSeleccionado($this->entidadConCamposAbiertosId);

        if ($entidadAbierta !== null) {
            $campos = DB::table('campos_personalizados')
                ->where('proyecto_id', (int) $entidadAbierta->proyecto_id)
                ->where('ambito', 'entidad_configurable')
                ->where('ambito_id', (int) $entidadAbierta->id)
                ->orderBy('orden')->orderBy('codigo')
                ->get();
        }

        $carterasDelProyecto = ! $proyectoValido
            ? collect()
            : DB::table('carteras')
                ->where('proyecto_id', $this->proyectoSeleccionadoId)
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']);

        return view('entidades::admin.admin-entidades-configurables', [
            'proyectos' => $proyectos,
            'entidades' => $entidades,
            'campos' => $campos,
            'carterasDelProyecto' => $carterasDelProyecto,
        ]);
    }
}
