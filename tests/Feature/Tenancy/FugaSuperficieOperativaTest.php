<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Casos\Infrastructure\Http\Livewire\ListadoCasos;
use App\Modules\Casos\Infrastructure\Persistence\Models\CasoModel;
use App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel;
use App\Modules\Tenancy\Domain\Exceptions\ConsultaSinContextoDeTenant;
use App\Modules\Tenancy\Domain\Exceptions\EscrituraFueraDelProyectoActivo;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\SelectorProyecto;
use App\Modules\Tenancy\Infrastructure\Http\Middleware\ResolverProyectoActivo;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * Red de seguridad de la superficie OPERATIVA (Fase 0).
 *
 * Aquí vive lo único que el CRM sí construyó con aislamiento real: las rutas
 * `/proyectos/{proyecto_id}/...` pasan por el middleware `proyecto.activo`
 * (`ResolverProyectoActivo`), que valida acceso y publica el binding
 * `tenancy.proyecto_activo`. De ese binding cuelgan dos cosas distintas:
 *
 *   a) el Global Scope `ScopeProyectoActivo` (trait `PerteneceAProyecto`),
 *      para los modelos Eloquent scoped;
 *   b) las pantallas que consultan con `DB::table(...)` y filtran a mano por
 *      `app('tenancy.proyecto_activo')->id` — `ListadoCasos` y `VistaDeTrabajo`
 *      son de éstas: el scope no las toca, las protege el binding.
 *
 * La mayoría de estos tests están en verde y su valor es fijarlo: cuando la
 * Fase 2 introduzca el contexto de MANDANTE, esto tiene que seguir igual de
 * cerrado.
 *
 * Pero el mecanismo tiene una condición de diseño que lo vuelve frágil, y de
 * ahí salen los tests rojos de este fichero:
 *
 *   FALLO ABIERTO (fail-open). `ScopeProyectoActivo::apply()` empieza con
 *   `if (! app()->bound('tenancy.proyecto_activo')) { return; }`. Sin binding
 *   el scope no añade ningún WHERE: la misma consulta que dentro de una
 *   pantalla devuelve un proyecto, fuera devuelve TODOS los mandantes.
 *   El aislamiento no es una propiedad del modelo, es una propiedad de haber
 *   pasado por un middleware HTTP concreto.
 *
 *   Consecuencias que se prueban abajo:
 *     1. Un job de cola o un comando artisan nunca ejecutan ese middleware
 *        (por eso `app/` está sembrado de 73 llamadas defensivas a
 *        `->sinScopeProyecto()`: el autor sabía que el scope no aplica ahí).
 *     2. `ResolverProyectoActivo` deja pasar SIN abortar y SIN bindear
 *        cualquier request de Livewire cuyo proyecto no se pueda resolver o
 *        al que el usuario no tenga acceso (rama `esRequestLivewire`). El
 *        Referer es un header del cliente: apuntándolo a un proyecto ajeno
 *        se obtiene una petición autenticada corriendo sin ningún scope.
 *     3. El scope solo filtra LECTURAS. Con el proyecto de A activo se puede
 *        insertar una fila en el proyecto de B sin que nada se queje.
 *
 * Los tests marcados como CARACTERIZACIÓN afirman la fuga tal como es hoy
 * (pasan en verde a propósito): son el retrato del fallo abierto. Cuando la
 * Fase 2 lo cierre, esos tienen que invertirse o borrarse — y los marcados
 * ROJO ESPERADO se pondrán verdes solos, sin tocarlos.
 */
final class FugaSuperficieOperativaTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ---------------------------------------------------------------
    // HTTP: /proyectos/{id}/... — el middleware `proyecto.activo`
    // ---------------------------------------------------------------

    public function test_gestor_de_a_recibe_403_en_las_rutas_del_proyecto_de_b(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $base = '/proyectos/'.$b['proyecto']->id;

        $rutas = [
            $base,
            $base.'/casos',
            $base.'/personas',
            $base.'/compromisos',
            $base.'/bandeja',
            $base.'/trabajo/'.$b['persona']->public_id,
        ];

        foreach ($rutas as $ruta) {
            $respuesta = $this->actingAs($a['gestor'])->get($ruta);

            $respuesta->assertStatus(403);
            $this->assertNoSeFiltra((string) $respuesta->getContent(), $b, 'GET '.$ruta);
        }
    }

    public function test_gestor_de_a_entra_sin_problema_a_las_rutas_de_su_propio_proyecto(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        // Control del test anterior: los 403 son por mandante ajeno, no porque
        // estas rutas estén rotas para todo el mundo.
        $base = '/proyectos/'.$a['proyecto']->id;

        foreach ([$base, $base.'/casos', $base.'/personas'] as $ruta) {
            $this->actingAs($a['gestor'])->get($ruta)->assertOk();
        }
    }

    public function test_ruta_de_un_proyecto_inexistente_da_404_y_no_200(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $inexistente = ((int) DB::table('proyectos')->max('id')) + 1000;

        $this->actingAs($a['gestor'])
            ->get('/proyectos/'.$inexistente.'/casos')
            ->assertNotFound();
    }

    public function test_persona_de_b_no_se_abre_desde_la_vista_de_trabajo_del_proyecto_de_a(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        // Control: el registro de B existe de verdad. Sin esto, el 404 de abajo
        // podría estar afirmando sólo que el public_id no existe en ningún lado.
        $this->assertDatabaseHas('personas', ['public_id' => $b['persona']->public_id]);

        // public_id ajeno metido a mano dentro de MI propio proyecto: el
        // buscador de la vista de trabajo tiene que no encontrarlo (404, no la
        // ficha de la persona del otro mandante).
        $this->actingAs($a['gestor'])
            ->get('/proyectos/'.$a['proyecto']->id.'/trabajo/'.$b['persona']->public_id)
            ->assertNotFound();
    }

    /**
     * El proyecto sale del parámetro de ruta y de ningún otro sitio. Cuando
     * había respaldo por `Referer`, quien hacía la petición elegía qué datos
     * veía; ahora, sin ese parámetro, el middleware corta y el resto del
     * pipeline no llega a correr.
     */
    public function test_un_request_livewire_con_referer_forjado_no_llega_a_consultar(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $request = Request::create('/livewire/update', 'POST');
        $request->headers->set('referer', 'http://localhost/proyectos/'.$b['proyecto']->id.'/casos');
        $request->setUserResolver(fn (): User => $a['gestor']);

        $llegoAlPipeline = false;

        try {
            (new ResolverProyectoActivo)->handle($request, function () use (&$llegoAlPipeline): Response {
                $llegoAlPipeline = true;

                return new Response('ok');
            });
        } catch (HttpException $e) {
            // Da igual con qué código corte —sin proyecto que resolver es un
            // 404— mientras corte.
            $this->assertContains($e->getStatusCode(), [403, 404]);
        }

        $this->assertFalse($llegoAlPipeline, 'FUGA: el Referer decidió el proyecto y la petición siguió sin contexto.');
    }

    // ---------------------------------------------------------------
    // SelectorProyecto
    // ---------------------------------------------------------------

    public function test_el_selector_no_lista_proyectos_de_otro_mandante(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        // Segundo proyecto en A para esquivar el auto-redirect del mount
        // (con un solo proyecto accesible el selector ni se renderiza).
        $segundoDeA = $this->crearProyectoCobranza($a['mandante']);
        $this->asignarGestorEn($a['gestor'], $segundoDeA);

        $componente = Livewire::actingAs($a['gestor'])->test(SelectorProyecto::class);

        $ids = $this->idsDe($componente->viewData('proyectos'));
        sort($ids);

        $esperados = [(int) $a['proyecto']->id, (int) $segundoDeA->id];
        sort($esperados);

        $this->assertSame($esperados, $ids, 'El selector debe listar exactamente los proyectos propios.');
        $this->assertNotContains((int) $b['proyecto']->id, $ids, 'FUGA: el selector lista un proyecto del otro mandante.');

        $this->assertNoSeFiltra($componente->html(), $b, 'SelectorProyecto (listado)');
    }

    public function test_forzar_el_query_param_mandante_del_ajeno_no_devuelve_nada(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $segundoDeA = $this->crearProyectoCobranza($a['mandante']);
        $this->asignarGestorEn($a['gestor'], $segundoDeA);

        // Control: con MI propio mandante el filtro sí devuelve mis proyectos.
        // Sin esto, el `[]` de abajo podría venir de que el parámetro rompe la
        // consulta para cualquier valor, y el test no probaría aislamiento.
        $propio = Livewire::actingAs($a['gestor'])
            ->withQueryParams(['mandante' => (string) $a['mandante']->id])
            ->test(SelectorProyecto::class);

        $idsPropios = $this->idsDe($propio->viewData('proyectos'));
        sort($idsPropios);

        $esperados = [(int) $a['proyecto']->id, (int) $segundoDeA->id];
        sort($esperados);

        $this->assertSame($esperados, $idsPropios, 'El filtro ?mandante debe funcionar con el mandante propio.');

        // ?mandante={B}: el parámetro es del cliente, así que se prueba como ataque.
        $ajeno = Livewire::actingAs($a['gestor'])
            ->withQueryParams(['mandante' => (string) $b['mandante']->id])
            ->test(SelectorProyecto::class);

        $idsAjenos = $this->idsDe($ajeno->viewData('proyectos'));

        $this->assertSame([], $idsAjenos, 'FUGA: pidiendo el mandante ajeno por query param el selector devolvió proyectos.');
        $this->assertNoSeFiltra($ajeno->html(), $b, 'SelectorProyecto (?mandante ajeno)');
    }

    public function test_el_selector_redirige_al_unico_proyecto_propio_y_nunca_al_ajeno(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        // /dashboard monta el SelectorProyecto; con un único proyecto accesible
        // su mount() redirige directo (F34C). El destino tiene que ser el mío.
        $this->actingAs($a['gestor'])
            ->get('/dashboard')
            ->assertRedirect('/proyectos/'.$a['proyecto']->id);

        // El simétrico: el gestor de B aterriza en B. Los dos juntos descartan
        // que el destino sea un id fijo o el primer proyecto de la tabla.
        $this->actingAs($b['gestor'])
            ->get('/dashboard')
            ->assertRedirect('/proyectos/'.$b['proyecto']->id);
    }

    // ---------------------------------------------------------------
    // Global Scope con proyecto activo
    // ---------------------------------------------------------------

    public function test_con_el_proyecto_de_a_activo_las_consultas_no_devuelven_nada_de_b(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $this->activarProyecto($a['proyecto']);

        $casos = $this->idsDe(CasoModel::query()->get());
        $personas = $this->idsDe(PersonaModel::query()->get());

        $this->assertSame([(int) $a['casoId']], $casos, 'Con A activo solo debe verse el caso de A.');
        $this->assertSame([(int) $a['persona']->id], $personas, 'Con A activo solo debe verse la persona de A.');
        $this->assertNotContains((int) $b['casoId'], $casos);
        $this->assertNotContains((int) $b['persona']->id, $personas);
    }

    /**
     * El listado devuelve colecciones; la búsqueda puntual por id es el otro
     * camino de acceso, y es el que usan las pantallas de edición. Con A
     * activo, pedir el id del caso de B tiene que devolver null, no la fila.
     */
    public function test_con_a_activo_buscar_por_id_un_caso_o_persona_de_b_devuelve_null(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $this->activarProyecto($a['proyecto']);

        $this->assertNotNull(CasoModel::query()->find((int) $a['casoId']), 'Control: el caso propio sí se encuentra.');

        $this->assertNull(
            CasoModel::query()->find((int) $b['casoId']),
            'FUGA: con A activo se recuperó por id el caso de B.'
        );
        $this->assertNull(
            PersonaModel::query()->find((int) $b['persona']->id),
            'FUGA: con A activo se recuperó por id la persona de B.'
        );
    }

    public function test_listado_de_casos_con_a_activo_no_muestra_ni_un_rastro_de_b(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $this->activarProyecto($a['proyecto']);

        $componente = Livewire::actingAs($a['gestor'])->test(ListadoCasos::class);

        $ids = $this->idsDe($componente->viewData('casos')->items());

        $this->assertSame([(int) $a['casoId']], $ids, 'El listado debe traer solo los casos del proyecto activo.');
        $this->assertSame(1, (int) $componente->viewData('totalProyecto'));
        $this->assertNoSeFiltra($componente->html(), $b, 'ListadoCasos');
    }

    /**
     * CARACTERIZACIÓN (verde a propósito).
     *
     * `sinScopeProyecto()` es la puerta de servicio del aislamiento y hoy es
     * incondicional: no comprueba rol, ni permiso, ni mandante. Cualquier
     * llamada — hay 73 en `app/` — devuelve las filas de todos los mandantes.
     * Se fija aquí para que la Fase 2 no la cierre sin darse cuenta y para que
     * quede escrito que el permiso lo tiene que poner quien la llama.
     */
    public function test_caracterizacion_sin_scope_proyecto_es_una_puerta_abierta_incondicional(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $this->activarProyecto($a['proyecto']);

        $ids = $this->idsDe(CasoModel::query()->sinScopeProyecto()->get());

        $this->assertContains((int) $a['casoId'], $ids);
        $this->assertContains(
            (int) $b['casoId'],
            $ids,
            'Si esto falla, `sinScopeProyecto()` dejó de ser incondicional: revisar las 73 llamadas de app/.'
        );
    }

    /**
     * El global scope sólo toca los SELECT, así que esta inserción no pasaba
     * por él y caía dentro del proyecto de B sin que nada la mirara. La corta
     * el guardia de escritura del trait, que revienta en vez de corregir el
     * proyecto en silencio: mover la fila a donde nadie la pidió sería peor
     * que no escribirla.
     */
    public function test_con_a_activo_no_se_puede_escribir_un_caso_en_el_proyecto_de_b(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $this->activarProyecto($a['proyecto']);

        $estadoDeB = (int) DB::table('estados_caso')
            ->where('proyecto_id', $b['proyecto']->id)
            ->value('id');

        $this->expectException(EscrituraFueraDelProyectoActivo::class);

        CasoModel::query()->create([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => (int) $b['proyecto']->id,
            'cartera_id' => (int) $b['cartera']->id,
            'persona_id' => (int) $b['persona']->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $estadoDeB,
            'fecha_ingreso' => '2026-09-02',
        ]);
    }

    // ---------------------------------------------------------------
    // El fallo abierto: sin binding no hay filtro
    // ---------------------------------------------------------------

    /**
     * La garantía que sustituyó al fallo abierto.
     *
     * Este test decía lo contrario: retrataba que sin binding de proyecto el
     * scope hacía `return` y la consulta salía desnuda, con los casos de todos
     * los clientes dentro. No hacía falta ningún ataque; era el comportamiento
     * normal de cualquier código que no viniera de una request HTTP con URL de
     * proyecto — un job, un comando, un listener.
     *
     * Cerrado en la Fase 3: ahora lanza, y esta prueba lo sostiene.
     */
    public function test_sin_contexto_de_proyecto_la_consulta_lanza_en_vez_de_devolverlo_todo(): void
    {
        $this->montarDosMandantes();

        $this->assertFalse(
            $this->app->bound('tenancy.proyecto_activo'),
            'Punto de partida: nadie ejecutó el middleware, no hay proyecto activo.'
        );

        $this->expectException(ConsultaSinContextoDeTenant::class);

        CasoModel::query()->get();
    }

    /**
     * Y el escape sigue existiendo, porque la plataforma lo necesita: los
     * catorce comandos, los cuatro jobs y las tareas de noche recorren todos
     * los proyectos por diseño. La diferencia es que ahora hay que escribirlo.
     */
    public function test_la_consulta_cross_proyecto_sigue_siendo_posible_si_se_declara(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $ids = $this->idsDe(CasoModel::query()->sinScopeProyecto()->get());

        $this->assertContains((int) $a['casoId'], $ids);
        $this->assertContains((int) $b['casoId'], $ids);
    }

    // ---------------------------------------------------------------
    // Fuera de HTTP no hay contexto: jobs y comandos
    // ---------------------------------------------------------------

    /**
     * CARACTERIZACIÓN (verde a propósito).
     *
     * `notificaciones:generar` es código real de producción que corre por
     * scheduler. Aunque el proceso tuviera un proyecto activo, el comando
     * recorre `compromisos` de TODOS los mandantes con `DB::table()` y escribe
     * notificaciones en los dos: la consola no tiene frontera de tenant en
     * ninguna parte.
     */
    public function test_caracterizacion_un_comando_artisan_alcanza_a_los_dos_mandantes(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $compromisoA = $this->crearCompromisoVencido($a);
        $compromisoB = $this->crearCompromisoVencido($b);

        // Ni siquiera con A "activo" el comando se limita a A.
        $this->activarProyecto($a['proyecto']);

        $this->artisan('notificaciones:generar')->assertSuccessful();

        $this->assertDatabaseHas('notificaciones', [
            'proyecto_id' => (int) $a['proyecto']->id,
            'entidad_id' => $compromisoA,
            'tipo' => 'compromiso_vencido',
        ]);
        $this->assertDatabaseHas('notificaciones', [
            'proyecto_id' => (int) $b['proyecto']->id,
            'entidad_id' => $compromisoB,
            'tipo' => 'compromiso_vencido',
        ]);
    }

    /**
     * El estado de un worker de cola o de un comando artisan: hubo contexto,
     * el proceso lo pierde, y desde ahí toda consulta Eloquent queda sin
     * scope. Antes veía los casos de todos los mandantes; ahora lanza, que es
     * lo que hace que las decenas de `->sinScopeProyecto()` repartidas por los
     * repositorios dejen de ser fe y pasen a ser el contrato.
     */
    public function test_un_job_sin_binding_de_proyecto_no_ve_los_casos_de_nadie(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $this->activarProyecto($a['proyecto']);
        $this->assertSame([(int) $a['casoId']], $this->idsDe(CasoModel::query()->get()));

        $this->app->forgetInstance('tenancy.proyecto_activo');
        $this->assertFalse($this->app->bound('tenancy.proyecto_activo'));

        $this->expectException(ConsultaSinContextoDeTenant::class);

        CasoModel::query()->get();
    }

    // ---------------------------------------------------------------
    // Utilidades del fichero
    // ---------------------------------------------------------------

    private function asignarGestorEn(User $usuario, stdClass $proyecto): void
    {
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $usuario->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id'),
            'activo' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $mandante
     */
    private function crearCompromisoVencido(array $mandante): int
    {
        return (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $mandante['proyecto']->id,
            'caso_id' => $mandante['casoId'],
            'tipo_compromiso' => 'promesa_pago',
            'estado' => 'pendiente',
            'fecha_vencimiento' => Carbon::now()->subDays(5)->toDateString(),
            'usuario_id' => $mandante['gestor']->id,
        ]);
    }
}
