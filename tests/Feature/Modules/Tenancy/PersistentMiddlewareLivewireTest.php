<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Models\User;
use App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire\FormularioCamposPersonalizados;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fix reportado por usuario: al hacer Livewire update desde /proyectos/{id}/trabajo/...
 * el binding `tenancy.proyecto_activo` no estaba disponible porque el middleware
 * `proyecto.activo` no corre en la ruta `/livewire/update`.
 *
 * Solución: `ResolverProyectoActivo` registrado como persistent middleware de Livewire,
 * resolviendo proyecto_id del Referer cuando el route no lo tiene.
 */
final class PersistentMiddlewareLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_gestor_guarda_campo_sin_tener_tenancy_bindeado_previamente(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->value('id');
        $casoId = (int) DB::table('casos')->where('proyecto_id', $proyectoId)->value('id');

        // Aseguramos que existe al menos un campo personalizado del ámbito caso×cartera.
        $campoId = (int) DB::table('campos_personalizados')
            ->where('proyecto_id', $proyectoId)
            ->where('ambito', 'caso')
            ->where('ambito_id', $carteraId)
            ->value('id');
        if ($campoId === 0) {
            DB::table('campos_personalizados')->insert([
                'proyecto_id' => $proyectoId,
                'ambito'      => 'caso',
                'ambito_id'   => $carteraId,
                'codigo'      => 'operador_externo',
                'etiqueta'    => 'Operador externo',
                'tipo'        => 'texto_corto',
                'obligatorio' => false,
                'activo'      => true,
                'orden'       => 10,
            ]);
        }

        $gestor = User::query()->create([
            'name' => 'Gestor', 'email' => 'gestor.fix.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $gestor->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolGestorId, 'activo' => true,
        ]);

        $this->actingAs($gestor);

        // Simulamos lo que ocurre en el browser: el GET a /proyectos/{id}/trabajo renderiza
        // con tenancy bindeado, pero los subsiguientes POST /livewire/update NO pasan por
        // `proyecto.activo` como middleware de ruta. Al comenzar este test, el binding
        // NO existe — replica la condición real del bug.
        $this->assertFalse(app()->bound('tenancy.proyecto_activo'),
            'Precondición: tenancy.proyecto_activo NO debe estar bindeado al inicio.');

        // El componente se monta sin depender de app('tenancy.proyecto_activo') porque
        // recibe proyectoId como prop. Debe poder guardar incluso sin binding previo.
        Livewire::test(FormularioCamposPersonalizados::class, [
                'proyectoId' => $proyectoId,
                'ambito'     => 'caso',
                'ambitoId'   => $carteraId,
                'entidadId'  => $casoId,
            ])
            ->set('valores.operador_externo', 'Valor del gestor')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('valores_campo_personalizado', [
            'entidad_id'        => $casoId,
            'valor_texto_corto' => 'Valor del gestor',
        ]);
    }

    public function test_middleware_extrae_proyecto_del_referer(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');

        $gestor = User::query()->create([
            'name' => 'Gestor', 'email' => 'gestor.ref.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $gestor->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolGestorId, 'activo' => true,
        ]);

        // Simulamos un request /livewire/update con Referer apuntando a /proyectos/{id}/trabajo
        $middleware = new \App\Modules\Tenancy\Infrastructure\Http\Middleware\ResolverProyectoActivo();
        $request = \Illuminate\Http\Request::create('/livewire/update', 'POST');
        $request->headers->set('Referer', "http://localhost/proyectos/{$proyectoId}/trabajo/01ABC");
        $request->setUserResolver(fn () => $gestor);

        $response = $middleware->handle($request, fn ($r) => response('ok'));

        $this->assertSame('ok', (string) $response->getContent());
        $this->assertTrue(app()->bound('tenancy.proyecto_activo'));
        $this->assertSame($proyectoId, (int) app('tenancy.proyecto_activo')->id);
    }

    public function test_middleware_no_aborta_en_livewire_cuando_no_hay_referer_resolvible(): void
    {
        $middleware = new \App\Modules\Tenancy\Infrastructure\Http\Middleware\ResolverProyectoActivo();
        $request = \Illuminate\Http\Request::create('/livewire/update', 'POST');

        $response = $middleware->handle($request, fn ($r) => response('ok'));

        // No aborta 404: el componente Livewire sigue ejecutándose aunque no haya tenancy.
        $this->assertSame('ok', (string) $response->getContent());
        $this->assertFalse(app()->bound('tenancy.proyecto_activo'));
    }
}
