<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Compromisos;

use App\Models\User;
use App\Modules\Compromisos\Infrastructure\Http\Livewire\ListadoCompromisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F34B — listado paginado de compromisos por proyecto + multi-tenancy.
 */
final class ListadoCompromisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_supervisor_ve_resumen_y_listado(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $totalDb = (int) DB::table('compromisos')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('eliminada_en')
            ->count();

        $c = Livewire::test(ListadoCompromisos::class);
        $compromisos = $c->viewData('compromisos');
        $this->assertSame($totalDb, $compromisos->total());
    }

    public function test_filtro_estado_pendiente(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $countPendientes = (int) DB::table('compromisos')
            ->where('proyecto_id', $proyectoId)
            ->where('estado', 'pendiente')
            ->whereNull('eliminada_en')
            ->count();

        $c = Livewire::test(ListadoCompromisos::class)->set('estado', 'pendiente');
        $this->assertSame($countPendientes, $c->viewData('compromisos')->total());
    }

    public function test_no_filtra_compromisos_de_otro_proyecto(): void
    {
        $proyectoA = $this->proyectoCobranza();
        $proyectoB = $this->proyectoCx();

        $supervisor = $this->crearConRol($proyectoA, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoA);
        $this->actingAs($supervisor);

        $totalA = (int) DB::table('compromisos')
            ->where('proyecto_id', $proyectoA)
            ->whereNull('eliminada_en')
            ->count();

        $c = Livewire::test(ListadoCompromisos::class);
        $this->assertSame($totalA, $c->viewData('compromisos')->total());

        $idsB = DB::table('compromisos')->where('proyecto_id', $proyectoB)->pluck('id')->all();
        foreach ($c->viewData('compromisos') as $comp) {
            $this->assertNotContains($comp->id, $idsB);
        }
    }

    public function test_gestor_accede_pantalla(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.compromisos.lista', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
    }

    private function proyectoCobranza(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function proyectoCx(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
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
            'email' => strtolower($codigoRol).'.lc.'.Str::random(6).'@crm.local',
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
