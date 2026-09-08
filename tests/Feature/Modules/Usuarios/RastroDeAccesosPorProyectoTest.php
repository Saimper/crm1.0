<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use App\Modules\Usuarios\Infrastructure\Http\Livewire\GestionUsuariosProyecto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Dar y quitar acceso a la cartera de un cliente deja constancia.
 *
 * Es la pantalla que el supervisor usa a diario, y era la única de las tres que
 * escriben pivotes de acceso que no anotaba nada: `/admin/usuarios` sí lo hace
 * desde que las acciones administrativas dejaron rastro, pero el camino corto
 * —el del supervisor sobre su propio proyecto— quedaba mudo.
 */
final class RastroDeAccesosPorProyectoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_asignar_un_rol_deja_rastro_con_el_codigo_del_rol_y_sus_carteras(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $supervisor = $this->crearSupervisor($proyecto);
        // Una cuenta del mismo cliente: el buscador se niega a traer a alguien de
        // otro mandante, y con razón.
        $nuevo = $this->crearGestor($this->crearProyectoCobranza($this->mandanteDe($proyecto)));

        $rolId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        $this->activarProyecto($proyecto);
        Livewire::actingAs($supervisor)
            ->test(GestionUsuariosProyecto::class)
            ->set('buscarEmail', $nuevo->email)
            ->call('buscarUsuario')
            ->assertHasNoErrors()
            ->set('rolAsignarValor', 'base:'.$rolId)
            ->set('carterasSeleccionadas', [(int) $cartera->id])
            ->call('asignar')
            ->assertHasNoErrors();

        $evento = DB::table('auditorias')
            ->where('entidad_tipo', 'usuario_proyecto_rol')
            ->where('entidad_id', $nuevo->id)
            ->first();

        $this->assertNotNull($evento, 'Dar acceso a la cartera de un cliente no dejó rastro.');
        $this->assertSame('creado', (string) $evento->evento);
        $this->assertSame((int) $proyecto->id, (int) $evento->proyecto_id);
        $this->assertSame((int) $supervisor->id, (int) $evento->usuario_id, 'El rastro dice quién lo hizo.');

        $datos = json_decode((string) $evento->datos_despues, true);
        $this->assertSame('GESTOR', $datos['rol_codigo']);
        $this->assertSame([$cartera->codigo], $datos['carteras'], 'Los códigos, que es lo que una persona reconoce.');
    }

    public function test_quitar_un_rol_deja_rastro_y_un_clic_en_vacio_no(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $gestor = $this->crearGestor($proyecto);
        $rolId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        $this->activarProyecto($proyecto);
        $componente = Livewire::actingAs($supervisor)->test(GestionUsuariosProyecto::class);

        $componente->call('quitar', (int) $gestor->id, $rolId);

        $bajas = DB::table('auditorias')
            ->where('entidad_tipo', 'usuario_proyecto_rol')
            ->where('evento', 'eliminado')
            ->where('entidad_id', $gestor->id)
            ->count();
        $this->assertSame(1, $bajas);

        // El mismo clic otra vez: ya no hay nada que quitar, así que no hay
        // evento nuevo. Un registro lleno de bajas que no ocurrieron es peor
        // que no tenerlo.
        $componente->call('quitar', (int) $gestor->id, $rolId);

        $this->assertSame($bajas, DB::table('auditorias')
            ->where('entidad_tipo', 'usuario_proyecto_rol')
            ->where('evento', 'eliminado')
            ->where('entidad_id', $gestor->id)
            ->count());
    }

    /**
     * Una cuenta anterior al SSO no tiene origen escrito, y el evento tiene que
     * acabar en el cliente donde esa cuenta trabaja, no en el de la pantalla
     * que se tenga abierta: si no, lo ve el administrador equivocado.
     */
    public function test_una_cuenta_sin_origen_se_atribuye_al_cliente_donde_tiene_acceso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $gestor = $this->crearGestor($proyecto);

        DB::table('users')->where('id', $gestor->id)->update(['mandante_origen_id' => null]);

        $rolId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        $this->activarProyecto($proyecto);
        Livewire::actingAs($supervisor)
            ->test(GestionUsuariosProyecto::class)
            ->call('quitar', (int) $gestor->id, $rolId);

        $evento = DB::table('auditorias')
            ->where('entidad_tipo', 'usuario_proyecto_rol')
            ->where('entidad_id', $gestor->id)
            ->first();

        $this->assertNotNull($evento);
        $this->assertSame(
            (int) $proyecto->mandante_id,
            (int) $evento->mandante_id,
            'El evento tiene que ser del cliente cuyo acceso se movió.'
        );
    }

    private function mandanteDe(object $proyecto): object
    {
        /** @var object $mandante */
        $mandante = DB::table('mandantes')->where('id', $proyecto->mandante_id)->first();

        return $mandante;
    }
}
