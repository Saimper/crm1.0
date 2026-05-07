<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DesnormalizacionDesdeGestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_registrar_gestion_actualiza_desnormalizados_del_caso(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'CONSUMO')->value('id');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estadoAbiertoId = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', 'ABIERTO')->value('id');

        $usuarioId = (int) DB::table('users')->insertGetId([
            'name' => 'Tester', 'email' => 'tester.'.Str::random(6).'@crm.local',
            'password' => bcrypt('x'), 'activo' => true,
        ]);
        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'T', 'apellidos' => 'U',
        ]);
        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'cartera_id' => $carteraId, 'persona_id' => $personaId,
            'tipo_caso' => 'cobranza', 'estado_caso_id' => $estadoAbiertoId,
            'fecha_ingreso' => '2026-04-17', 'prioridad' => 100,
        ]);

        $this->assertNull(DB::table('casos')->where('id', $casoId)->value('fecha_ultima_gestion'));

        $resultadoId = (int) DB::table('resultados')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'CONTACTO_TITULAR')->value('id');

        $fechaGestion = new DateTimeImmutable('2026-04-17 10:30:00');
        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $proyectoId,
            casoId: $casoId,
            personaId: $personaId,
            contactoId: null,
            canalId: (int) DB::table('canales')->where('codigo', 'TELEFONO')->value('id'),
            tipoGestionId: (int) DB::table('tipos_gestion')->where('proyecto_id', $proyectoId)->where('codigo', 'LLAMADA_SALIENTE')->value('id'),
            resultadoId: $resultadoId,
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: $usuarioId,
            notas: null,
            duracion: new DuracionSegundos(120),
            creadaEn: $fechaGestion,
        ));

        $caso = DB::table('casos')->where('id', $casoId)->first();
        $this->assertNotNull($caso->fecha_ultima_gestion);
        $this->assertSame($resultadoId, (int) $caso->resultado_ultima_gestion_id);
        $this->assertSame($usuarioId, (int) $caso->usuario_ultima_gestion_id);
    }
}
