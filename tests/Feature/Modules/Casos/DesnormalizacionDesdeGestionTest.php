<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class DesnormalizacionDesdeGestionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_registrar_gestion_actualiza_desnormalizados_del_caso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['persona' => $persona]);
        $cascada = $this->crearCascadaGestionEn($proyecto);
        $usuario = $this->crearGestor($proyecto);

        $this->assertNull(DB::table('casos')->where('id', $casoId)->value('fecha_ultima_gestion'));

        $fechaGestion = new DateTimeImmutable('2026-04-17 10:30:00');

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
            usuarioId: (int) $usuario->id,
            notas: null,
            duracion: new DuracionSegundos(120),
            creadaEn: $fechaGestion,
        ));

        $caso = DB::table('casos')->where('id', $casoId)->first();

        $this->assertNotNull($caso->fecha_ultima_gestion);
        $this->assertSame($cascada['resultado_id'], (int) $caso->resultado_ultima_gestion_id);
        $this->assertSame((int) $usuario->id, (int) $caso->usuario_ultima_gestion_id);
    }
}
