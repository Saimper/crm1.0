<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Compromisos;

use App\Modules\Cobranza\Infrastructure\Http\Livewire\ResolverPromesa;
use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Compromisos\Application\UseCases\CancelarCompromiso;
use App\Modules\Compromisos\Application\UseCases\MarcarCompromisoCumplido;
use App\Modules\Compromisos\Application\UseCases\MarcarCompromisoRoto;
use App\Modules\Compromisos\Infrastructure\Http\Livewire\EditarCompromiso;
use App\Modules\Cx\Infrastructure\Http\Livewire\ResolverResolucion;
use App\Modules\Servicio\Infrastructure\Http\Livewire\ResolverAccion;
use App\Modules\Venta\Infrastructure\Http\Livewire\ResolverCierre;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ArchivedCommitmentWritesTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_all_common_resolvers_refuse_archived_account_writes(): void
    {
        [$id] = $this->archivedCommitment();
        foreach ([MarcarCompromisoCumplido::class, MarcarCompromisoRoto::class, CancelarCompromiso::class] as $action) {
            try {
                app($action)->execute(new ResolverCompromisoInput($id, new DateTimeImmutable));
                $this->fail('An archived commitment must remain unchanged.');
            } catch (DomainException $error) {
                $this->assertStringContainsString('archivada', $error->getMessage());
            }
        }
        $this->assertDatabaseHas('compromisos', ['id' => $id, 'estado' => 'pendiente', 'fecha_resolucion' => null]);
    }

    public function test_stale_resolution_forms_and_editor_are_closed_after_archive(): void
    {
        [$id, $publicId] = $this->archivedCommitment();
        foreach ([ResolverPromesa::class => 'cumplida', ResolverResolucion::class => 'cumplida', ResolverAccion::class => 'ejecutada', ResolverCierre::class => 'ganado'] as $component => $action) {
            Livewire::test($component, ['compromisoId' => $id])->set('accion', $action)->call('confirmar')->assertStatus(409);
        }
        Livewire::test(EditarCompromiso::class, ['compromiso' => $publicId])->assertStatus(409);
        $this->assertDatabaseHas('compromisos', ['id' => $id, 'estado' => 'pendiente']);
    }

    public function test_scheduled_expiry_preserves_archived_commitment_state(): void
    {
        [$id] = $this->archivedCommitment();
        $this->artisan('compromisos:romper-vencidos')->assertSuccessful();
        $this->assertDatabaseHas('compromisos', ['id' => $id, 'estado' => 'pendiente']);
    }

    /** @return array{int, string} */
    private function archivedCommitment(): array
    {
        $project = $this->crearProyectoCobranza();
        $portfolio = $this->crearCarteraEn($project);
        $caseId = $this->crearCasoEn($project, ['cartera' => $portfolio]);
        $user = $this->crearSupervisor($project);
        $this->actingAs($user);
        $this->activarProyecto($project);
        $publicId = (string) Str::ulid();
        $id = (int) DB::table('compromisos')->insertGetId([
            'public_id' => $publicId, 'proyecto_id' => $project->id, 'caso_id' => $caseId,
            'tipo_compromiso' => 'promesa_pago', 'estado' => 'pendiente',
            'fecha_vencimiento' => now()->subDays(2)->toDateString(), 'usuario_id' => $user->id,
            'creada_en' => now()->subDays(4), 'actualizada_en' => now()->subDays(4),
        ]);
        DB::table('carteras')->where('id', $portfolio->id)->update(['activo' => false, 'eliminada_en' => now()]);

        return [$id, $publicId];
    }
}
