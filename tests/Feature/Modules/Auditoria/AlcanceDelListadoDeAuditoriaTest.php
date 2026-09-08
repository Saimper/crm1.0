<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auditoria;

use App\Models\User;
use App\Modules\Auditoria\Infrastructure\Http\Livewire\ListadoAuditoria;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * Las cuatro garantías del listado de auditoría en modo global
 * (`/admin/auditoria`), la pantalla donde un cliente comprueba quién tocó sus
 * datos y donde por tanto no puede aparecer ni una fila de otro:
 *
 *  1. el admin de un mandante ve lo suyo y sólo lo suyo;
 *  2. en modo proyecto el recorte se gana con `auditoria.ver` EN ESE proyecto,
 *     no con haber pasado por un middleware;
 *  3. quien alcanza más de un cliente puede acotar la pantalla a uno;
 *  4. un evento sin proyecto —toda acción administrativa— sigue siendo de su
 *     dueño, y de nadie más.
 *
 * La cuarta es la que tiene dos filos: es fácil devolvérsela a su dueño
 * enseñándosela a todo el mundo, y entonces el arreglo es la fuga. Por eso
 * aquí el evento huérfano del cliente ajeno se afirma explícitamente ausente.
 */
final class AlcanceDelListadoDeAuditoriaTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_el_admin_de_un_mandante_solo_ve_los_eventos_de_su_cliente(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $propio = $this->insertarEventoDeProyecto($a, 'casos');
        $ajeno = $this->insertarEventoDeProyecto($b, 'casos');

        $ids = $this->idsDePagina($this->listadoGlobalComo($a['adminMandante']));

        // El propio primero: contra un listado vacío, el assertNotContains de
        // abajo pasaría sin haber demostrado nada.
        self::assertContains($propio, $ids, 'El admin del mandante A no ve ni los eventos de su propio proyecto.');
        self::assertNotContains($ajeno, $ids, '/admin/auditoria expone a A un evento del proyecto de B.');
    }

    public function test_en_modo_proyecto_el_listado_exige_el_permiso_en_ese_proyecto(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $ajeno = $this->insertarEventoDeProyecto($b, 'casos');

        // Defensa en profundidad: hoy el binding sólo lo publica el middleware,
        // que valida el acceso antes de bindear. Pero servir
        // `where proyecto_id = <activo>` sin preguntar por el permiso deja el
        // historial completo de un cliente colgando de un módulo ajeno.
        $this->activarProyecto($b['proyecto']);

        $componente = Livewire::actingAs($a['supervisor'])->test(ListadoAuditoria::class);

        self::assertNotContains(
            $ajeno,
            $this->idsDePagina($componente),
            'El listado sirve la auditoría del proyecto activo a quien no tiene `auditoria.ver` en él.'
        );
    }

    public function test_quien_alcanza_varios_clientes_puede_acotar_la_pantalla_a_uno(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $deA = $this->insertarEventoDeProyecto($a, 'casos');
        $deB = $this->insertarEventoDeProyecto($b, 'casos');

        $componente = Livewire::actingAs($this->crearAdminGlobal())
            ->test(ListadoAuditoria::class)
            ->set('mandanteId', (int) $a['mandante']->id);

        $ids = $this->idsDePagina($componente);

        self::assertContains($deA, $ids, 'Acotando al mandante A no aparecen sus propios eventos.');
        self::assertNotContains($deB, $ids, 'Acotando al mandante A siguen apareciendo eventos de B.');
    }

    public function test_el_filtro_de_mandante_no_amplia_lo_que_el_usuario_ya_alcanzaba(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $ajeno = $this->insertarEventoDeProyecto($b, 'casos');

        // `mandanteId` viaja sin #[Locked] porque es un filtro de pantalla. Lo
        // que lo hace seguro es que se INTERSECA con el alcance del usuario:
        // pedir el cliente ajeno tiene que dejar la pantalla vacía, no abrirla.
        $componente = Livewire::actingAs($a['adminMandante'])
            ->test(ListadoAuditoria::class)
            ->set('mandanteId', (int) $b['mandante']->id);

        self::assertNotContains(
            $ajeno,
            $this->idsDePagina($componente),
            'Fijar `mandanteId` al cliente ajeno amplía el alcance en vez de estrecharlo.'
        );
    }

    public function test_un_evento_sin_proyecto_sigue_siendo_de_su_cliente_y_de_nadie_mas(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        // Una acción de plataforma (alta de usuario, cambio de rol): no cuelga
        // de ningún proyecto, así que un recorte por `proyecto_id` la descarta
        // y su dueño pierde el rastro de lo que se hizo en su propia casa.
        $propio = $this->insertarEventoDePlataforma($a);
        $ajeno = $this->insertarEventoDePlataforma($b);

        $ids = $this->idsDePagina($this->listadoGlobalComo($a['adminMandante']));

        self::assertContains($propio, $ids, 'El admin de A pierde el rastro de su propia acción administrativa.');
        self::assertNotContains($ajeno, $ids, 'Devolver los huérfanos a su dueño se los enseñó también a los demás.');
    }

    // ---------------------------------------------------------------------

    private function listadoGlobalComo(User $usuario): Testable
    {
        return Livewire::actingAs($usuario)->test(ListadoAuditoria::class);
    }

    /**
     * Los ids de la página que se rindió.
     *
     * Va por `items()` a propósito: `registros` es un paginador, y un paginador
     * contesta a `toArray()` con el sobre —`current_page`, `data`, `links`— y
     * no con las filas. Quien lo vuelca directo a una colección acaba leyendo
     * la metadata y no la página.
     *
     * @return list<int>
     */
    private function idsDePagina(Testable $componente): array
    {
        $registros = $componente->viewData('registros');
        self::assertInstanceOf(LengthAwarePaginator::class, $registros);

        return $this->idsDe($registros->items());
    }

    /**
     * Un evento colgado del proyecto del mandante dado.
     *
     * @param  array<string, mixed>  $lado
     */
    private function insertarEventoDeProyecto(array $lado, string $entidadTipo): int
    {
        return (int) DB::table('auditorias')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => (int) $lado['proyecto']->id,
            'mandante_id' => (int) $lado['mandante']->id,
            'usuario_id' => (int) $lado['gestor']->id,
            'entidad_tipo' => $entidadTipo,
            'entidad_id' => (int) $lado['casoId'],
            'evento' => 'actualizado',
            'ip' => '10.0.0.1',
            'creada_en' => Carbon::now(),
        ]);
    }

    /**
     * Un evento administrativo escrito a pelo, como los que dejan las pantallas
     * que aún no pasan por el observer: sin proyecto y sin mandante. Su única
     * atribución posible es quién lo hizo.
     *
     * @param  array<string, mixed>  $lado
     */
    private function insertarEventoDePlataforma(array $lado): int
    {
        return (int) DB::table('auditorias')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => null,
            'mandante_id' => null,
            'usuario_id' => (int) $lado['adminMandante']->id,
            'entidad_tipo' => 'users',
            'entidad_id' => (int) $lado['gestor']->id,
            'evento' => 'actualizado',
            'creada_en' => Carbon::now(),
        ]);
    }
}
