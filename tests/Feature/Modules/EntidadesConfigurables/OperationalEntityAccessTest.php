<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\EntidadesConfigurables;

use App\Modules\EntidadesConfigurables\Application\Services\ServicioEntidades;
use App\Modules\EntidadesConfigurables\Domain\ValueObjects\RelacionEntidad;
use App\Modules\EntidadesConfigurables\Infrastructure\Http\Livewire\GestorRegistrosEntidad;
use App\Modules\EntidadesConfigurables\Infrastructure\Http\Livewire\PanelEntidadesVinculadas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class OperationalEntityAccessTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_definition_cannot_reference_a_portfolio_owned_by_another_project(): void
    {
        $project = $this->crearProyectoCobranza();
        $other = $this->crearProyectoCobranza();
        $portfolio = $this->crearCarteraEn($other);
        $this->expectException(\DomainException::class);
        app(ServicioEntidades::class)->crearEntidad($project->id, 'INVALID', 'Invalid', carteraId: $portfolio->id);
    }

    public function test_embedded_panel_cannot_switch_to_another_project_during_hydration(): void
    {
        $project = $this->crearProyectoCobranza();
        $other = $this->crearProyectoCobranza();
        $case = $this->crearCasoEn($project);
        $this->activarProyecto($project);
        $this->actingAs($this->crearAdminGlobal());
        $panel = Livewire::test(PanelEntidadesVinculadas::class, [
            'proyectoId' => $project->id, 'vinculo' => 'caso', 'vinculoId' => $case,
        ]);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $panel->set('proyectoId', $other->id);
    }

    public function test_panel_rejects_editing_or_deleting_a_record_from_another_account(): void
    {
        $project = $this->crearProyectoCobranza();
        $one = $this->crearCasoEn($project);
        $two = $this->crearCasoEn($project);
        $service = app(ServicioEntidades::class);
        $entity = $service->crearEntidad($project->id, 'DETAIL', 'Detalle', RelacionEntidad::CASO);
        $record = $service->crearRegistro($project->id, $entity, 'Other account', [], casoId: $two);
        $this->activarProyecto($project);
        $this->actingAs($this->crearSupervisor($project));
        $props = ['proyectoId' => $project->id, 'vinculo' => 'caso', 'vinculoId' => $one];
        Livewire::test(PanelEntidadesVinculadas::class, $props)->call('abrirFormEditar', $entity, $record)->assertNotFound();
        Livewire::test(PanelEntidadesVinculadas::class, $props)->call('eliminar', $record)->assertNotFound();
        $this->assertDatabaseHas('entidades_registros', ['id' => $record, 'titulo' => 'Other account', 'eliminado_en' => null]);
    }

    public function test_standalone_manager_cannot_read_linked_records_or_mutate_another_definition(): void
    {
        $project = $this->crearProyectoCobranza();
        $service = app(ServicioEntidades::class);
        $one = $service->crearEntidad($project->id, 'ONE', 'Primera');
        $two = $service->crearEntidad($project->id, 'TWO', 'Segunda');
        $linked = $service->crearEntidad($project->id, 'LINKED', 'Vinculada', RelacionEntidad::CASO);
        $record = $service->crearRegistro($project->id, $two, 'Private record', []);
        $this->activarProyecto($project);
        $this->actingAs($this->crearSupervisor($project));
        Livewire::test(GestorRegistrosEntidad::class, ['proyectoId' => $project->id, 'entidadId' => $linked])->assertNotFound();
        $props = ['proyectoId' => $project->id, 'entidadId' => $one];
        Livewire::test(GestorRegistrosEntidad::class, $props)->call('abrirFormEditar', $record)->assertNotFound();
        Livewire::test(GestorRegistrosEntidad::class, $props)->call('eliminar', $record)->assertNotFound();
        $this->assertDatabaseHas('entidades_registros', ['id' => $record, 'eliminado_en' => null]);
    }

    public function test_portfolio_scope_applies_to_embedded_panels_and_direct_entity_pages(): void
    {
        $project = $this->crearProyectoCobranza();
        $allowed = $this->crearCarteraEn($project);
        $denied = $this->crearCarteraEn($project);
        $case = $this->crearCasoEn($project, ['cartera' => $denied]);
        $entity = app(ServicioEntidades::class)->crearEntidad($project->id, 'RESTRICTED', 'Restricted catalog', carteraId: $denied->id);
        $user = $this->crearSupervisor($project);
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $user->id, 'proyecto_id' => $project->id,
            'rol_id' => DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'), 'cartera_id' => $allowed->id,
        ]);
        $this->activarProyecto($project);
        $this->actingAs($user);
        Livewire::test(PanelEntidadesVinculadas::class, [
            'proyectoId' => $project->id, 'vinculo' => 'caso', 'vinculoId' => $case,
        ])->assertForbidden();
        $this->get(route('proyectos.entidades.registros', ['proyecto_id' => $project->id, 'entidad_id' => $entity]))->assertForbidden();
    }

    public function test_open_form_cannot_save_or_delete_after_its_definition_is_archived(): void
    {
        $project = $this->crearProyectoCobranza();
        $service = app(ServicioEntidades::class);
        $entity = $service->crearEntidad($project->id, 'CATALOG', 'Catálogo');
        $record = $service->crearRegistro($project->id, $entity, 'Original', []);
        $this->activarProyecto($project);
        $this->actingAs($this->crearSupervisor($project));
        $form = Livewire::test(GestorRegistrosEntidad::class, ['proyectoId' => $project->id, 'entidadId' => $entity])
            ->call('abrirFormEditar', $record)->set('titulo', 'Changed');
        $service->eliminarEntidad($project->id, $entity);
        $form->call('guardar')->assertNotFound();
        try {
            $service->eliminarRegistro($project->id, $record);
            $this->fail('Archived definition records must remain unchanged.');
        } catch (\RuntimeException $error) {
            $this->assertNotEmpty($error->getMessage());
        }
        $this->assertDatabaseHas('entidades_registros', ['id' => $record, 'titulo' => 'Original', 'eliminado_en' => null]);
    }

    public function test_account_archive_closes_existing_panels_and_all_service_mutations(): void
    {
        $project = $this->crearProyectoCobranza();
        $portfolio = $this->crearCarteraEn($project);
        $case = $this->crearCasoEn($project, ['cartera' => $portfolio]);
        $service = app(ServicioEntidades::class);
        $entity = $service->crearEntidad($project->id, 'ACCOUNT_DATA', 'Cuenta', RelacionEntidad::CASO);
        $record = $service->crearRegistro($project->id, $entity, 'Original', [], casoId: $case);
        $this->activarProyecto($project);
        $this->actingAs($this->crearSupervisor($project));
        $panel = Livewire::test(PanelEntidadesVinculadas::class, [
            'proyectoId' => $project->id, 'vinculo' => 'caso', 'vinculoId' => $case,
        ])->call('abrirFormEditar', $entity, $record)->set('titulo', 'Changed');
        DB::table('carteras')->where('id', $portfolio->id)->update(['activo' => false, 'eliminada_en' => now()]);
        $panel->call('guardar')->assertNotFound();
        foreach ([
            fn () => $service->crearRegistro($project->id, $entity, 'New', [], casoId: $case),
            fn () => $service->actualizarRegistro($project->id, $entity, $record, 'Changed', []),
            fn () => $service->eliminarRegistro($project->id, $record),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Archived account records must remain unchanged.');
            } catch (\DomainException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
        $this->assertDatabaseCount('entidades_registros', 1);
        $this->assertDatabaseHas('entidades_registros', ['id' => $record, 'titulo' => 'Original', 'eliminado_en' => null]);
    }
}
