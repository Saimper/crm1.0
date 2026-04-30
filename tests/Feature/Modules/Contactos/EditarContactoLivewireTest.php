<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Contactos;

use App\Models\User;
use App\Modules\Contactos\Infrastructure\Http\Livewire\ListaContactos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F34B — edición y eliminación de contactos vía Livewire.
 */
final class EditarContactoLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_edita_contacto_existente(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $persona = (object) DB::table('personas')->where('proyecto_id', $proyectoId)->first();
        $contactoId = (int) DB::table('contactos')->insertGetId([
            'proyecto_id' => $proyectoId,
            'persona_id' => $persona->id,
            'tipo' => 'telefono',
            'valor' => '+593 11111111',
            'es_principal' => false,
        ]);

        Livewire::test(ListaContactos::class, ['persona' => $persona->public_id])
            ->call('abrirEditar', $contactoId)
            ->set('valor', '+593 22222222')
            ->set('etiqueta', 'Móvil')
            ->call('guardarEdicion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('contactos', [
            'id' => $contactoId,
            'valor' => '+593 22222222',
            'etiqueta' => 'Móvil',
        ]);
    }

    public function test_marcar_principal_degrada_otros_del_mismo_tipo(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $persona = (object) DB::table('personas')->where('proyecto_id', $proyectoId)->first();
        $c1 = (int) DB::table('contactos')->insertGetId([
            'proyecto_id' => $proyectoId, 'persona_id' => $persona->id,
            'tipo' => 'correo', 'valor' => 'a@x.com', 'es_principal' => true,
        ]);
        $c2 = (int) DB::table('contactos')->insertGetId([
            'proyecto_id' => $proyectoId, 'persona_id' => $persona->id,
            'tipo' => 'correo', 'valor' => 'b@x.com', 'es_principal' => false,
        ]);

        Livewire::test(ListaContactos::class, ['persona' => $persona->public_id])
            ->call('abrirEditar', $c2)
            ->set('esPrincipal', true)
            ->call('guardarEdicion')
            ->assertHasNoErrors();

        $this->assertFalse((bool) DB::table('contactos')->where('id', $c1)->value('es_principal'));
        $this->assertTrue((bool) DB::table('contactos')->where('id', $c2)->value('es_principal'));
    }

    public function test_eliminar_contacto_marca_eliminada_en(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $persona = (object) DB::table('personas')->where('proyecto_id', $proyectoId)->first();
        $cid = (int) DB::table('contactos')->insertGetId([
            'proyecto_id' => $proyectoId, 'persona_id' => $persona->id,
            'tipo' => 'telefono', 'valor' => '+593 99999999', 'es_principal' => false,
        ]);

        Livewire::test(ListaContactos::class, ['persona' => $persona->public_id])
            ->call('eliminar', $cid);

        $this->assertNotNull(DB::table('contactos')->where('id', $cid)->value('eliminada_en'));
    }

    public function test_gestor_no_puede_eliminar(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($gestor);

        $persona = (object) DB::table('personas')->where('proyecto_id', $proyectoId)->first();
        $cid = (int) DB::table('contactos')->insertGetId([
            'proyecto_id' => $proyectoId, 'persona_id' => $persona->id,
            'tipo' => 'telefono', 'valor' => '+593 88888888', 'es_principal' => false,
        ]);

        try {
            Livewire::test(ListaContactos::class, ['persona' => $persona->public_id])
                ->call('eliminar', $cid);
        } catch (\Throwable $e) {
            // Ok — abort(403) lanza HttpException; aceptamos cualquier excepción.
        }

        // El contacto sigue activo (no se eliminó), independientemente de cómo
        // Livewire propague el abort.
        $this->assertNull(DB::table('contactos')->where('id', $cid)->value('eliminada_en'));
    }

    private function proyectoCobranza(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function bindProyectoActivo(int $proyectoId): void
    {
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
    }

    private function crearConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.ec.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
