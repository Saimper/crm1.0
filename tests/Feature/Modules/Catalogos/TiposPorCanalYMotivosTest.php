<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalogos;

use App\Modules\Casos\Infrastructure\Http\Livewire\NuevaGestion;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\Contracts\ConsultaTiposPorCanal;
use App\Modules\Tenancy\Application\UseCases\ConfigurarCanalesTipoGestion;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoCatalogosTipo;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoResultados;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoTiposGestion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class TiposPorCanalYMotivosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_channel_selection_filters_types_and_resets_dependent_choices(): void
    {
        $project = $this->crearProyectoCobranza();
        $catalog = $this->crearCascadaGestionEn($project);
        $other = $this->crearCascadaGestionEn($project);
        $whatsapp = (int) DB::table('canales')->where('codigo', 'WHATSAPP')->value('id');
        $this->activarProyecto($project);
        $this->actingAs($this->crearAdminGlobal());
        $model = ProyectoModel::findOrFail($project->id);
        Livewire::test(PasoTiposGestion::class, ['proyecto' => $model])
            ->call('abrirFormEditar', $catalog['tipo_gestion_id'])
            ->set('form.canales', [(string) $catalog['canal_id']])->call('guardar')->assertHasNoErrors();
        app(ConfigurarCanalesTipoGestion::class)->execute((int) $project->id, $other['tipo_gestion_id'], [$whatsapp]);
        $caseId = $this->crearCasoEn($project);
        $personId = (int) DB::table('casos')->where('id', $caseId)->value('persona_id');
        $form = Livewire::test(NuevaGestion::class, ['casoId' => $caseId, 'personaId' => $personId, 'tipoCaso' => 'cobranza'])
            ->set('canalId', $catalog['canal_id'])
            ->assertViewHas('tiposGestion', fn ($types) => $types->pluck('id')->all() === [$catalog['tipo_gestion_id']])
            ->set('tipoGestionId', $catalog['tipo_gestion_id'])->set('resultadoId', $catalog['resultado_id'])
            ->set('motivoNoContactoId', $catalog['motivo_no_contacto_id'])
            ->set('canalId', $whatsapp)->assertSet('tipoGestionId', null)->assertSet('resultadoId', null)
            ->assertSet('motivoNoContactoId', null)
            ->assertViewHas('tiposGestion', fn ($types) => $types->pluck('id')->all() === [$other['tipo_gestion_id']]);
        $form->set('tipoGestionId', $catalog['tipo_gestion_id'])->set('resultadoId', $catalog['resultado_id'])
            ->call('guardar')->assertHasErrors('tipoGestionId');
        $this->assertDatabaseCount('gestiones', 0);
    }

    public function test_multiple_channels_and_unrestricted_legacy_types_respect_project_isolation_and_inactive_types(): void
    {
        $project = $this->crearProyectoCobranza();
        $other = $this->crearProyectoCobranza();
        $catalog = $this->crearCascadaGestionEn($project);
        $foreign = $this->crearCascadaGestionEn($other);
        $ids = DB::table('canal_proyecto')->where('proyecto_id', $project->id)->limit(2)->pluck('canal_id')->map(fn ($id): int => (int) $id)->all();
        $query = app(ConsultaTiposPorCanal::class);
        $this->assertContains($catalog['tipo_gestion_id'], $query->idsAdmitidos((int) $project->id, $ids[1]));
        $this->assertNotContains($foreign['tipo_gestion_id'], $query->idsAdmitidos((int) $project->id, $ids[1]));
        app(ConfigurarCanalesTipoGestion::class)->execute((int) $project->id, $catalog['tipo_gestion_id'], $ids);
        foreach ($ids as $id) {
            $this->assertContains($catalog['tipo_gestion_id'], $query->idsAdmitidos((int) $project->id, $id));
        }
        DB::table('tipos_gestion')->where('id', $catalog['tipo_gestion_id'])->update(['activo' => false]);
        $this->assertSame([], $query->idsAdmitidos((int) $project->id, $ids[0]));
        $this->expectException(ValidationException::class);
        app(ConfigurarCanalesTipoGestion::class)->execute((int) $project->id, $foreign['tipo_gestion_id'], $ids);
    }

    public function test_only_explicit_no_contact_flag_enables_reasons_and_reasons_are_editable_in_catalogs(): void
    {
        $project = $this->crearProyectoCobranza();
        $catalog = $this->crearCascadaGestionEn($project, ['es_contacto_efectivo' => false]);
        $caseId = $this->crearCasoEn($project);
        $personId = (int) DB::table('casos')->where('id', $caseId)->value('persona_id');
        $this->activarProyecto($project);
        $this->actingAs($this->crearAdminGlobal());
        $form = Livewire::test(NuevaGestion::class, ['casoId' => $caseId, 'personaId' => $personId, 'tipoCaso' => 'cobranza'])
            ->set('canalId', $catalog['canal_id'])->set('tipoGestionId', $catalog['tipo_gestion_id'])
            ->set('resultadoId', $catalog['resultado_id'])->assertDontSeeHtml('id="gestion-reason"');
        Livewire::test(PasoResultados::class, ['proyecto' => ProyectoModel::findOrFail($project->id)])
            ->call('abrirFormEditar', $catalog['resultado_id'])->set('form.es_no_contactado', true)->call('guardar')->assertHasNoErrors();
        $form->call('$refresh')->assertSeeHtml('id="gestion-reason"')
            ->set('motivoNoContactoId', $catalog['motivo_no_contacto_id'])->call('guardar')->assertHasNoErrors();
        $this->assertDatabaseHas('gestiones', ['caso_id' => $caseId, 'motivo_no_contacto_id' => $catalog['motivo_no_contacto_id']]);
        Livewire::test(PasoCatalogosTipo::class, ['proyecto' => ProyectoModel::findOrFail($project->id)])
            ->call('cambiarTab', 'motivos_no_contacto')->assertSee('Motivo');
    }

    public function test_backend_rejects_channel_mismatch_and_foreign_reason_even_without_livewire(): void
    {
        $project = $this->crearProyectoCobranza();
        $other = $this->crearProyectoCobranza();
        $catalog = $this->crearCascadaGestionEn($project, ['es_contacto_efectivo' => false]);
        $foreign = $this->crearCascadaGestionEn($other);
        $caseId = $this->crearCasoEn($project);
        $personId = (int) DB::table('casos')->where('id', $caseId)->value('persona_id');
        $user = $this->crearGestor($project);
        $whatsapp = (int) DB::table('canales')->where('codigo', 'WHATSAPP')->value('id');
        app(ConfigurarCanalesTipoGestion::class)->execute((int) $project->id, $catalog['tipo_gestion_id'], [$whatsapp]);
        $input = fn (int $channel, ?int $reason) => new RegistrarGestionInput((string) Str::ulid(), (int) $project->id, $caseId,
            $personId, null, $channel, $catalog['tipo_gestion_id'], $catalog['resultado_id'], $reason,
            null, (int) $user->id, null, null, new DateTimeImmutable);
        try {
            app(RegistrarGestion::class)->execute($input($catalog['canal_id'], null));
            $this->fail('Channel mismatch accepted.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('canal', $e->getMessage());
        }
        DB::table('resultados')->where('id', $catalog['resultado_id'])->update(['es_no_contactado' => true]);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('motivo');
        app(RegistrarGestion::class)->execute($input($whatsapp, $foreign['motivo_no_contacto_id']));
    }
}
