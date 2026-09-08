<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Dar de baja a alguien tiene que quitarle el acceso de verdad.
 *
 * `users.activo` se comprobaba en el handshake SSO —con test— y en ningún otro
 * sitio. Por /login se entraba igual, y una sesión ya abierta duraba las ocho
 * horas de `SESSION_LIFETIME`, así que despedir a un agente a media mañana lo
 * dejaba trabajando el resto del turno.
 */
final class UsuarioDesactivadoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_un_usuario_desactivado_no_entra_por_login(): void
    {
        $usuario = User::query()->create([
            'name' => 'Ex Empleado',
            'email' => 'ex@crm.local',
            'password' => Hash::make('secreto123'),
            'activo' => false,
        ]);

        Volt::test('pages.auth.login')
            ->set('form.email', $usuario->email)
            ->set('form.password', 'secreto123')
            ->call('login')
            ->assertHasErrors('form.email');

        $this->assertGuest();
    }

    public function test_el_mensaje_distingue_cuenta_desactivada_de_contrasena_mala(): void
    {
        $usuario = User::query()->create([
            'name' => 'Ex Empleado',
            'email' => 'ex2@crm.local',
            'password' => Hash::make('secreto123'),
            'activo' => false,
        ]);

        // Quien acaba de demostrar que la cuenta es suya no aprende nada nuevo
        // al leer que está desactivada, y con el mensaje genérico llamaría a
        // soporte creyendo que su contraseña dejó de funcionar.
        Volt::test('pages.auth.login')
            ->set('form.email', $usuario->email)
            ->set('form.password', 'secreto123')
            ->call('login')
            ->assertHasErrors(['form.email' => trans('auth.desactivada')]);
    }

    public function test_a_quien_falla_la_contrasena_se_le_sigue_dando_el_mensaje_generico(): void
    {
        User::query()->create([
            'name' => 'Ex Empleado',
            'email' => 'ex3@crm.local',
            'password' => Hash::make('secreto123'),
            'activo' => false,
        ]);

        // Si el mensaje de «desactivada» saliera antes de validar la contraseña,
        // cualquiera podría preguntar por un correo y averiguar si existe.
        Volt::test('pages.auth.login')
            ->set('form.email', 'ex3@crm.local')
            ->set('form.password', 'no-es-la-buena')
            ->call('login')
            ->assertHasErrors(['form.email' => trans('auth.failed')]);
    }

    public function test_un_usuario_activo_sigue_entrando(): void
    {
        $usuario = User::query()->create([
            'name' => 'Gestora',
            'email' => 'activa@crm.local',
            'password' => Hash::make('secreto123'),
            'activo' => true,
        ]);

        Volt::test('pages.auth.login')
            ->set('form.email', $usuario->email)
            ->set('form.password', 'secreto123')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_desactivar_a_alguien_con_la_sesion_abierta_lo_echa_en_la_siguiente_peticion(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        // Sesión de verdad, no `actingAs`: éste inyecta el objeto User en memoria
        // y su `activo` se queda congelado, mientras que en una petición real el
        // guard rehidrata al usuario desde la base en cada request. Probarlo con
        // actingAs mediría el atributo cacheado del test, no el comportamiento.
        DB::table('users')->where('id', $gestor->id)->update(['password' => Hash::make('secreto123')]);

        Volt::test('pages.auth.login')
            ->set('form.email', $gestor->email)
            ->set('form.password', 'secreto123')
            ->call('login')
            ->assertHasNoErrors();

        $this->get(route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]))->assertOk();

        // El supervisor lo da de baja a media mañana.
        DB::table('users')->where('id', $gestor->id)->update(['activo' => false]);

        // En producción cada petición arranca un contenedor nuevo y el guard
        // vuelve a leer al usuario de la base. Dentro de un test el contenedor
        // se reutiliza y el guard conserva el objeto de la petición anterior, así
        // que hay que olvidarlo para que la siguiente llamada se parezca a una
        // petición de verdad; si no, se estaría midiendo la caché del test.
        $this->app['auth']->forgetGuards();

        $this->get(route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_la_baja_no_depende_del_pivote_del_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        DB::table('users')->where('id', $gestor->id)->update(['activo' => false]);

        // `tieneAccesoAProyecto()` mira el `activo` del pivote usuario-proyecto,
        // no el de la cuenta. Quitarle la marca al usuario no toca ese pivote,
        // así que sin el middleware el acceso seguía intacto.
        $this->assertTrue(
            DB::table('usuario_proyecto_rol')
                ->where('usuario_id', $gestor->id)
                ->where('proyecto_id', $proyecto->id)
                ->where('activo', true)
                ->exists(),
            'El pivote sigue activo: es justo el hueco que cubre el middleware.'
        );

        DB::table('users')->where('id', $gestor->id)->update(['password' => Hash::make('secreto123')]);

        Volt::test('pages.auth.login')
            ->set('form.email', $gestor->email)
            ->set('form.password', 'secreto123')
            ->call('login')
            ->assertHasErrors(['form.email' => trans('auth.desactivada')]);

        $this->get(route('proyectos.dashboard', ['proyecto_id' => $proyecto->id]))
            ->assertRedirect(route('login'));
    }
}
