<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\InsertaCti;
use Tests\TestCase;

final class ExportsAdicionalesTest extends TestCase
{
    use InsertaCti;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_export_casos_devuelve_csv_con_cabeceras_y_datos(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearCasoEn($proyecto);
        $supervisor = $this->crearSupervisor($proyecto);

        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.exportar-casos', ['proyecto_id' => $proyecto->id]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $contenido = $response->streamedContent();
        $this->assertStringContainsString('caso_public_id', $contenido);
        $this->assertStringContainsString('cobranza', $contenido);
    }

    public function test_export_gestiones_devuelve_csv(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['persona' => $persona]);
        $cascada = $this->crearCascadaGestionEn($proyecto);
        $gestor = $this->crearGestor($proyecto);
        $supervisor = $this->crearSupervisor($proyecto);

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
            creadaEn: new DateTimeImmutable('2026-04-17 10:30:00'),
        ));

        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.exportar-gestiones', ['proyecto_id' => $proyecto->id]));

        $response->assertStatus(200)->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('gestion_public_id', $response->streamedContent());
    }

    public function test_export_compromisos_devuelve_csv(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->insertarCompromisoPromesaPagoConTipoPago($proyecto, $this->crearTipoPagoEn($proyecto));
        $supervisor = $this->crearSupervisor($proyecto);

        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.exportar-compromisos', ['proyecto_id' => $proyecto->id]));

        $response->assertStatus(200)->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('compromiso_public_id', $response->streamedContent());
    }

    public function test_gestor_sin_permiso_recibe_403_en_exports(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);

        $this->actingAs($gestor)
            ->get(route('proyectos.importaciones.exportar-casos', ['proyecto_id' => $proyecto->id]))
            ->assertStatus(403);
    }
}
