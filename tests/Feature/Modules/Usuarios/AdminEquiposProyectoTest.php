<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use App\Models\User;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminEquiposProyecto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class AdminEquiposProyectoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_accede_ruta_equipos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor)
            ->get(route('proyectos.equipos', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);
    }

    public function test_gestor_recibe_403_en_ruta_equipos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.equipos', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(403);
    }

    public function test_supervisor_crea_equipo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        Livewire::test(AdminEquiposProyecto::class)
            ->call('abrirFormCrear')
            ->set('formCodigo', 'EQ_TEST')
            ->set('formNombre', 'Equipo de prueba')
            ->call('guardarEquipo')
            ->assertHasNoErrors()
            ->assertSet('formEquipoVisible', false);

        $this->assertDatabaseHas('equipos', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EQ_TEST',
            'nombre' => 'Equipo de prueba',
            'activo' => true,
        ]);
    }

    public function test_codigo_duplicado_en_mismo_proyecto_no_produce_dos_equipos_con_el_mismo_codigo(): void
    {
        // Intención original: dos equipos del mismo proyecto no comparten código.
        // El rechazo ya no es un error de validación: desde el batch B6
        // (GeneradorCodigo::resolverConflicto, política UI documentada) el
        // componente sufija `_2` en vez de devolver el form con errores.
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $c = Livewire::test(AdminEquiposProyecto::class);
        $c->call('abrirFormCrear')
            ->set('formCodigo', 'DUP')
            ->set('formNombre', 'Primero')
            ->call('guardarEquipo')
            ->assertHasNoErrors();

        $c->call('abrirFormCrear')
            ->set('formCodigo', 'DUP')
            ->set('formNombre', 'Segundo')
            ->call('guardarEquipo');

        $codigos = DB::table('equipos')
            ->where('proyecto_id', $proyecto->id)
            ->orderBy('id')
            ->pluck('codigo')
            ->all();

        $this->assertSame(['DUP', 'DUP_2'], $codigos);
        $this->assertSame(count($codigos), count(array_unique($codigos)));
    }

    public function test_agregar_y_quitar_miembro(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $gestor = $this->crearGestor($proyecto);

        $c = Livewire::test(AdminEquiposProyecto::class)
            ->call('abrirFormCrear')
            ->set('formCodigo', 'EQ_MIEMBROS')
            ->set('formNombre', 'Con miembros')
            ->call('guardarEquipo');

        $equipoId = $this->equipoIdDe($proyecto, 'EQ_MIEMBROS');

        $c->call('gestionarMiembros', $equipoId)
            ->set('buscarEmail', $gestor->email)
            ->call('buscarUsuario')
            ->assertHasNoErrors()
            ->call('agregarMiembro');

        $this->assertDatabaseHas('equipo_usuario', [
            'equipo_id' => $equipoId,
            'usuario_id' => $gestor->id,
            'proyecto_id' => $proyecto->id,
        ]);

        $c->call('quitarMiembro', $gestor->id);

        $this->assertDatabaseMissing('equipo_usuario', [
            'equipo_id' => $equipoId,
            'usuario_id' => $gestor->id,
        ]);
    }

    public function test_no_puede_agregar_admin_global_como_miembro(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $admin = $this->crearAdminGlobal();

        Livewire::test(AdminEquiposProyecto::class)
            ->call('abrirFormCrear')
            ->set('formCodigo', 'EQ_ADMIN')
            ->set('formNombre', 'Test admin')
            ->call('guardarEquipo');

        $equipoId = $this->equipoIdDe($proyecto, 'EQ_ADMIN');

        Livewire::test(AdminEquiposProyecto::class)
            ->call('gestionarMiembros', $equipoId)
            ->set('buscarEmail', $admin->email)
            ->call('buscarUsuario')
            ->assertHasErrors(['buscarEmail']);
    }

    public function test_no_puede_agregar_usuario_sin_rol_en_el_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        // Usuario sin rol en este proyecto
        $extra = User::query()->create([
            'name' => 'Suelto', 'email' => 'suelto.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);

        Livewire::test(AdminEquiposProyecto::class)
            ->call('abrirFormCrear')
            ->set('formCodigo', 'EQ_NULL')
            ->set('formNombre', 'Test sin-rol')
            ->call('guardarEquipo');

        $equipoId = $this->equipoIdDe($proyecto, 'EQ_NULL');

        Livewire::test(AdminEquiposProyecto::class)
            ->call('gestionarMiembros', $equipoId)
            ->set('buscarEmail', $extra->email)
            ->call('buscarUsuario')
            ->assertHasErrors(['buscarEmail']);
    }

    public function test_desactivar_y_activar_equipo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        Livewire::test(AdminEquiposProyecto::class)
            ->call('abrirFormCrear')
            ->set('formCodigo', 'EQ_TOGGLE')
            ->set('formNombre', 'Toggle')
            ->call('guardarEquipo');

        $id = $this->equipoIdDe($proyecto, 'EQ_TOGGLE');

        Livewire::test(AdminEquiposProyecto::class)->call('desactivar', $id);
        $this->assertFalse((bool) DB::table('equipos')->where('id', $id)->value('activo'));

        Livewire::test(AdminEquiposProyecto::class)->call('activar', $id);
        $this->assertTrue((bool) DB::table('equipos')->where('id', $id)->value('activo'));
    }

    private function equipoIdDe(stdClass $proyecto, string $codigo): int
    {
        return (int) DB::table('equipos')
            ->where('proyecto_id', $proyecto->id)
            ->where('codigo', $codigo)
            ->value('id');
    }
}
