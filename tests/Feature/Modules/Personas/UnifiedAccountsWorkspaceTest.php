<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Personas;

use App\Modules\Casos\Infrastructure\Http\Livewire\VistaDeTrabajo;
use App\Modules\Personas\Infrastructure\Http\Livewire\BuscadorGlobal;
use App\Modules\Personas\Infrastructure\Http\Livewire\ListadoPersonas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class UnifiedAccountsWorkspaceTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_archiving_a_portfolio_removes_its_people_and_counts_from_active_list_search_and_export(): void
    {
        $project = $this->crearProyectoCobranza();
        $active = $this->crearCarteraEn($project);
        $archived = $this->crearCarteraEn($project);
        $mixed = $this->crearPersonaEn($project, '810000001');
        $historical = $this->crearPersonaEn($project, '810000002');
        $this->crearPersonaEn($project, '810000003');
        $this->crearCasoEn($project, ['cartera' => $active, 'persona' => $mixed]);
        $this->crearCasoEn($project, ['cartera' => $archived, 'persona' => $mixed]);
        $this->crearCasoEn($project, ['cartera' => $archived, 'persona' => $historical]);
        DB::table('carteras')->where('id', $archived->id)->update(['activo' => false, 'eliminada_en' => now()]);
        $this->activarProyecto($project);
        $this->actingAs($this->crearSupervisor($project));

        $list = Livewire::test(ListadoPersonas::class);
        $this->assertSame(1, $list->viewData('totalProyecto'));
        $this->assertSame(1, (int) $list->viewData('personas')->first()->total_casos);
        Livewire::test(BuscadorGlobal::class)->set('query', '810000002')
            ->assertViewHas('personas', fn ($people) => $people->isEmpty())
            ->assertViewHas('casos', fn ($cases) => $cases->isEmpty());
        $csv = $this->get(route('proyectos.personas.exportar', ['proyecto_id' => $project->id]))->assertOk()->streamedContent();
        $this->assertStringContainsString('810000001', $csv);
        $this->assertStringNotContainsString('810000002', $csv);
        $this->assertStringNotContainsString('810000003', $csv);
        Livewire::test(VistaDeTrabajo::class, ['persona' => $historical->public_id])->assertNotFound();
        $this->assertDatabaseHas('personas', ['id' => $historical->id, 'eliminada_en' => null]);
    }

    public function test_person_work_view_shows_every_authorized_debt_and_every_pending_promise_without_leaking_siblings(): void
    {
        $project = $this->crearProyectoCobranza();
        $allowed = $this->crearCarteraEn($project);
        $denied = $this->crearCarteraEn($project);
        $person = $this->crearPersonaEn($project);
        $user = $this->crearSupervisor($project);
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $user->id, 'proyecto_id' => $project->id,
            'rol_id' => DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'), 'cartera_id' => $allowed->id,
        ]);
        $ids = [];
        foreach ([[$allowed, 'LOAN-A', 150, 10], [$allowed, 'LOAN-B', 900, 30], [$denied, 'SECRET-LOAN', 777, 99]] as [$portfolio, $reference, $balance, $days]) {
            $id = $this->crearCasoEn($project, ['cartera' => $portfolio, 'persona' => $person]);
            $ids[] = $id;
            DB::table('casos_cobranza')->insert([
                'caso_id' => $id, 'proyecto_id' => $project->id, 'numero_prestamo' => $reference,
                'moneda' => 'USD', 'saldo_total' => $balance, 'dias_mora' => $days,
            ]);
        }
        foreach ([$ids[0], $ids[0], $ids[1], $ids[2]] as $id) {
            DB::table('compromisos')->insert([
                'public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id, 'caso_id' => $id,
                'tipo_compromiso' => 'promesa_pago', 'estado' => 'pendiente',
                'fecha_vencimiento' => now()->addDays(5)->toDateString(), 'usuario_id' => $user->id,
            ]);
        }
        $this->activarProyecto($project);
        $this->actingAs($user);
        Livewire::test(VistaDeTrabajo::class, ['persona' => $person->public_id])
            ->assertViewHas('casos', fn ($cases) => $cases->count() === 2)
            ->assertViewHas('compromisosPendientes', fn ($promises) => $promises->count() === 3)
            ->assertSee('LOAN-A')->assertSee('LOAN-B')->assertDontSee('SECRET-LOAN');

        $hidden = $this->crearPersonaEn($project);
        $this->crearCasoEn($project, ['persona' => $hidden, 'cartera' => $denied]);
        Livewire::test(VistaDeTrabajo::class, ['persona' => $hidden->public_id])->assertNotFound();
    }

    public function test_other_advisors_account_remains_readable_but_cannot_offer_a_management_form(): void
    {
        $project = $this->crearProyectoCobranza();
        $person = $this->crearPersonaEn($project);
        $caseId = $this->crearCasoEn($project, ['persona' => $person]);
        $owner = $this->crearGestor($project);
        $other = $this->crearGestor($project);
        DB::table('asignaciones')->insert([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id, 'caso_id' => $caseId,
            'usuario_id' => $owner->id, 'fecha_asignacion' => now()->toDateString(), 'estado' => 'pendiente',
        ]);
        $this->activarProyecto($project);
        $this->actingAs($other);
        Livewire::test(VistaDeTrabajo::class, ['persona' => $person->public_id])
            ->assertViewHas('puedeGestionarCaso', false)->assertSee('Cuenta en consulta');
        $this->actingAs($owner);
        Livewire::test(VistaDeTrabajo::class, ['persona' => $person->public_id])
            ->assertViewHas('puedeGestionarCaso', true);
        $this->assertDatabaseHas('asignaciones', ['caso_id' => $caseId, 'usuario_id' => $owner->id]);
    }
}
