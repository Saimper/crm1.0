<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Casos\Infrastructure\Http\Livewire\FichaHistorica;
use App\Modules\Casos\Infrastructure\Http\Livewire\ListadoHistorico;
use App\Modules\Usuarios\Application\RolesBase\GuardarRolBase;
use App\Modules\Usuarios\Domain\RolesBase\ConfiguracionRolBase;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class HistoricalPermissionScopeTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_unrestricted_operational_role_cannot_widen_restricted_history_role(): void
    {
        [$project, $allowed, $denied, $user, $allowedCase, $deniedCase] = $this->scenario('GESTOR');
        self::assertNull($user->carterasPermitidas((int) $project->id));
        self::assertSame([(int) $allowed->id], $user->carterasPermitidasParaPermiso('historico.ver', (int) $project->id));
        $this->activarProyecto($project);
        $this->actingAs($user);
        Livewire::test(ListadoHistorico::class)->assertViewHas('cuentas', fn ($rows) => $rows->total() === 1 && (int) $rows->first()->caso_id === $allowedCase);
        Livewire::test(FichaHistorica::class, ['caso' => DB::table('casos')->where('id', $deniedCase)->value('public_id')])->assertNotFound();
        $csv = $this->get(route('proyectos.historico.exportar', ['proyecto_id' => $project->id]))->assertOk()->streamedContent();
        self::assertStringContainsString('HISTORY-ALLOWED', $csv);
        self::assertStringNotContainsString('HISTORY-DENIED', $csv);
    }

    public function test_unrestricted_read_does_not_widen_export_scope(): void
    {
        [$project, $allowed, $denied, $user] = $this->scenario('AUDITOR');
        self::assertNull($user->carterasPermitidasParaPermiso('historico.ver', (int) $project->id));
        self::assertSame([(int) $allowed->id], $user->carterasPermitidasParaPermiso('historico.exportar', (int) $project->id));
        $this->activarProyecto($project);
        $this->actingAs($user);
        Livewire::test(ListadoHistorico::class)->assertViewHas('cuentas', fn ($rows) => $rows->total() === 2);
        $csv = $this->get(route('proyectos.historico.exportar', ['proyecto_id' => $project->id]))->assertOk()->streamedContent();
        self::assertStringContainsString('HISTORY-ALLOWED', $csv);
        self::assertStringNotContainsString('HISTORY-DENIED', $csv);
    }

    public function test_unrestricted_export_does_not_widen_read_scope_and_project_changes_clear_memo(): void
    {
        [$project, $allowed, $denied, $user] = $this->scenario('GESTOR');
        $other = $this->crearProyectoCobranza();
        $admin = $this->crearAdminGlobal();
        $save = app(GuardarRolBase::class);
        self::assertSame([(int) $allowed->id], $user->carterasPermitidasParaPermiso('historico.exportar', (int) $project->id));
        $save->execute(ConfiguracionRolBase::proyecto('GESTOR', (int) $project->id, ['historico.exportar' => 'permitir']), (int) $admin->id);
        self::assertNull($user->carterasPermitidasParaPermiso('historico.exportar', (int) $project->id));
        self::assertSame([], $user->carterasPermitidasParaPermiso('historico.exportar', (int) $other->id));
        $csv = $this->actingAs($user)->get(route('proyectos.historico.exportar', ['proyecto_id' => $project->id]))->assertOk()->streamedContent();
        self::assertStringContainsString('HISTORY-ALLOWED', $csv);
        self::assertStringNotContainsString('HISTORY-DENIED', $csv);
        $save->execute(ConfiguracionRolBase::proyecto('SUPERVISOR', (int) $project->id, ['historico.ver' => 'denegar']), (int) $admin->id);
        self::assertSame([], $user->carterasPermitidasParaPermiso('historico.ver', (int) $project->id));
        $this->get(route('proyectos.historico.exportar', ['proyecto_id' => $project->id]))->assertForbidden();
    }

    private function scenario(string $unrestrictedRole): array
    {
        $project = $this->crearProyectoCobranza();
        $allowed = $this->crearCarteraEn($project);
        $denied = $this->crearCarteraEn($project);
        $allowedCase = $this->crearCasoEn($project, ['cartera' => $allowed]);
        $deniedCase = $this->crearCasoEn($project, ['cartera' => $denied]);
        foreach ([$allowedCase => 'HISTORY-ALLOWED', $deniedCase => 'HISTORY-DENIED'] as $caseId => $reference) {
            DB::table('casos_cobranza')->insert(['caso_id' => $caseId, 'proyecto_id' => $project->id, 'numero_prestamo' => $reference, 'moneda' => 'USD', 'saldo_total' => 100]);
        }
        DB::table('carteras')->whereIn('id', [$allowed->id, $denied->id])->update(['activo' => false]);
        $user = $this->crearSupervisor($project);
        DB::table('usuario_proyecto_rol_cartera')->insert(['usuario_id' => $user->id, 'proyecto_id' => $project->id,
            'rol_id' => DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'), 'cartera_id' => $allowed->id]);
        DB::table('usuario_proyecto_rol')->insert(['usuario_id' => $user->id, 'proyecto_id' => $project->id,
            'rol_id' => DB::table('roles')->where('codigo', $unrestrictedRole)->value('id'), 'activo' => true]);

        return [$project, $allowed, $denied, $user, $allowedCase, $deniedCase];
    }
}
