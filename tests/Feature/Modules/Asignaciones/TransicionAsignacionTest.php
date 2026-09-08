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
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class TransicionAsignacionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
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
            canalId: $ctx['cascada']['canal_id'],
            tipoGestionId: $ctx['cascada']['tipo_gestion_id'],
            resultadoId: $ctx['cascada']['resultado_id'],
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

    /** @param array{proyectoId:int,casoId:int,personaId:int,usuarioId:int,cascada:array<string,int>} $ctx */
    private function registrarAsignacion(array $ctx): int
    {
        $campanaId = (int) DB::table('campanas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $ctx['proyectoId'],
            'codigo' => 'CAMP_'.strtoupper(Str::random(6)), 'nombre' => 'Camp Test',
            'estado' => 'activa', 'fecha_inicio' => '2026-04-01',
            'creada_en' => Carbon::now(), 'actualizada_en' => Carbon::now(),
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

    /** @return array{proyectoId:int,casoId:int,personaId:int,usuarioId:int,cascada:array<string,int>} */
    private function contexto(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, [
            'persona' => $persona,
            'fecha_ingreso' => '2026-04-17',
        ]);
        $cascada = $this->crearCascadaGestionEn($proyecto);
        $usuario = $this->crearGestor($proyecto);

        return [
            'proyectoId' => (int) $proyecto->id,
            'casoId' => $casoId,
            'personaId' => (int) $persona->id,
            'usuarioId' => (int) $usuario->id,
            'cascada' => $cascada,
        ];
    }
}
