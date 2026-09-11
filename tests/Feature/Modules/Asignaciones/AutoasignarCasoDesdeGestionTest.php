<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Asignaciones;

use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * Cuando un asesor gestiona una cuenta sin dueño, la cuenta pasa a ser suya.
 *
 * Antes, gestionar una cuenta encontrada por búsqueda no dejaba rastro en la
 * bandeja de nadie: el asesor registraba una promesa y al día siguiente no tenía
 * forma de saber que le tocaba seguirla.
 */
final class AutoasignarCasoDesdeGestionTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** @return array<string, mixed> */
    private function escenario(bool $permite): array
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        DB::table('proyectos')->where('id', $proyecto->id)->update(['permite_autoasignacion' => $permite]);

        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto);
        $gestor = $this->crearGestor($proyecto);

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => '2026-09-01',
        ]);

        return compact('proyecto', 'casoId', 'persona', 'gestor');
    }

    /** @param array<string, mixed> $ctx */
    private function gestionar(array $ctx): void
    {
        $proyecto = $ctx['proyecto'];

        $tipoGestionId = (int) DB::table('tipos_gestion')->insertGetId([
            'proyecto_id' => $proyecto->id, 'codigo' => 'LLAMADA_'.Str::random(4), 'nombre' => 'Llamada',
        ]);
        $resultadoId = (int) DB::table('resultados')->insertGetId([
            'proyecto_id' => $proyecto->id, 'codigo' => 'CONTACTO_'.Str::random(4), 'nombre' => 'Contacto',
        ]);

        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: (int) $proyecto->id,
            casoId: $ctx['casoId'],
            personaId: (int) $ctx['persona']->id,
            contactoId: null,
            canalId: (int) DB::table('canales')->value('id'),
            tipoGestionId: $tipoGestionId,
            resultadoId: $resultadoId,
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: (int) $ctx['gestor']->id,
            notas: 'Prueba',
            duracion: new DuracionSegundos(60),
            creadaEn: new DateTimeImmutable,
        ));
    }

    private function asignacionesDe(int $casoId): int
    {
        return DB::table('asignaciones')->where('caso_id', $casoId)->count();
    }

    public function test_gestionar_una_cuenta_sin_duenio_la_asigna_al_asesor(): void
    {
        $ctx = $this->escenario(permite: true);

        $this->gestionar($ctx);

        $this->assertDatabaseHas('asignaciones', [
            'caso_id' => $ctx['casoId'],
            'usuario_id' => (int) $ctx['gestor']->id,
        ]);
    }

    public function test_si_el_proyecto_no_lo_permite_no_se_asigna_nada(): void
    {
        $ctx = $this->escenario(permite: false);

        $this->gestionar($ctx);

        $this->assertSame(0, $this->asignacionesDe($ctx['casoId']));
    }

    public function test_una_cuenta_con_duenio_no_se_le_quita_a_su_asesor(): void
    {
        $ctx = $this->escenario(permite: true);
        $otro = $this->crearGestor($ctx['proyecto']);

        DB::table('asignaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => (int) $ctx['proyecto']->id,
            'caso_id' => $ctx['casoId'],
            'usuario_id' => (int) $otro->id,
            'fecha_asignacion' => '2026-09-01',
        ]);

        DB::table('rol_proyecto_permiso')->insert([
            'proyecto_id' => $ctx['proyecto']->id,
            'rol_id' => DB::table('roles')->where('codigo', 'GESTOR')->value('id'),
            'permiso_id' => DB::table('permisos')->where('codigo', 'casos.colaborar')->value('id'),
            'permitido' => true,
        ]);

        $this->gestionar($ctx);

        $this->assertSame(
            (int) $otro->id,
            (int) DB::table('asignaciones')->where('caso_id', $ctx['casoId'])->value('usuario_id'),
            'Robar una cuenta en silencio es peor que no asignarla.'
        );
        $this->assertSame(1, $this->asignacionesDe($ctx['casoId']));
        $this->assertDatabaseHas('gestiones', [
            'proyecto_id' => $ctx['proyecto']->id, 'caso_id' => $ctx['casoId'], 'usuario_id' => $ctx['gestor']->id,
        ]);
    }

    public function test_managing_another_owner_without_cooperation_is_rejected_without_writes(): void
    {
        $ctx = $this->escenario(permite: true);
        $owner = $this->crearGestor($ctx['proyecto']);
        DB::table('asignaciones')->insert([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $ctx['proyecto']->id,
            'caso_id' => $ctx['casoId'], 'usuario_id' => $owner->id, 'fecha_asignacion' => '2026-09-01',
        ]);

        try {
            $this->gestionar($ctx);
            self::fail('Cooperation must be explicitly granted to manage another advisor’s account.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('No tienes permiso para gestionar esta cuenta', $exception->getMessage());
            $this->assertDatabaseMissing('gestiones', ['proyecto_id' => $ctx['proyecto']->id, 'caso_id' => $ctx['casoId']]);
            $this->assertDatabaseHas('asignaciones', ['proyecto_id' => $ctx['proyecto']->id, 'caso_id' => $ctx['casoId'], 'usuario_id' => $owner->id]);
            $this->assertSame(1, $this->asignacionesDe($ctx['casoId']));
        }
    }

    public function test_la_gestion_queda_registrada_aunque_no_se_pueda_asignar(): void
    {
        $ctx = $this->escenario(permite: false);

        $this->gestionar($ctx);

        $this->assertSame(
            1,
            DB::table('gestiones')->where('caso_id', $ctx['casoId'])->count(),
            'La gestión es el hecho de negocio; la asignación es una comodidad.'
        );
    }
}
