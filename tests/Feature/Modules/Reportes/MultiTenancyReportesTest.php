<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Models\User;
use App\Modules\Reportes\Infrastructure\Http\Livewire\ListadoReportesCustom;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F34C — multi-tenancy: definiciones de reportes custom de proyecto B
 * no aparecen en listado de proyecto A.
 */
final class MultiTenancyReportesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_listado_definiciones_filtra_por_proyecto_activo(): void
    {
        $proyectoA = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        $usuarioId = (int) DB::table('users')->first()->id;

        $base = [
            'entidad_raiz' => 'casos',
            'columnas' => json_encode([]),
            'filtros' => json_encode([]),
            'agrupaciones' => json_encode([]),
            'orden' => json_encode([]),
            'creado_por_usuario_id' => $usuarioId,
        ];
        DB::table('reportes_definiciones')->insert(array_merge($base, [
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoA,
            'codigo' => 'REP_A',
            'nombre' => 'Reporte A',
        ]));
        DB::table('reportes_definiciones')->insert(array_merge($base, [
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB,
            'codigo' => 'REP_B',
            'nombre' => 'Reporte B',
        ]));

        $this->bindProyectoActivo($proyectoA);
        $this->actingAs($this->crearConRol($proyectoA, 'SUPERVISOR'));

        $c = Livewire::test(ListadoReportesCustom::class);
        $defs = $c->viewData('definiciones');
        $codigos = collect($defs)->pluck('codigo')->all();
        $this->assertContains('REP_A', $codigos);
        $this->assertNotContains('REP_B', $codigos);
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
