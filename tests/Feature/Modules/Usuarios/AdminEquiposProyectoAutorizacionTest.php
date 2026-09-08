<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use App\Models\User;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminEquiposProyecto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El `can:usuarios.gestionar` de /proyectos/{id}/equipos protege la PÁGINA. Cada
 * acción del componente es un POST aparte a /livewire/update que no vuelve a
 * pasar por el middleware, así que `guardarEquipo`, `activar`, `desactivar`,
 * `agregarMiembro` y `quitarMiembro` escribían sin comprobar ni permiso ni
 * pertenencia, y los ids de equipo y de usuario viajaban en propiedades que el
 * cliente podía reapuntar desde la consola.
 */
final class AdminEquiposProyectoAutorizacionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_gestor_no_puede_crear_un_equipo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearGestor($proyecto));

        Livewire::test(AdminEquiposProyecto::class)
            ->set('formCodigo', 'EQ_GESTOR')
            ->set('formNombre', 'Intento del gestor')
            ->call('guardarEquipo')
            ->assertForbidden();

        $this->assertDatabaseMissing('equipos', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EQ_GESTOR',
        ]);
    }

    public function test_auditor_no_puede_desactivar_un_equipo(): void
    {
        // El AUDITOR tiene `equipos.ver` y ninguno de escritura: puede mirar la
        // composición del equipo, no tocarla.
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $equipoId = $this->crearEquipoEn($proyecto);

        $this->actingAs($this->crearAuditor($proyecto));

        Livewire::test(AdminEquiposProyecto::class)
            ->call('desactivar', $equipoId)
            ->assertForbidden();

        $this->assertTrue(
            (bool) DB::table('equipos')->where('id', $equipoId)->value('activo'),
            'El AUDITOR es de sólo lectura: el equipo no debe haberse desactivado.'
        );
    }

    public function test_auditor_no_puede_quitar_un_miembro(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $equipoId = $this->crearEquipoEn($proyecto);
        $gestor = $this->crearGestor($proyecto);
        $this->meterEnEquipo($proyecto, $equipoId, (int) $gestor->id);

        $this->actingAs($this->crearAuditor($proyecto));

        Livewire::test(AdminEquiposProyecto::class)
            ->call('gestionarMiembros', $equipoId)   // ver sí puede
            ->call('quitarMiembro', $gestor->id)     // quitar no
            ->assertForbidden();

        $this->assertDatabaseHas('equipo_usuario', [
            'equipo_id' => $equipoId,
            'usuario_id' => $gestor->id,
        ]);
    }

    public function test_auditor_no_puede_agregar_un_miembro(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $equipoId = $this->crearEquipoEn($proyecto);
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($this->crearAuditor($proyecto));

        Livewire::test(AdminEquiposProyecto::class)
            ->call('gestionarMiembros', $equipoId)
            ->set('buscarEmail', $gestor->email)
            ->call('buscarUsuario')
            ->assertForbidden();

        $this->assertDatabaseMissing('equipo_usuario', [
            'equipo_id' => $equipoId,
            'usuario_id' => $gestor->id,
        ]);
    }

    public function test_supervisor_si_hace_el_ciclo_completo(): void
    {
        // El camino legítimo entero: crear, editar, miembros y desactivar. Un
        // endurecimiento que rompa esto no sirve de nada.
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $gestor = $this->crearGestor($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $c = Livewire::test(AdminEquiposProyecto::class)
            ->call('abrirFormCrear')
            ->set('formCodigo', 'EQ_CICLO')
            ->set('formNombre', 'Ciclo completo')
            ->call('guardarEquipo')
            ->assertHasNoErrors();

        $equipoId = (int) DB::table('equipos')
            ->where('proyecto_id', $proyecto->id)
            ->where('codigo', 'EQ_CICLO')
            ->value('id');
        $this->assertNotSame(0, $equipoId);

        $c->call('abrirFormEditar', $equipoId)
            ->set('formNombre', 'Ciclo renombrado')
            ->call('guardarEquipo')
            ->assertHasNoErrors();

        $this->assertSame(
            'Ciclo renombrado',
            DB::table('equipos')->where('id', $equipoId)->value('nombre')
        );

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

        $c->call('desactivar', $equipoId);
        $this->assertFalse((bool) DB::table('equipos')->where('id', $equipoId)->value('activo'));

        $c->call('activar', $equipoId);
        $this->assertTrue((bool) DB::table('equipos')->where('id', $equipoId)->value('activo'));
    }

    public function test_no_se_puede_desactivar_el_equipo_de_otro_mandante(): void
    {
        $propio = $this->crearProyectoCobranza();
        $ajeno = $this->crearProyectoCobranza();   // crearProyecto le hace su propio mandante
        $equipoAjeno = $this->crearEquipoEn($ajeno);

        $this->activarProyecto($propio);
        $this->actingAs($this->crearSupervisor($propio));

        // Tiene `equipos.administrar` EN SU PROYECTO. Si sólo se mirara el
        // permiso, lo arrastraría al ajeno.
        Livewire::test(AdminEquiposProyecto::class)
            ->call('desactivar', $equipoAjeno)
            ->assertNotFound();

        $this->assertTrue(
            (bool) DB::table('equipos')->where('id', $equipoAjeno)->value('activo'),
            'FUGA: se desactivó el equipo de otro mandante.'
        );
    }

    public function test_no_se_puede_abrir_ni_gestionar_el_equipo_de_otro_mandante(): void
    {
        $propio = $this->crearProyectoCobranza();
        $ajeno = $this->crearProyectoCobranza();
        $equipoAjeno = $this->crearEquipoEn($ajeno);

        $this->activarProyecto($propio);
        $this->actingAs($this->crearSupervisor($propio));

        Livewire::test(AdminEquiposProyecto::class)
            ->call('abrirFormEditar', $equipoAjeno)
            ->assertNotFound();

        Livewire::test(AdminEquiposProyecto::class)
            ->call('gestionarMiembros', $equipoAjeno)
            ->assertNotFound();
    }

    public function test_el_equipo_en_edicion_no_se_puede_reapuntar_desde_el_cliente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $equipoId = $this->crearEquipoEn($proyecto);

        $this->actingAs($this->crearSupervisor($proyecto));

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(AdminEquiposProyecto::class)->set('equipoEditandoId', $equipoId);
    }

    public function test_el_usuario_a_agregar_no_se_puede_reapuntar_desde_el_cliente(): void
    {
        // `buscarUsuario()` es quien descarta a los ADMIN_GLOBAL y a los que no
        // tienen rol en el proyecto. Si el id fuera escribible desde el cliente,
        // esos dos filtros se saltaban enteros.
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $suelto = User::query()->create([
            'name' => 'Suelto',
            'email' => 'suelto.'.Str::random(6).'@crm.local',
            'password' => 'x',
            'activo' => true,
        ]);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(AdminEquiposProyecto::class)->set('usuarioBuscadoId', $suelto->id);
    }

    private function crearEquipoEn(stdClass $proyecto, ?string $codigo = null): int
    {
        return (int) DB::table('equipos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo ?? 'EQ_'.strtoupper(Str::random(6)),
            'nombre' => 'Equipo de prueba',
            'activo' => true,
        ]);
    }

    private function meterEnEquipo(stdClass $proyecto, int $equipoId, int $usuarioId): void
    {
        DB::table('equipo_usuario')->insert([
            'equipo_id' => $equipoId,
            'usuario_id' => $usuarioId,
            'proyecto_id' => $proyecto->id,
            'activo' => true,
            'creada_en' => Carbon::now(),
        ]);
    }
}
