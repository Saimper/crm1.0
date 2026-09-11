<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Modules\Asignaciones\Infrastructure\Http\Livewire\Bandeja;
use App\Modules\Casos\Application\Services\ConsultaListadoCasos;
use App\Modules\Casos\Infrastructure\Http\Livewire\VistaDeTrabajo;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Importaciones\Application\UseCases\ProcesarFilaDinamica;
use App\Modules\Importaciones\Application\UseCases\ProcesarFilaInput;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\Exceptions\FilaNoImportable;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Tenancy\Application\UseCases\AdministrarDisponibilidad;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\AdminMandantes;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoCarteras;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use App\Modules\Usuarios\Application\RolesCustom\DTOs\EntradaRolCustom;
use App\Modules\Usuarios\Application\RolesCustom\UseCases\CrearRolCustom;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class DisponibilidadOperativaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_disabled_portfolio_leaves_workflows_and_reactivation_restores_accounts_without_changing_history(): void
    {
        $project = $this->crearProyectoCobranza();
        $portfolio = $this->crearCarteraEn($project);
        $person = $this->crearPersonaEn($project);
        $caseId = $this->crearCasoEn($project, ['cartera' => $portfolio, 'persona' => $person]);
        $user = $this->crearGestor($project);
        $this->activarProyecto($project);
        $this->actingAs($user);
        $catalog = $this->crearCascadaGestionEn($project);
        $input = new RegistrarGestionInput((string) Str::ulid(), (int) $project->id, $caseId, (int) $person->id,
            null, $catalog['canal_id'], $catalog['tipo_gestion_id'], $catalog['resultado_id'], null, null,
            (int) $user->id, 'Historical interaction', null, new DateTimeImmutable);
        app(RegistrarGestion::class)->execute($input);
        $history = DB::table('gestiones')->where('caso_id', $caseId)->get()->toJson();
        $availability = app(AdministrarDisponibilidad::class);
        $availability->cambiarEstadoCartera((int) $project->id, (int) $portfolio->id, false);

        $this->assertSame(0, app(ConsultaListadoCasos::class)->consultaBase((int) $project->id, 'cobranza')->count());
        Livewire::test(Bandeja::class)->assertViewHas('totalGeneral', 0);
        Livewire::test(VistaDeTrabajo::class, ['persona' => $person->public_id])
            ->assertNotFound();
        $this->assertSame($history, DB::table('gestiones')->where('caso_id', $caseId)->get()->toJson());
        try {
            app(RegistrarGestion::class)->execute($input);
            $this->fail('Disabled accounts must reject stale work forms.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('no está disponible', $e->getMessage());
        }

        $availability->cambiarEstadoCartera((int) $project->id, (int) $portfolio->id, true);
        $this->assertSame(1, app(ConsultaListadoCasos::class)->consultaBase((int) $project->id, 'cobranza')->count());
        Livewire::test(VistaDeTrabajo::class, ['persona' => $person->public_id])
            ->assertViewHas('casos', fn ($cases) => $cases->count() === 1);
        $availability->eliminarCartera((int) $project->id, (int) $portfolio->id);
        $availability->cambiarEstadoCartera((int) $project->id, (int) $portfolio->id, true);
        $this->assertSame(0, app(ConsultaListadoCasos::class)->consultaBase((int) $project->id, 'cobranza')->count());
        $this->assertSame($history, DB::table('gestiones')->where('caso_id', $caseId)->get()->toJson());
        $this->assertDatabaseHas('casos', ['id' => $caseId, 'eliminada_en' => null]);
    }

    public function test_direct_row_processing_without_import_authorization_cannot_reincorporate_an_account(): void
    {
        $project = $this->crearProyectoCobranza();
        $active = $this->crearCarteraEn($project);
        $disabled = $this->crearCarteraEn($project);
        $caseId = $this->crearCasoEn($project, ['cartera' => $disabled]);
        DB::table('carteras')->where('id', $disabled->id)->update(['activo' => false]);
        try {
            app(ProcesarFilaDinamica::class)->execute(new ProcesarFilaInput(
                fila: ['id_cpelegido' => 'RETIRED_ACCOUNT'],
                esquema: new EsquemaImportacion(TargetImportacion::CASO_COBRANZA, (int) $project->id, (int) $active->id, ModoImportacion::UPDATE, []),
                importacionFilaId: 1, mapaCampos: [], casosExistentes: ['RETIRED_ACCOUNT' => $caseId],
            ));
            $this->fail('Direct processing must require server-side import authorization.');
        } catch (FilaNoImportable $error) {
            $this->assertStringContainsString('autorización de procesamiento válida', $error->getMessage());
        }
        $this->assertDatabaseHas('casos', ['id' => $caseId, 'cartera_id' => $disabled->id]);
    }

    public function test_global_administrator_can_delete_mandante_with_projects_while_preserving_other_tenants(): void
    {
        $project = $this->crearProyectoCobranza();
        $caseId = $this->crearCasoEn($project);
        $other = $this->crearProyectoCobranza();
        Livewire::actingAs($this->crearAdminGlobal())->test(AdminMandantes::class)
            ->call('eliminar', (int) $project->mandante_id)->assertHasNoErrors();
        $this->assertDatabaseHas('mandantes', ['id' => $project->mandante_id, 'activo' => false]);
        $this->assertNotNull(DB::table('mandantes')->where('id', $project->mandante_id)->value('eliminada_en'));
        $this->assertNotNull(DB::table('proyectos')->where('id', $project->id)->value('eliminada_en'));
        $this->assertSame(0, DB::table('carteras')->where('proyecto_id', $project->id)->where('activo', true)->count());
        $this->assertDatabaseHas('casos', ['id' => $caseId, 'eliminada_en' => null]);
        $this->assertDatabaseHas('proyectos', ['id' => $other->id, 'activo' => true, 'eliminada_en' => null]);
        $this->get(route('proyectos.carteras', ['proyecto_id' => $project->id]))->assertNotFound();
    }

    public function test_supervisor_gets_independent_delete_permission_in_one_project_and_revocation_takes_effect(): void
    {
        $project = $this->crearProyectoCobranza();
        $other = $this->crearProyectoCobranza();
        $portfolio = $this->crearCarteraEn($project);
        $second = $this->crearCarteraEn($project);
        $foreign = $this->crearCarteraEn($other);
        $admin = $this->crearAdminGlobal();
        $supervisor = $this->crearSupervisor($project);
        $this->actingAs($admin);
        $roleId = app(CrearRolCustom::class)->execute(new EntradaRolCustom((int) $project->id,
            'DELETE_PORTFOLIOS', 'Eliminar carteras', null, ['carteras.ver', 'carteras.eliminar']), (int) $admin->id);
        DB::table('usuario_proyecto_rol_custom')->insert([
            'proyecto_id' => $project->id, 'usuario_id' => $supervisor->id, 'rol_custom_id' => $roleId, 'activo' => true,
        ]);
        $this->actingAs($supervisor);
        $this->get(route('proyectos.carteras', ['proyecto_id' => $project->id]))->assertOk();
        $component = Livewire::test(PasoCarteras::class, ['proyecto' => ProyectoModel::findOrFail($project->id)])
            ->assertViewHas('puedeEditar', false)->assertViewHas('puedeEliminar', true);
        $component->call('eliminarCartera', (int) $portfolio->id)->assertHasNoErrors();
        $this->assertNotNull(DB::table('carteras')->where('id', $portfolio->id)->value('eliminada_en'));
        Livewire::test(PasoCarteras::class, ['proyecto' => ProyectoModel::findOrFail($project->id)])
            ->call('eliminarCartera', (int) $foreign->id)->assertNotFound();
        $this->assertDatabaseHas('carteras', ['id' => $foreign->id, 'eliminada_en' => null, 'activo' => true]);
        DB::table('usuario_proyecto_rol_custom')->where('proyecto_id', $project->id)->where('usuario_id', $supervisor->id)->update(['activo' => false]);
        $component->call('eliminarCartera', (int) $second->id)->assertForbidden();
        $this->assertDatabaseHas('carteras', ['id' => $second->id, 'eliminada_en' => null]);
    }
}
