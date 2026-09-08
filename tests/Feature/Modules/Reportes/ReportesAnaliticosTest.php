<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use App\Modules\Reportes\Infrastructure\Http\Livewire\DashboardAnalitico;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ReportesAnaliticosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_accede_ruta_analiticos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        $this->actingAs($supervisor)
            ->get(route('proyectos.reportes.analiticos', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(200);
    }

    public function test_gestor_recibe_403_en_analiticos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.reportes.analiticos', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(403);
    }

    public function test_componente_render_con_datos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['persona' => $persona]);
        $cascada = $this->crearCascadaGestionEn($proyecto);
        $gestor = $this->crearGestor($proyecto);

        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: (int) $proyecto->id,
            casoId: $casoId,
            personaId: (int) $persona->id,
            contactoId: null,
            canalId: $cascada['canal_id'],
            tipoGestionId: $cascada['tipo_gestion_id'],
            resultadoId: $cascada['resultado_id'],
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: (int) $gestor->id,
            notas: null,
            duracion: new DuracionSegundos(120),
            creadaEn: new DateTimeImmutable('now'),
        ));

        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        Livewire::test(DashboardAnalitico::class)
            ->assertViewHas('distribucionCasos')
            ->assertViewHas('compromisosPorEstado')
            ->assertViewHas('efectividadPorResultado');
    }
}
