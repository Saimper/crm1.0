<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Models\User;
use App\Modules\Reportes\Application\UseCases\EjecutarReporte;
use App\Modules\Reportes\Domain\Constructor\Entities\DefinicionReporte;
use App\Modules\Reportes\Domain\Constructor\Enums\EntidadRaiz;
use App\Modules\Reportes\Domain\Constructor\ValueObjects\ColumnaReporte;
use App\Modules\Reportes\Infrastructure\Http\Livewire\DashboardAnalitico;
use App\Modules\Reportes\Infrastructure\Http\Livewire\DashboardOperativo;
use App\Modules\Reportes\Infrastructure\Http\Livewire\PanelDelDia;
use App\Modules\Reportes\Infrastructure\Http\Livewire\ReporteEquipos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ArchivedPortfolioReportsTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_dashboards_count_only_active_portfolios_without_deleting_history(): void
    {
        [$project, $teamId] = $this->scenario();
        Livewire::test(DashboardOperativo::class)
            ->assertViewHas('totalGestiones', 1)->assertViewHas('cuentasIntentadas', 1)
            ->assertViewHas('compromisosVigentes', 1)->assertViewHas('compromisosVencidos', 1)
            ->assertViewHas('ranking', fn ($rows) => (int) $rows->sum('total_gestiones') === 1)
            ->assertViewHas('gestiones', fn ($rows) => $rows->count() === 1);
        Livewire::test(DashboardAnalitico::class)
            ->assertViewHas('totalGestiones', 1)
            ->assertViewHas('distribucionCasos', fn ($rows) => (int) $rows->sum('total') === 1)
            ->assertViewHas('compromisosPorEstado', fn ($rows) => (int) $rows->sum('total') === 3)
            ->assertViewHas('gestionesPorMes', fn ($rows) => (int) $rows->sum('total') === 1)
            ->assertViewHas('topDias', fn ($rows) => (int) $rows->sum('total') === 1);
        Livewire::test(PanelDelDia::class, ['proyectoId' => (int) $project->id])->set('rango', 'semana')
            ->assertViewHas('totalGestiones', 1)->assertViewHas('vigentes', 1)
            ->assertViewHas('cumplidas', 1)->assertViewHas('vencidasSinResolver', 1)
            ->assertViewHas('tendencia', fn ($rows) => (int) $rows->sum('total') === 1)
            ->assertViewHas('dinero', fn ($money) => $money->prometido === 300.0 && $money->cumplido === 100.0);
        Livewire::test(ReporteEquipos::class)->call('expandir', $teamId)
            ->assertViewHas('filas', fn ($rows) => collect($rows)->sum('total_gestiones') === 1
                && collect($rows)->sum('compromisos_vigentes') === 1 && collect($rows)->sum('compromisos_vencidos') === 1)
            ->assertViewHas('detalle', fn ($rows) => collect($rows)->sum('total') === 1);
        $this->assertSame(4, DB::table('gestiones')->where('proyecto_id', $project->id)->count());
        $this->assertSame(12, DB::table('compromisos')->where('proyecto_id', $project->id)->count());
    }

    public function test_every_custom_report_root_excludes_archived_accounts(): void
    {
        [$project] = $this->scenario();
        foreach (EntidadRaiz::cases() as $entity) {
            $definition = new DefinicionReporte((int) $project->id, 'ARCHIVE_'.$entity->value, 'Archive isolation', $entity,
                [new ColumnaReporte($entity->value.'.public_id', 'ID')]);
            $rows = iterator_to_array(app(EjecutarReporte::class)->execute($definition, limite: 100)->filas);
            $this->assertCount($entity === EntidadRaiz::COMPROMISOS ? 3 : 1, $rows, $entity->value);
        }
    }

    public function test_all_dashboards_respect_the_portfolios_granting_the_report_permission(): void
    {
        [$project, $teamId] = $this->scenario();
        $allowed = (int) DB::table('carteras')->where('proyecto_id', $project->id)->where('activo', true)->whereNull('eliminada_en')->orderBy('id')->value('id');
        // A second active portfolio must remain hidden even with an unrelated unscoped role.
        DB::table('carteras')->where('proyecto_id', $project->id)->whereNotNull('eliminada_en')->update(['eliminada_en' => null]);
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'proyecto_id' => $project->id, 'usuario_id' => auth()->id(), 'cartera_id' => $allowed,
            'rol_id' => DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'),
        ]);
        DB::table('usuario_proyecto_rol')->insert([
            'proyecto_id' => $project->id, 'usuario_id' => auth()->id(), 'activo' => true,
            'rol_id' => DB::table('roles')->where('codigo', 'GESTOR')->value('id'),
        ]);
        User::olvidarPermisosCacheados();

        Livewire::test(DashboardOperativo::class)->assertViewHas('totalGestiones', 1)
            ->assertViewHas('compromisosVigentes', 1)->assertViewHas('compromisosVencidos', 1);
        Livewire::test(DashboardAnalitico::class)->assertViewHas('totalGestiones', 1)
            ->assertViewHas('distribucionCasos', fn ($rows) => (int) $rows->sum('total') === 1);
        Livewire::test(PanelDelDia::class, ['proyectoId' => (int) $project->id])
            ->assertViewHas('totalGestiones', 1)->assertViewHas('vigentes', 1)
            ->assertViewHas('dinero', fn ($money) => $money->prometido === 300.0);
        Livewire::test(ReporteEquipos::class)->call('expandir', $teamId)
            ->assertViewHas('filas', fn ($rows) => collect($rows)->sum('total_gestiones') === 1)
            ->assertViewHas('detalle', fn ($rows) => collect($rows)->sum('total') === 1);
    }

    /** @return array{\stdClass, int} */
    private function scenario(): array
    {
        $project = $this->crearProyectoCobranza();
        $user = $this->crearSupervisor($project);
        $this->activarProyecto($project);
        $this->actingAs($user);
        $cascade = $this->crearCascadaGestionEn($project);
        $teamId = (int) DB::table('equipos')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id,
            'codigo' => 'ARCHIVE_TEAM', 'nombre' => 'Equipo activo', 'activo' => true,
        ]);
        DB::table('equipo_usuario')->insert(['proyecto_id' => $project->id, 'equipo_id' => $teamId, 'usuario_id' => $user->id, 'activo' => true]);
        foreach (['active', 'archived', 'inactive', 'deleted_account'] as $status) {
            $portfolio = $this->crearCarteraEn($project);
            $person = $this->crearPersonaEn($project);
            $caseId = $this->crearCasoEn($project, ['cartera' => $portfolio, 'persona' => $person]);
            DB::table('gestiones')->insert([
                'public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id, 'caso_id' => $caseId,
                'persona_id' => $person->id, 'usuario_id' => $user->id, 'creada_en' => now(),
                'canal_id' => $cascade['canal_id'], 'tipo_gestion_id' => $cascade['tipo_gestion_id'], 'resultado_id' => $cascade['resultado_id'],
            ]);
            foreach (['future', 'overdue', 'fulfilled'] as $state) {
                $commitmentId = (int) DB::table('compromisos')->insertGetId([
                    'public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id, 'caso_id' => $caseId,
                    'usuario_id' => $user->id, 'tipo_compromiso' => 'promesa_pago', 'estado' => $state === 'fulfilled' ? 'cumplido' : 'pendiente',
                    'fecha_vencimiento' => ($state === 'overdue' ? now()->subDay() : now()->addDay())->toDateString(),
                    'fecha_resolucion' => $state === 'fulfilled' ? now()->toDateString() : null, 'creada_en' => now(),
                ]);
                DB::table('compromisos_promesa_pago')->insert(['proyecto_id' => $project->id, 'compromiso_id' => $commitmentId, 'monto' => '100.000', 'moneda' => 'USD']);
            }
            if ($status === 'archived') {
                DB::table('carteras')->where('id', $portfolio->id)->update(['eliminada_en' => now()]);
            } elseif ($status === 'inactive') {
                DB::table('carteras')->where('id', $portfolio->id)->update(['activo' => false]);
            } elseif ($status === 'deleted_account') {
                DB::table('casos')->where('id', $caseId)->update(['eliminada_en' => now()]);
            }
        }

        return [$project, $teamId];
    }
}
