<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notificaciones;

use App\Models\User;
use App\Modules\Notificaciones\Infrastructure\Http\Livewire\ListadoNotificaciones;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F34C — multi-tenancy: notificaciones de proyecto B no aparecen
 * en bandeja de proyecto A para mismo usuario.
 */
final class MultiTenancyNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_listado_filtra_por_proyecto_activo(): void
    {
        $proyectoA = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        $u = $this->crearConRol($proyectoA, 'GESTOR');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoB,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id'),
            'activo' => true,
        ]);

        DB::table('notificaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoA,
            'destinatario_usuario_id' => $u->id,
            'tipo' => 'compromiso_por_vencer',
            'entidad_tipo' => 'compromiso',
            'entidad_id' => 9001,
            'titulo' => 'Aviso A',
            'creada_en' => Carbon::now(),
        ]);
        DB::table('notificaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB,
            'destinatario_usuario_id' => $u->id,
            'tipo' => 'compromiso_por_vencer',
            'entidad_tipo' => 'compromiso',
            'entidad_id' => 9002,
            'titulo' => 'Aviso B',
            'creada_en' => Carbon::now(),
        ]);

        $this->bindProyectoActivo($proyectoA);
        $this->actingAs($u);

        $c = Livewire::test(ListadoNotificaciones::class);
        $items = $c->viewData('notificaciones');
        foreach ($items as $n) {
            $this->assertNotSame('Aviso B', $n->titulo);
        }
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
            'email' => strtolower($codigoRol).'.mt.'.Str::random(6).'@crm.local',
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
