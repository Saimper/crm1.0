<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\AdminMandantes;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El `admin.global` de /admin/mandantes protege la página, no el commit.
 *
 * Cada acción de Livewire es un POST aparte a /livewire/update que reentra en el
 * componente sin volver a pasar por el middleware de la ruta, y con las
 * propiedades que mande el cliente. Sin guarda en el método, una sesión que
 * montara el componente cuando sí tenía derecho —o al que se le revocó
 * ADMIN_GLOBAL con la pestaña abierta— seguía creando, editando y desactivando
 * mandantes. Y el mandante es la raíz del árbol de tenancy: desactivar uno tumba
 * la operación de todos sus proyectos.
 *
 * Por eso los tests montan como ADMIN_GLOBAL y cambian de usuario ANTES de la
 * llamada: reproducen el commit que llega después, que es justo lo que el
 * middleware de la ruta ya no mira.
 *
 * Aquí no vale `autorizarEn()` del trait: esta pantalla no tiene proyecto activo
 * contra el que evaluar un permiso, y el mandante no pertenece a ningún proyecto,
 * así que tampoco hay pertenencia que comprobar. La guarda es el rol global.
 */
final class AdminMandantesAutorizacionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_un_supervisor_no_puede_crear_un_mandante(): void
    {
        $this->actingAs($this->crearAdminGlobal());

        $componente = Livewire::test(AdminMandantes::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'BANCO_COLADO')
            ->set('form.nombre', 'Banco Colado S.A.');

        $this->actingAs($this->supervisorDeAlgunProyecto());

        $componente->call('guardar')->assertForbidden();

        $this->assertDatabaseMissing('mandantes', ['codigo' => 'BANCO_COLADO']);
    }

    public function test_un_supervisor_no_puede_editar_un_mandante_existente(): void
    {
        $mandante = $this->crearMandante('BANCO_X', 'Banco X S.A.');
        $this->actingAs($this->crearAdminGlobal());

        $componente = Livewire::test(AdminMandantes::class)
            ->call('abrirFormEditar', (int) $mandante->id)
            ->set('form.nombre', 'Banco Reescrito');

        $this->actingAs($this->supervisorDeAlgunProyecto());

        $componente->call('guardar')->assertForbidden();

        $this->assertSame(
            'Banco X S.A.',
            DB::table('mandantes')->where('id', $mandante->id)->value('nombre')
        );
    }

    public function test_un_supervisor_no_puede_desactivar_un_mandante(): void
    {
        $mandante = $this->crearMandante('BANCO_X', 'Banco X S.A.');
        $this->actingAs($this->crearAdminGlobal());

        $componente = Livewire::test(AdminMandantes::class);

        $this->actingAs($this->supervisorDeAlgunProyecto());

        $componente->call('desactivar', (int) $mandante->id)->assertForbidden();

        $this->assertTrue(
            (bool) DB::table('mandantes')->where('id', $mandante->id)->value('activo'),
            'Desactivar el mandante tumba la operación de todos sus proyectos.'
        );
    }

    public function test_un_supervisor_no_puede_reactivar_un_mandante(): void
    {
        $mandante = $this->crearMandante('BANCO_X', 'Banco X S.A.');
        DB::table('mandantes')->where('id', $mandante->id)->update(['activo' => false]);

        $this->actingAs($this->crearAdminGlobal());
        $componente = Livewire::test(AdminMandantes::class);

        $this->actingAs($this->supervisorDeAlgunProyecto());

        $componente->call('activar', (int) $mandante->id)->assertForbidden();

        $this->assertFalse((bool) DB::table('mandantes')->where('id', $mandante->id)->value('activo'));
    }

    public function test_un_supervisor_no_puede_leer_los_datos_de_un_mandante(): void
    {
        $mandante = $this->crearMandante('BANCO_AJENO', 'Banco Ajeno S.A.');

        $this->actingAs($this->crearAdminGlobal());
        $componente = Livewire::test(AdminMandantes::class);

        $this->actingAs($this->supervisorDeAlgunProyecto());

        // abrirFormEditar no escribe, pero vuelca código, nombre y documento del
        // mandante en una propiedad pública: es una fuga de datos de otro cliente
        // del BPO y se cierra igual que una escritura.
        $componente->call('abrirFormEditar', (int) $mandante->id)
            ->assertForbidden()
            ->assertSet('form.nombre', '');
    }

    public function test_quien_no_es_admin_global_ni_llega_a_montar_el_componente(): void
    {
        $this->actingAs($this->supervisorDeAlgunProyecto());

        // El listado es el inventario completo de clientes del BPO, y se repinta
        // en cada commit: la guarda va también en render().
        Livewire::test(AdminMandantes::class)->assertForbidden();
    }

    public function test_el_mandante_en_edicion_no_se_puede_reapuntar_desde_el_cliente(): void
    {
        $abierto = $this->crearMandante('BANCO_ABIERTO', 'Banco Abierto');
        $otro = $this->crearMandante('BANCO_OTRO', 'Banco Otro');

        $this->actingAs($this->crearAdminGlobal());

        // #[Locked]: sin esto, el cliente cambiaba editandoId entre abrir el
        // formulario y guardar, y el UPDATE caía sobre otro mandante.
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(AdminMandantes::class)
            ->call('abrirFormEditar', (int) $abierto->id)
            ->set('editandoId', (int) $otro->id);
    }

    public function test_el_admin_global_si_crea_edita_y_desactiva(): void
    {
        $this->actingAs($this->crearAdminGlobal());

        // (b) el camino legítimo entero, para que el endurecimiento no rompa la operación.
        Livewire::test(AdminMandantes::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'BANCO_LEGIT')
            ->set('form.nombre', 'Banco Legítimo S.A.')
            ->call('guardar')
            ->assertHasNoErrors();

        $id = (int) DB::table('mandantes')->where('codigo', 'BANCO_LEGIT')->value('id');
        $this->assertGreaterThan(0, $id);

        Livewire::test(AdminMandantes::class)
            ->call('abrirFormEditar', $id)
            ->assertSet('form.nombre', 'Banco Legítimo S.A.')
            ->set('form.nombre', 'Banco Legítimo ACTUALIZADO')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(
            'Banco Legítimo ACTUALIZADO',
            DB::table('mandantes')->where('id', $id)->value('nombre')
        );

        Livewire::test(AdminMandantes::class)->call('desactivar', $id);
        $this->assertFalse((bool) DB::table('mandantes')->where('id', $id)->value('activo'));

        Livewire::test(AdminMandantes::class)->call('activar', $id);
        $this->assertTrue((bool) DB::table('mandantes')->where('id', $id)->value('activo'));
    }

    /** El rol más alto por proyecto, que no tiene nada que hacer en la tabla de mandantes. */
    private function supervisorDeAlgunProyecto(): User
    {
        return $this->crearSupervisor($this->crearProyectoCobranza());
    }
}
