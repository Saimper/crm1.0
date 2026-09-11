<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notificaciones;

use App\Modules\Compromisos\Domain\Events\CompromisoRoto;
use App\Modules\Notificaciones\Application\Listeners\NotificarCompromisoRoto;
use App\Modules\Notificaciones\Application\Services\GeneradorNotificaciones;
use App\Modules\Notificaciones\Infrastructure\Http\Livewire\BadgeNotificaciones;
use App\Modules\Notificaciones\Infrastructure\Http\Livewire\ListadoNotificaciones;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ArchivedPortfolioNotificationsTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_archived_reminders_disappear_from_inbox_and_badge_but_are_preserved(): void
    {
        $project = $this->crearProyectoCx();
        $user = $this->crearGestor($project);
        $this->activarProyecto($project);
        $this->actingAs($user);
        $archivedCases = [];
        $portfolios = [];
        foreach (['active', 'archived', 'inactive'] as $status) {
            $portfolio = $this->crearCarteraEn($project);
            $caseId = $this->crearCasoEn($project, ['cartera' => $portfolio]);
            $portfolios[$status] = $portfolio->id;
            if ($status !== 'active') {
                $archivedCases[] = $caseId;
            }
            foreach (['yesterday', 'tomorrow'] as $deadline) {
                $this->commitment((int) $project->id, $caseId, (int) $user->id, $deadline);
            }
        }
        $generator = app(GeneradorNotificaciones::class);
        $this->assertSame(12, $generator->ejecutar());
        Livewire::test(BadgeNotificaciones::class)->assertSet('noLeidas', 12);

        DB::table('carteras')->where('id', $portfolios['archived'])->update(['eliminada_en' => now()]);
        DB::table('carteras')->where('id', $portfolios['inactive'])->update(['activo' => false]);
        Livewire::test(BadgeNotificaciones::class)->assertSet('noLeidas', 4);
        Livewire::test(ListadoNotificaciones::class)
            ->assertViewHas('totalNoLeidas', 4)
            ->assertViewHas('notificaciones', fn ($rows) => $rows->total() === 4)
            ->call('marcarTodasLeidas');
        $this->assertSame(12, DB::table('notificaciones')->count());
        $this->assertSame(8, DB::table('notificaciones')->whereNull('leida_en')->count());
        Livewire::test(BadgeNotificaciones::class)->assertSet('noLeidas', 0);

        foreach ($archivedCases as $caseId) {
            $this->commitment((int) $project->id, $caseId, (int) $user->id, 'tomorrow');
        }
        $this->assertSame(0, $generator->ejecutar());
        $this->assertSame(12, DB::table('notificaciones')->count());
    }

    public function test_late_resolution_event_does_not_create_an_archived_reminder(): void
    {
        $project = $this->crearProyectoCx();
        $user = $this->crearGestor($project);
        $portfolio = $this->crearCarteraEn($project);
        $caseId = $this->crearCasoEn($project, ['cartera' => $portfolio]);
        $commitmentId = $this->commitment((int) $project->id, $caseId, (int) $user->id, 'yesterday');
        DB::table('carteras')->where('id', $portfolio->id)->update(['eliminada_en' => now()]);
        app(NotificarCompromisoRoto::class)->handle(new CompromisoRoto(
            compromisoId: $commitmentId, proyectoId: (int) $project->id, casoId: $caseId,
            usuarioId: (int) $user->id, fechaResolucion: new \DateTimeImmutable, quedanCompromisosVigentesEnCaso: false,
        ));
        $this->assertSame(0, DB::table('notificaciones')->count());
    }

    public function test_account_metadata_is_filtered_for_other_notification_types_too(): void
    {
        $project = $this->crearProyectoCobranza();
        $user = $this->crearGestor($project);
        $this->activarProyecto($project);
        $this->actingAs($user);
        foreach (['active', 'archived', 'batch'] as $index => $status) {
            $metadata = ['cantidad' => 1];
            if ($status !== 'batch') {
                $portfolio = $this->crearCarteraEn($project);
                $metadata['caso_id'] = $this->crearCasoEn($project, ['cartera' => $portfolio]);
                if ($status === 'archived') {
                    DB::table('carteras')->where('id', $portfolio->id)->update(['eliminada_en' => now()]);
                }
            }
            DB::table('notificaciones')->insert([
                'public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id, 'destinatario_usuario_id' => $user->id,
                'entidad_tipo' => 'asignaciones_batch', 'entidad_id' => $index + 1, 'tipo' => 'asignacion_recibida',
                'titulo' => $status, 'metadata' => json_encode($metadata), 'creada_en' => now(),
            ]);
        }
        Livewire::test(BadgeNotificaciones::class)->assertSet('noLeidas', 2);
        Livewire::test(ListadoNotificaciones::class)->assertViewHas('totalNoLeidas', 2)
            ->assertViewHas('notificaciones', fn ($rows) => $rows->total() === 2 && ! $rows->contains('titulo', 'archived'));
        $this->assertSame(3, DB::table('notificaciones')->count());
    }

    private function commitment(int $projectId, int $caseId, int $userId, string $deadline): int
    {
        $id = (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $projectId, 'caso_id' => $caseId,
            'usuario_id' => $userId, 'estado' => 'pendiente', 'tipo_compromiso' => 'resolucion_ticket',
            'fecha_vencimiento' => ($deadline === 'yesterday' ? now()->subDay() : now()->addDay())->toDateString(),
        ]);
        DB::table('compromisos_resolucion_ticket')->insert([
            'proyecto_id' => $projectId, 'compromiso_id' => $id,
            'accion_comprometida' => 'Resolver solicitud', 'fecha_limite_sla' => now()->addHours(2),
        ]);

        return $id;
    }
}
