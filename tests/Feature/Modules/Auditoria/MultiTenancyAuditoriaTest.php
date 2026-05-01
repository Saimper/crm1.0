<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auditoria;

use App\Models\User;
use App\Modules\Auditoria\Infrastructure\Http\Livewire\ListadoAuditoria;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F34C — multi-tenancy en auditoría: ListadoAuditoria filtra por
 * proyecto activo. Auditoría de proyecto B no aparece scopeada en A.
 */
final class MultiTenancyAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_listado_no_muestra_auditorias_de_otro_proyecto(): void
    {
        $proyectoA = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        $usuarioId = (int) DB::table('users')->first()->id;

        DB::table('auditorias')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoA,
            'usuario_id' => $usuarioId,
            'entidad_tipo' => 'CasoF34C',
            'entidad_id' => 9991,
            'evento' => 'creado',
            'creada_en' => Carbon::now(),
        ]);
        DB::table('auditorias')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB,
            'usuario_id' => $usuarioId,
            'entidad_tipo' => 'CasoF34C',
            'entidad_id' => 9992,
            'evento' => 'creado',
            'creada_en' => Carbon::now(),
        ]);

        $this->bindProyectoActivo($proyectoA);
        $this->actingAs($this->crearConRol($proyectoA, 'AUDITOR'));

        $c = Livewire::test(ListadoAuditoria::class);
        $registros = $c->viewData('registros');

        $idsB = DB::table('auditorias')->where('proyecto_id', $proyectoB)->pluck('id')->all();
        foreach ($registros as $r) {
            $this->assertNotContains($r->id, $idsB);
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
