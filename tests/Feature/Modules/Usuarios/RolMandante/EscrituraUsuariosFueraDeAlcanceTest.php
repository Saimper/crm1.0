<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios\RolMandante;

use App\Models\User;
use App\Modules\Usuarios\Infrastructure\Http\Livewire\AdminUsuarios;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El scoping de AdminUsuarios vivia solo en render() — la lectura — mientras
 * guardarUsuario() reescribia correo y contrasenia de cualquier id. Como
 * `editandoUsuarioId` es una propiedad publica de Livewire, ese id lo elegia el
 * cliente: un ADMIN_MANDANTE (rol que el wrapper puede auto-asignarse por SSO)
 * podia apuntar al ADMIN_GLOBAL y quedarse con su cuenta.
 */
final class EscrituraUsuariosFueraDeAlcanceTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_mandante_no_puede_reescribir_la_cuenta_del_admin_global(): void
    {
        $mandante = $this->crearMandante();
        $this->crearProyectoCobranza($mandante);
        $admin = $this->crearAdminMandante($mandante);
        $global = $this->crearAdminGlobal();

        $correoOriginal = (string) $global->email;
        $hashOriginal = (string) $global->password;

        Livewire::actingAs($admin)->test(AdminUsuarios::class)
            ->set('formUsuario', [
                'name' => 'Secuestrada',
                'email' => 'atacante@evil.io',
                'password' => 'contrasenia-nueva',
                'activo' => true,
            ])
            ->call('abrirFormEditarUsuario', $global->id)
            ->assertStatus(403);

        $fresco = $global->fresh();
        $this->assertSame($correoOriginal, (string) $fresco->email);
        $this->assertSame($hashOriginal, (string) $fresco->password);
    }

    public function test_admin_mandante_no_puede_editar_a_un_usuario_de_otro_mandante(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $this->crearProyectoCobranza($mandanteA);
        $proyectoB = $this->crearProyectoCobranza($mandanteB);

        $admin = $this->crearAdminMandante($mandanteA);
        $ajeno = $this->crearGestor($proyectoB);

        Livewire::actingAs($admin)->test(AdminUsuarios::class)
            ->call('abrirFormEditarUsuario', $ajeno->id)
            ->assertStatus(403);
    }

    public function test_el_id_en_edicion_no_se_puede_fijar_desde_el_cliente(): void
    {
        $mandante = $this->crearMandante();
        $this->crearProyectoCobranza($mandante);
        $admin = $this->crearAdminMandante($mandante);
        $global = $this->crearAdminGlobal();

        $this->expectException(\Livewire\Exceptions\CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($admin)->test(AdminUsuarios::class)
            ->set('editandoUsuarioId', $global->id);
    }

    public function test_el_admin_global_sigue_pudiendo_editar_a_cualquiera(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $global = $this->crearAdminGlobal();
        $gestor = $this->crearGestor($proyecto);

        Livewire::actingAs($global)->test(AdminUsuarios::class)
            ->call('abrirFormEditarUsuario', $gestor->id)
            ->assertOk()
            ->set('formUsuario.name', 'Nombre Corregido')
            ->set('formUsuario.email', (string) $gestor->email)
            ->set('formUsuario.activo', true)
            ->call('guardarUsuario')
            ->assertHasNoErrors();

        $this->assertSame('Nombre Corregido', (string) $gestor->fresh()->name);
    }

    public function test_un_usuario_creado_a_mano_no_queda_ligado_a_ningun_mandante(): void
    {
        $global = $this->crearAdminGlobal();

        Livewire::actingAs($global)->test(AdminUsuarios::class)
            ->call('abrirFormCrearUsuario')
            ->set('formUsuario', [
                'name' => 'Alta Manual',
                'email' => 'alta.manual@crm.local',
                'password' => 'contrasenia-larga',
                'activo' => true,
            ])
            ->call('guardarUsuario')
            ->assertHasNoErrors();

        $this->assertNull(
            User::query()->where('email', 'alta.manual@crm.local')->value('mandante_origen_id'),
            'Un alta manual no pertenece a ningún mandante, y por eso el SSO no puede reclamarla.'
        );
    }

    public function test_hash_de_contrasenia_intacto_tras_un_intento_rechazado(): void
    {
        $mandante = $this->crearMandante();
        $this->crearProyectoCobranza($mandante);
        $admin = $this->crearAdminMandante($mandante);

        $victima = User::query()->create([
            'name' => 'Ajena',
            'email' => 'ajena@crm.local',
            'password' => Hash::make('la-suya'),
            'activo' => true,
        ]);

        Livewire::actingAs($admin)->test(AdminUsuarios::class)
            ->call('abrirFormEditarUsuario', $victima->id)
            ->assertStatus(403);

        $this->assertTrue(
            Hash::check('la-suya', (string) $victima->fresh()->password),
            'La contraseña de una cuenta fuera de alcance no debe cambiar.'
        );
        $this->assertSame(0, DB::table('usuario_global_rol')->where('usuario_id', $victima->id)->count());
    }

    private function crearAdminMandante(\stdClass $mandante): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => 'Admin Mandante',
            'email' => 'admin.mand.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);

        $rolId = (int) DB::table('roles')->where('codigo', 'ADMIN_MANDANTE')->value('id');
        DB::table('usuario_mandante_rol')->insert([
            'usuario_id' => $u->id,
            'mandante_id' => $mandante->id,
            'rol_id' => $rolId,
            'activo' => true,
        ]);

        return $u;
    }
}
