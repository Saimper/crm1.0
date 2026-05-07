<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Asignaciones;

use App\Modules\Asignaciones\Application\DTOs\RegistrarAsignacionInput;
use App\Modules\Asignaciones\Application\UseCases\CerrarAsignacion;
use App\Modules\Asignaciones\Application\UseCases\RegistrarAsignacion;
use App\Modules\Asignaciones\Domain\Exceptions\TransicionAsignacionInvalida;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TransicionAsignacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_registrar_gestion_pasa_asignacion_pendiente_a_en_trabajo(): void
    {
        $ctx = $this->contexto();
        $asignacionId = $this->registrarAsignacion($ctx);

        $this->assertDatabaseHas('asignaciones', [
            'id' => $asignacionId, 'estado' => 'pendiente',
        ]);

        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: (int) DB::table('canales')->where('codigo', 'TELEFONO')->value('id'),
            tipoGestionId: (int) DB::table('tipos_gestion')->where('proyecto_id', $ctx['proyectoId'])->where('codigo', 'LLAMADA_SALIENTE')->value('id'),
            resultadoId: (int) DB::table('resultados')->where('proyecto_id', $ctx['proyectoId'])->where('codigo', 'CONTACTO_TITULAR')->value('id'),
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: $ctx['usuarioId'],
            notas: null,
            duracion: new DuracionSegundos(60),
            creadaEn: new DateTimeImmutable('2026-04-17 10:00:00'),
        ));

        $this->assertDatabaseHas('asignaciones', [
            'id' => $asignacionId, 'estado' => 'en_trabajo',
        ]);
    }

    public function test_cerrar_asignacion_cambia_estado(): void
    {
        $ctx = $this->contexto();
        $asignacionId = $this->registrarAsignacion($ctx);

        $this->app->make(CerrarAsignacion::class)->execute($asignacionId, new DateTimeImmutable('2026-04-20'));

        $row = DB::table('asignaciones')->where('id', $asignacionId)->first();
        $this->assertSame('cerrada', $row->estado);
        $this->assertNotNull($row->cerrada_en);
    }

    public function test_no_permite_cerrar_dos_veces(): void
    {
        $ctx = $this->contexto();
        $asignacionId = $this->registrarAsignacion($ctx);
        $useCase = $this->app->make(CerrarAsignacion::class);

        $useCase->execute($asignacionId, new DateTimeImmutable('2026-04-20'));

        $this->expectException(TransicionAsignacionInvalida::class);
        $useCase->execute($asignacionId, new DateTimeImmutable('2026-04-21'));
    }

    /** @param array{proyectoId:int,casoId:int,personaId:int,usuarioId:int} $ctx */
    private function registrarAsignacion(array $ctx): int
    {
        $campanaId = (int) DB::table('campanas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $ctx['proyectoId'],
            'codigo' => 'CAMP_TEST', 'nombre' => 'Camp Test',
            'estado' => 'activa', 'fecha_inicio' => '2026-04-01',
        ]);

        return $this->app->make(RegistrarAsignacion::class)->execute(new RegistrarAsignacionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            campanaId: $campanaId,
            casoId: $ctx['casoId'],
            usuarioId: $ctx['usuarioId'],
            fechaAsignacion: new DateTimeImmutable('2026-04-17'),
            prioridad: 100,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        ));
    }

    /** @return array{proyectoId:int,casoId:int,personaId:int,usuarioId:int} */
    private function contexto(): array
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
            'nombres' => 'Test', 'apellidos' => 'User',
        ]);

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'cartera_id' => $carteraId, 'persona_id' => $personaId,
            'tipo_caso' => 'cobranza', 'estado_caso_id' => $estadoAbiertoId,
            'fecha_ingreso' => '2026-04-17', 'prioridad' => 100,
        ]);

        return ['proyectoId' => $proyectoId, 'casoId' => $casoId, 'personaId' => $personaId, 'usuarioId' => $usuarioId];
    }
}
