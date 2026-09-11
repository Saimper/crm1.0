<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Modules\Integracion\Application\UseCases\EmitirSanctumTokenDesdeJwt;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class OperationalPreviewTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_summary_excludes_archived_activity_and_other_projects_without_erasing_it(): void
    {
        $project = $this->crearProyectoCobranza();
        $person = $this->crearPersonaEn($project, 'PREVIEW-ARCHIVE');
        $user = $this->crearGestor($project);
        $active = $this->crearCarteraEn($project);
        $activeCase = $this->crearCasoEn($project, ['cartera' => $active, 'persona' => $person]);
        [$activeManagement, $activeCommitment] = $this->activity($project, $person, $activeCase, $user->id, 5);

        foreach (['inactive', 'deleted_portfolio', 'deleted_account'] as $status) {
            $portfolio = $this->crearCarteraEn($project);
            $caseId = $this->crearCasoEn($project, ['cartera' => $portfolio, 'persona' => $person]);
            $this->activity($project, $person, $caseId, $user->id, 1);
            if ($status === 'deleted_account') {
                DB::table('casos')->where('id', $caseId)->update(['eliminada_en' => now()]);
            } else {
                DB::table('carteras')->where('id', $portfolio->id)->update(
                    $status === 'inactive' ? ['activo' => false] : ['eliminada_en' => now()],
                );
            }
        }
        $other = $this->crearProyectoCobranza();
        $foreignPerson = $this->crearPersonaEn($other, $person->identificacion);
        $foreignCase = $this->crearCasoEn($other, ['persona' => $foreignPerson]);
        $this->activity($other, $foreignPerson, $foreignCase, $user->id, 0);
        Sanctum::actingAs($user, $this->abilities($project));

        $this->getJson($this->url($project, $person))->assertOk()
            ->assertJsonCount(1, 'casos')
            ->assertJsonPath('casos.0.public_id', DB::table('casos')->where('id', $activeCase)->value('public_id'))
            ->assertJsonPath('ultima_gestion.public_id', $activeManagement)
            ->assertJsonPath('compromiso_vigente.public_id', $activeCommitment);

        DB::table('carteras')->where('id', $active->id)->update(['activo' => false]);
        $this->getJson($this->url($project, $person))->assertNotFound();
        $this->assertSame(4, DB::table('gestiones')->where('proyecto_id', $project->id)->count());
        $this->assertSame(4, DB::table('compromisos')->where('proyecto_id', $project->id)->count());
    }

    public function test_case_permission_portfolio_scope_also_governs_commitment_and_management_summary(): void
    {
        $project = $this->crearProyectoCobranza();
        $person = $this->crearPersonaEn($project);
        $user = $this->crearGestor($project);
        $allowed = $this->crearCarteraEn($project);
        $denied = $this->crearCarteraEn($project);
        $allowedCase = $this->crearCasoEn($project, ['cartera' => $allowed, 'persona' => $person]);
        $deniedCase = $this->crearCasoEn($project, ['cartera' => $denied, 'persona' => $person]);
        [$management, $commitment] = $this->activity($project, $person, $allowedCase, $user->id, 5);
        $this->activity($project, $person, $deniedCase, $user->id, 1);
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $user->id, 'proyecto_id' => $project->id,
            'rol_id' => DB::table('roles')->where('codigo', 'GESTOR')->value('id'), 'cartera_id' => $allowed->id,
        ]);
        Sanctum::actingAs($user, $this->abilities($project));

        $this->getJson($this->url($project, $person))->assertOk()->assertJsonCount(1, 'casos')
            ->assertJsonPath('ultima_gestion.public_id', $management)
            ->assertJsonPath('compromiso_vigente.public_id', $commitment);
    }

    public function test_today_commitment_uses_project_timezone_at_the_utc_day_boundary(): void
    {
        $this->travelTo(Carbon::parse('2026-09-11 02:00:00', 'UTC'));
        $project = $this->crearProyectoCobranza();
        DB::table('mandantes')->where('id', $project->mandante_id)->update(['zona_horaria' => 'America/Panama']);
        $person = $this->crearPersonaEn($project);
        $user = $this->crearGestor($project);
        $caseId = $this->crearCasoEn($project, ['persona' => $person]);
        [, $commitment] = $this->activity($project, $person, $caseId, $user->id, 0);
        DB::table('compromisos')->where('public_id', $commitment)->update(['fecha_vencimiento' => '2026-09-10']);
        Sanctum::actingAs($user, $this->abilities($project));

        $this->getJson($this->url($project, $person))->assertOk()
            ->assertJsonPath('compromiso_vigente.public_id', $commitment);
    }

    /** @return array{string, string} */
    private function activity(stdClass $project, stdClass $person, int $caseId, int $userId, int $days): array
    {
        $catalog = $this->crearCascadaGestionEn($project);
        $management = (string) Str::ulid();
        $commitment = (string) Str::ulid();
        DB::table('gestiones')->insert([
            'public_id' => $management, 'proyecto_id' => $project->id, 'caso_id' => $caseId,
            'persona_id' => $person->id, 'usuario_id' => $userId, 'canal_id' => $catalog['canal_id'],
            'tipo_gestion_id' => $catalog['tipo_gestion_id'], 'resultado_id' => $catalog['resultado_id'],
            'creada_en' => now()->subDays($days),
        ]);
        DB::table('compromisos')->insert([
            'public_id' => $commitment, 'proyecto_id' => $project->id, 'caso_id' => $caseId,
            'usuario_id' => $userId, 'tipo_compromiso' => 'promesa_pago', 'estado' => 'pendiente',
            'fecha_vencimiento' => now()->addDays($days)->toDateString(),
        ]);

        return [$management, $commitment];
    }

    private function url(stdClass $project, stdClass $person): string
    {
        $type = DB::table('tipos_identificacion')->where('id', $person->tipo_identificacion_id)->value('codigo');

        return '/api/integracion/persona?'.http_build_query([
            'proyecto_id' => $project->id, 'identificacion' => $person->identificacion, 'tipo_identificacion_codigo' => $type,
        ]);
    }

    /** @return list<string> */
    private function abilities(stdClass $project): array
    {
        return [...EmitirSanctumTokenDesdeJwt::HABILIDADES, EmitirSanctumTokenDesdeJwt::habilidadDeMandante((int) $project->mandante_id)];
    }
}
