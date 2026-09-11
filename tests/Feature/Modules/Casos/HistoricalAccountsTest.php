<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Models\User;
use App\Modules\Casos\Application\Services\ConsultaHistorico;
use App\Modules\Casos\Infrastructure\Http\Livewire\FichaHistorica;
use App\Modules\Casos\Infrastructure\Http\Livewire\ListadoHistorico;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class HistoricalAccountsTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_history_excludes_active_accounts_other_projects_and_denied_portfolios(): void
    {
        $project = $this->crearProyectoCobranza();
        $allowed = $this->crearCarteraEn($project);
        $denied = $this->crearCarteraEn($project);
        $allowedId = $this->crearCasoEn($project, ['cartera' => $allowed]);
        $deniedId = $this->crearCasoEn($project, ['cartera' => $denied]);
        $this->crearCasoEn($project);
        $foreign = $this->crearProyectoCobranza();
        $foreignPortfolio = $this->crearCarteraEn($foreign);
        $this->crearCasoEn($foreign, ['cartera' => $foreignPortfolio]);
        DB::table('carteras')->whereIn('id', [$allowed->id, $denied->id, $foreignPortfolio->id])->update(['activo' => false]);
        $user = $this->crearSupervisor($project);
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $user->id, 'proyecto_id' => $project->id,
            'rol_id' => DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'), 'cartera_id' => $allowed->id,
        ]);
        $this->activarProyecto($project);
        $this->actingAs($user);
        Livewire::test(ListadoHistorico::class)
            ->assertViewHas('cuentas', fn ($rows) => $rows->total() === 1 && (int) $rows->first()->caso_id === $allowedId);
        Livewire::test(FichaHistorica::class, ['caso' => DB::table('casos')->where('id', $deniedId)->value('public_id')])->assertNotFound();
        $this->actingAs($this->crearGestor($project))->get(route('proyectos.historico.lista', ['proyecto_id' => $project->id]))->assertForbidden();
    }

    public function test_a_reincorporated_account_retains_its_source_balance_author_and_only_source_activity(): void
    {
        [$project, $caseId, $source, $destination, $owner, $archive, $oldManagement, $oldPromise] = $this->movedScenario();
        $supervisor = $this->crearSupervisor($project);
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $supervisor->id, 'proyecto_id' => $project->id,
            'rol_id' => DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'), 'cartera_id' => $source->id,
        ]);
        $this->activarProyecto($project);
        $this->actingAs($supervisor);
        $casePublic = DB::table('casos')->where('id', $caseId)->value('public_id');
        $ficha = Livewire::withQueryParams(['archivo' => $archive])->test(FichaHistorica::class, ['caso' => $casePublic])
            ->assertSee('ORIGINAL-NOTE')->assertDontSee('NEW-PORTFOLIO-NOTE');
        $this->assertSame('100.000', number_format((float) $ficha->viewData('cuenta')->saldo_total, 3, '.', ''));
        $this->assertSame($owner->name, $ficha->viewData('cuenta')->asesor_nombre);
        $this->assertSame([$oldManagement], $ficha->viewData('gestiones')->pluck('registro_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([$oldPromise], $ficha->viewData('compromisos')->pluck('registro_id')->map(fn ($id) => (int) $id)->all());
        $this->assertDatabaseHas('casos', ['id' => $caseId, 'cartera_id' => $destination->id]);
        $this->assertDatabaseHas('asignaciones', ['caso_id' => $caseId, 'usuario_id' => $owner->id]);
        Livewire::withQueryParams([])->test(FichaHistorica::class, ['caso' => $casePublic])->assertNotFound();
    }

    public function test_history_csv_uses_source_filters_neutralizes_notes_and_audits_each_export(): void
    {
        [$project, $caseId, $source, , , $archive, $oldManagement] = $this->movedScenario();
        DB::table('gestiones')->where('id', $oldManagement)->update(['notas' => '=HYPERLINK("example")']);
        $this->actingAs($this->crearSupervisor($project));
        $parameters = ['proyecto_id' => $project->id, 'tipo' => 'gestiones', 'cartera' => $source->public_id];
        $csv = $this->get(route('proyectos.historico.exportar', $parameters))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('NEW-PORTFOLIO-NOTE', $csv);
        $this->assertDatabaseHas('auditorias', ['evento' => 'exportado', 'entidad_tipo' => 'gestiones', 'proyecto_id' => $project->id]);
        foreach (['cuentas', 'compromisos'] as $type) {
            $parameters['tipo'] = $type;
            $this->get(route('proyectos.historico.exportar', $parameters))->assertOk()->streamedContent();
        }
        $this->assertSame(3, DB::table('auditorias')->where('evento', 'exportado')->count());
        $this->assertSame(1, app(ConsultaHistorico::class)->cuentas((int) $project->id, [(int) $source->id], archivo: $archive)->count());
    }

    public function test_historical_activity_export_crosses_chunk_boundaries_without_losing_or_repeating_records(): void
    {
        $project = $this->crearProyectoCobranza();
        $portfolio = $this->crearCarteraEn($project);
        $person = $this->crearPersonaEn($project);
        $caseId = $this->crearCasoEn($project, ['cartera' => $portfolio, 'persona' => $person]);
        $user = $this->crearSupervisor($project);
        $catalog = $this->crearCascadaGestionEn($project);
        $rows = [];
        for ($index = 0; $index < 505; $index++) {
            $rows[] = [
                'public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id, 'caso_id' => $caseId, 'persona_id' => $person->id,
                'usuario_id' => $user->id, 'tipo_gestion_id' => $catalog['tipo_gestion_id'], 'resultado_id' => $catalog['resultado_id'],
                'canal_id' => $catalog['canal_id'], 'notas' => 'HISTORY-'.$index, 'creada_en' => now(),
            ];
        }
        DB::table('gestiones')->insert($rows);
        DB::table('carteras')->where('id', $portfolio->id)->update(['activo' => false]);
        $csv = $this->actingAs($user)->get(route('proyectos.historico.exportar', [
            'proyecto_id' => $project->id, 'tipo' => 'gestiones',
        ]))->assertOk()->streamedContent();
        $lines = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));
        $headers = array_shift($lines);
        $noteColumn = array_search('notas', $headers, true);
        $notes = array_column($lines, $noteColumn);
        $this->assertCount(505, $notes);
        $this->assertCount(505, array_unique($notes));
        $this->assertContains('HISTORY-0', $notes);
        $this->assertContains('HISTORY-504', $notes);
    }

    /** @return array{stdClass, int, stdClass, stdClass, User, string, int, int} */
    private function movedScenario(): array
    {
        $project = $this->crearProyectoCobranza();
        $source = $this->crearCarteraEn($project);
        $destination = $this->crearCarteraEn($project);
        $person = $this->crearPersonaEn($project, '820000001');
        $caseId = $this->crearCasoEn($project, ['cartera' => $destination, 'persona' => $person]);
        DB::table('casos_cobranza')->insert(['caso_id' => $caseId, 'proyecto_id' => $project->id,
            'numero_prestamo' => 'ARCHIVED-LOAN', 'moneda' => 'USD', 'saldo_total' => 9000, 'dias_mora' => 99]);
        DB::table('carteras')->where('id', $source->id)->update(['activo' => false, 'eliminada_en' => now()]);
        $owner = $this->crearGestor($project);
        DB::table('asignaciones')->insert(['public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id,
            'caso_id' => $caseId, 'usuario_id' => $owner->id, 'fecha_asignacion' => now()->toDateString()]);
        $catalog = $this->crearCascadaGestionEn($project);
        $oldManagement = $this->management($project, $caseId, (int) $person->id, (int) $owner->id, $catalog, 'ORIGINAL-NOTE');
        $oldPromise = $this->promise($project, $caseId, (int) $owner->id);
        $archive = (string) Str::ulid();
        DB::table('caso_cartera_movimientos')->insert([
            'public_id' => $archive, 'proyecto_id' => $project->id, 'caso_id' => $caseId,
            'cartera_origen_id' => $source->id, 'cartera_destino_id' => $destination->id, 'usuario_id' => $owner->id,
            'asesor_anterior_id' => $owner->id, 'trasladada_en' => now(),
            'instantanea' => json_encode(['referencia' => 'ARCHIVED-LOAN', 'saldo_total' => '100.000', 'moneda' => 'USD', 'dias_mora' => 5,
                'cartera_nombre' => $source->nombre, 'asesor_nombre' => $owner->name, 'estado_caso_nombre' => 'Abierto',
                'fecha_ingreso' => now()->toDateString(), 'last_gestion_id' => $oldManagement, 'last_compromiso_id' => $oldPromise], JSON_THROW_ON_ERROR),
        ]);
        // Both writes deliberately share the transfer's second; ID boundaries must distinguish them.
        $this->management($project, $caseId, (int) $person->id, (int) $owner->id, $catalog, 'NEW-PORTFOLIO-NOTE');
        $this->promise($project, $caseId, (int) $owner->id);

        return [$project, $caseId, $source, $destination, $owner, $archive, $oldManagement, $oldPromise];
    }

    /** @param array<string, int> $catalog */
    private function management(stdClass $project, int $caseId, int $personId, int $userId, array $catalog, string $notes): int
    {
        return (int) DB::table('gestiones')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id, 'caso_id' => $caseId, 'persona_id' => $personId,
            'usuario_id' => $userId, 'tipo_gestion_id' => $catalog['tipo_gestion_id'], 'resultado_id' => $catalog['resultado_id'],
            'canal_id' => $catalog['canal_id'], 'notas' => $notes, 'creada_en' => now(),
        ]);
    }

    private function promise(stdClass $project, int $caseId, int $userId): int
    {
        return (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id, 'caso_id' => $caseId, 'usuario_id' => $userId,
            'tipo_compromiso' => 'promesa_pago', 'estado' => 'pendiente', 'fecha_vencimiento' => now()->addDays(3)->toDateString(),
        ]);
    }
}
