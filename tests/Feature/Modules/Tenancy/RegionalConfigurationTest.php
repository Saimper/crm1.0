<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use App\Modules\Importaciones\Domain\Contracts\CampoPersonalizadoImportacionRepository;
use App\Modules\Tenancy\Application\Services\RelojDelMandante;
use App\Modules\Tenancy\Application\UseCases\SaveRegionalSettings;
use App\Modules\Tenancy\Domain\Contracts\RegionalConfiguration;
use App\Modules\Tenancy\Domain\ValueObjects\RegionalSettings;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\RegionalSettingsEditor;
use App\Support\Database\LocalCalendarSql;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class RegionalConfigurationTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_project_inherits_mandante_defaults_and_overrides_are_isolated(): void
    {
        $mandante = $this->crearMandante();
        $first = $this->crearProyecto('cobranza', $mandante);
        $second = $this->crearProyecto('cobranza', $mandante);
        app(SaveRegionalSettings::class)->execute((int) $mandante->id, null, new RegionalSettings(timezone: 'America/Panama', currency: 'PAB'));
        app(SaveRegionalSettings::class)->execute((int) $mandante->id, (int) $first->id,
            new RegionalSettings(timezone: 'Europe/Madrid', currency: 'EUR', dateFormat: 'Y-m-d', decimalPlaces: 3, decimalSeparator: ',', thousandsSeparator: '.'));
        $resolver = app(RegionalConfiguration::class);
        $this->assertSame('EUR', $resolver->forProject((int) $first->id)->currency);
        $this->assertSame(3, $resolver->forProject((int) $first->id)->decimalPlaces);
        $this->assertSame('PAB', $resolver->forProject((int) $second->id)->currency);
        $this->assertSame(2, $resolver->forProject((int) $second->id)->decimalPlaces);
        app(SaveRegionalSettings::class)->execute((int) $mandante->id, (int) $first->id, null);
        $this->assertSame('PAB', $resolver->forProject((int) $first->id)->currency);
    }

    public function test_explicit_project_clock_works_without_request_context(): void
    {
        $mandante = $this->crearMandante();
        $first = $this->crearProyecto('cobranza', $mandante);
        $second = $this->crearProyecto('cobranza', $mandante);
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);
        DB::table('proyectos')->where('id', $first->id)->update(['zona_horaria' => 'Europe/Madrid']);
        Carbon::setTestNow(Carbon::parse('2026-09-08 02:00:00', 'UTC'));
        try {
            $clock = app(RelojDelMandante::class);
            $this->assertSame('2026-09-08', $clock->hoy(proyectoId: (int) $first->id));
            $this->assertSame('2026-09-07', $clock->hoy(proyectoId: (int) $second->id));
            $this->assertSame('2026-09-07 22:00:00', $clock->inicioDe('hoy', proyectoId: (int) $first->id)->toDateTimeString());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_global_administrator_can_save_defaults_from_the_form(): void
    {
        $mandante = $this->crearMandante();
        $this->actingAs($this->crearAdminGlobal());
        Livewire::test(RegionalSettingsEditor::class, ['mandanteId' => (int) $mandante->id])
            ->set('regional.zona_horaria', 'America/Panama')
            ->set('regional.decimales', 3)
            ->set('regional.moneda', 'PAB')
            ->call('save')->assertHasNoErrors()->assertSee('Configuración regional guardada.');
        $this->assertDatabaseHas('mandantes', ['id' => $mandante->id, 'zona_horaria' => 'America/Panama', 'decimales' => 3, 'moneda' => 'PAB']);
    }

    public function test_form_rejects_equal_separators_without_writing_settings(): void
    {
        $mandante = $this->crearMandante();
        $this->actingAs($this->crearAdminGlobal());
        Livewire::test(RegionalSettingsEditor::class, ['mandanteId' => (int) $mandante->id])
            ->set('regional.separador_decimal', ',')->call('save')->assertHasErrors(['regional']);
        $this->assertDatabaseHas('mandantes', ['id' => $mandante->id, 'separador_decimal' => '.']);
    }

    public function test_form_blocks_gestor_and_forged_project_mandante_pair(): void
    {
        $project = $this->crearProyectoCobranza();
        $other = $this->crearMandante();
        $this->actingAs($this->crearGestor($project));
        Livewire::test(RegionalSettingsEditor::class, ['mandanteId' => (int) $project->mandante_id, 'projectId' => (int) $project->id])->assertStatus(403);
        $this->actingAs($this->crearAdminGlobal());
        Livewire::test(RegionalSettingsEditor::class, ['mandanteId' => (int) $other->id, 'projectId' => (int) $project->id])->assertStatus(404);
    }

    public function test_sql_daily_buckets_use_local_midnight_and_daylight_saving(): void
    {
        foreach ([
            ['America/Panama', '2026-09-08 02:00:00', '2026-09-07'],
            ['Europe/Madrid', '2026-03-28 22:30:00', '2026-03-28'],
            ['Europe/Madrid', '2026-03-29 22:30:00', '2026-03-30'],
        ] as [$zone, $instant, $expected]) {
            $expression = LocalCalendarSql::expression('instant', $zone);
            $row = DB::selectOne('SELECT '.$expression.' AS day FROM (SELECT ? AS instant) AS sample', [$instant]);
            $this->assertSame($expected, $row->day);
        }
    }

    public function test_new_account_uses_regional_currency_and_preserves_real_precision(): void
    {
        $project = $this->crearProyectoCobranza();
        $portfolio = $this->crearCarteraEn($project);
        $person = $this->crearPersonaEn($project);
        $state = $this->crearEstadoCasoEn($project);
        app(SaveRegionalSettings::class)->execute((int) $project->mandante_id, (int) $project->id, new RegionalSettings(currency: 'EUR', decimalPlaces: 3));
        $input = new RegistrarCasoCobranzaInput(
            proyectoId: (int) $project->id, carteraId: (int) $portfolio->id, personaId: (int) $person->id,
            estadoCasoId: (int) $state->id, fechaIngreso: new \DateTimeImmutable('2026-09-10'), prioridad: 1,
            numeroPrestamo: 'REGIONAL-ACCOUNT', saldoTotal: '100.001',
        );
        $output = app(RegistrarCasoCobranza::class)->execute($input);
        $this->assertDatabaseHas('casos_cobranza', ['caso_id' => $output->casoId, 'moneda' => 'EUR', 'saldo_total' => '100.001']);
    }

    public function test_custom_money_import_preserves_precision_and_original_currency(): void
    {
        $project = $this->crearProyectoCobranza();
        $portfolio = $this->crearCarteraEn($project);
        $caseId = $this->crearCasoEn($project, ['cartera' => $portfolio]);
        app(SaveRegionalSettings::class)->execute((int) $project->mandante_id, (int) $project->id, new RegionalSettings(currency: 'EUR', decimalPlaces: 3));
        $repository = app(CampoPersonalizadoImportacionRepository::class);
        $fieldId = $repository->crearCampo((int) $project->id, (int) $portfolio->id, 'REGIONAL_MONEY', 'Monto regional', TipoCampo::MONEDA);
        $repository->guardarValoresEnLote([['campo_id' => $fieldId, 'entidad_id' => $caseId, 'tipo' => 'moneda', 'valor' => '1234567890123.456']]);
        $this->assertDatabaseHas('valores_campo_personalizado', ['campo_personalizado_id' => $fieldId, 'valor_moneda_monto' => '1234567890123.456', 'valor_moneda_codigo' => 'EUR']);
        app(SaveRegionalSettings::class)->execute((int) $project->mandante_id, (int) $project->id, new RegionalSettings(currency: 'USD', decimalPlaces: 3));
        $repository->guardarValoresEnLote([['campo_id' => $fieldId, 'entidad_id' => $caseId, 'tipo' => 'moneda', 'valor' => '1234567890123.457']]);
        $this->assertDatabaseHas('valores_campo_personalizado', ['campo_personalizado_id' => $fieldId, 'valor_moneda_monto' => '1234567890123.457', 'valor_moneda_codigo' => 'EUR']);
    }

    public function test_meaningful_third_decimal_survives_database_storage(): void
    {
        $project = $this->crearProyectoCobranza();
        $portfolio = $this->crearCarteraEn($project);
        $person = $this->crearPersonaEn($project);
        $caseId = $this->crearCasoEn($project, ['cartera' => $portfolio, 'persona' => $person]);
        DB::table('casos_cobranza')->insert(['caso_id' => $caseId, 'proyecto_id' => $project->id, 'numero_prestamo' => 'PRECISION-3', 'moneda' => 'USD', 'saldo_total' => '1234567890123.456']);
        $this->assertSame('1234567890123.456', DB::table('casos_cobranza')->where('caso_id', $caseId)->value('saldo_total'));
        $this->expectException(\InvalidArgumentException::class);
        app(SaveRegionalSettings::class)->execute((int) $project->mandante_id, (int) $project->id, new RegionalSettings(decimalPlaces: 2));
    }
}
