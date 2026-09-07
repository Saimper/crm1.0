<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Casos\Infrastructure\Http\Livewire\NuevaGestion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Registrar una gestión exige `gestiones.crear`.
 *
 * Hasta ahora la única puerta era `can:casos.ver` en la ruta, y el AUDITOR la
 * tiene: podía escribir gestiones desde una pantalla de solo lectura. El
 * permiso existe desde F22 y nadie lo comprobaba en el camino de escritura
 * (§11, tercera capa).
 */
final class PermisoRegistrarGestionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_un_auditor_no_puede_registrar_una_gestion(): void
    {
        [$proyecto, $casoId] = $this->casoEn();
        $auditor = $this->crearAuditor($proyecto);
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyecto->id));

        $this->assertTrue($auditor->tienePermiso('casos.ver', (int) $proyecto->id));
        $this->assertFalse($auditor->tienePermiso('gestiones.crear', (int) $proyecto->id));

        Livewire::actingAs($auditor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => 1, 'tipoCaso' => 'cobranza'])
            ->set('canalId', (int) DB::table('canales')->value('id'))
            ->set('tipoGestionId', $this->catalogo($proyecto, 'tipos_gestion'))
            ->set('resultadoId', $this->catalogo($proyecto, 'resultados'))
            ->call('guardar')
            ->assertForbidden();

        $this->assertDatabaseCount('gestiones', 0);
    }

    public function test_un_gestor_si_puede_registrar_una_gestion(): void
    {
        [$proyecto, $casoId, $personaId] = $this->casoEn();
        $gestor = $this->crearGestor($proyecto);
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyecto->id));

        Livewire::actingAs($gestor)
            ->test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => $personaId, 'tipoCaso' => 'cobranza'])
            ->set('canalId', (int) DB::table('canales')->value('id'))
            ->set('tipoGestionId', $this->catalogo($proyecto, 'tipos_gestion'))
            ->set('resultadoId', $this->catalogo($proyecto, 'resultados'))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('gestiones', 1);
    }

    /** @return array{0: stdClass, 1: int, 2: int} */
    private function casoEn(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $persona = $this->crearPersonaEn($proyecto);

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => Carbon::today(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return [$proyecto, $casoId, (int) $persona->id];
    }

    private function catalogo(stdClass $proyecto, string $tabla): int
    {
        $id = DB::table($tabla)->where('proyecto_id', $proyecto->id)->value('id');

        return $id !== null ? (int) $id : (int) DB::table($tabla)->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => strtoupper(Str::random(8)),
            'nombre' => 'Catálogo de prueba',
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }
}
