<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Gestiones;

use App\Modules\Cobranza\Domain\ValueObjects\DatosPromesaPago;
use App\Modules\Cobranza\Domain\ValueObjects\FechaPromesa;
use App\Modules\Cobranza\Domain\ValueObjects\MontoPromesa;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use App\Modules\Gestiones\Domain\Exceptions\CausaRequerida;
use App\Modules\Gestiones\Domain\Exceptions\PromesaRequerida;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use Database\Seeders\Casos\EstadosCasoDemoSeeder;
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
use Database\Seeders\Gestiones\CanalesSeeder;
use Database\Seeders\Gestiones\GestionesCatalogosDemoSeeder;
use Database\Seeders\Tenancy\CarterasDemoSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RegistrarGestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            MandantesDemoSeeder::class,
            ProyectosDemoSeeder::class,
            CarterasDemoSeeder::class,
            TiposIdentificacionSeeder::class,
            EstadosCasoDemoSeeder::class,
            CanalesSeeder::class,
            GestionesCatalogosDemoSeeder::class,
        ]);
    }

    public function test_registra_gestion_con_resultado_efectivo_sin_compromiso(): void
    {
        $ctx = $this->contexto();
        Event::fake([GestionRegistrada::class]);

        $output = $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $this->idGlobal('canales', 'TELEFONO'),
            tipoGestionId: $this->idProyecto('tipos_gestion', 'LLAMADA_SALIENTE', $ctx['proyectoId']),
            resultadoId: $this->idProyecto('resultados', 'CONTACTO_TITULAR', $ctx['proyectoId']),
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: $ctx['usuarioId'],
            notas: 'Cliente confirma recepción.',
            duracion: new DuracionSegundos(120),
            creadaEn: new DateTimeImmutable('2026-04-17 10:00:00'),
        ));

        $this->assertGreaterThan(0, $output->id);
        $this->assertDatabaseHas('gestiones', [
            'id' => $output->id,
            'proyecto_id' => $ctx['proyectoId'],
            'caso_id' => $ctx['casoId'],
            'causa_id' => null,
        ]);
        Event::assertDispatched(GestionRegistrada::class);
    }

    public function test_throws_cuando_resultado_requiere_causa_y_no_se_provee(): void
    {
        $ctx = $this->contexto();

        $this->expectException(CausaRequerida::class);
        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $this->idGlobal('canales', 'TELEFONO'),
            tipoGestionId: $this->idProyecto('tipos_gestion', 'LLAMADA_SALIENTE', $ctx['proyectoId']),
            resultadoId: $this->idProyecto('resultados', 'NEGOCIACION', $ctx['proyectoId']),
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: $ctx['usuarioId'],
            notas: null,
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-17 10:00:00'),
        ));
    }

    public function test_registra_gestion_con_promesa_pago_y_causa(): void
    {
        $ctx = $this->contexto();
        $causaId = $this->idProyecto('causas_gestion', 'DESEMPLEO', $ctx['proyectoId']);

        $output = $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $this->idGlobal('canales', 'TELEFONO'),
            tipoGestionId: $this->idProyecto('tipos_gestion', 'LLAMADA_SALIENTE', $ctx['proyectoId']),
            resultadoId: $this->idProyecto('resultados', 'PROMESA_PAGO', $ctx['proyectoId']),
            motivoNoContactoId: null,
            causaId: $causaId,
            usuarioId: $ctx['usuarioId'],
            notas: 'Promete pagar el viernes.',
            duracion: new DuracionSegundos(240),
            creadaEn: new DateTimeImmutable('2026-04-17 11:30:00'),
            datosCompromiso: new DatosPromesaPago(
                monto: new MontoPromesa('500.00', 'USD'),
                fechaVencimiento: new FechaPromesa(new DateTimeImmutable('2026-04-24')),
                tipoPagoId: null,
            ),
        ));

        $this->assertDatabaseHas('gestiones', [
            'id' => $output->id,
            'resultado_id' => $this->idProyecto('resultados', 'PROMESA_PAGO', $ctx['proyectoId']),
            'causa_id' => $causaId,
        ]);
    }

    public function test_throws_cuando_resultado_requiere_compromiso_y_no_llegan_datos(): void
    {
        $ctx = $this->contexto();
        $causaId = $this->idProyecto('causas_gestion', 'DESEMPLEO', $ctx['proyectoId']);

        $this->expectException(PromesaRequerida::class);
        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $this->idGlobal('canales', 'TELEFONO'),
            tipoGestionId: $this->idProyecto('tipos_gestion', 'LLAMADA_SALIENTE', $ctx['proyectoId']),
            resultadoId: $this->idProyecto('resultados', 'PROMESA_PAGO', $ctx['proyectoId']),
            motivoNoContactoId: null,
            causaId: $causaId,
            usuarioId: $ctx['usuarioId'],
            notas: null,
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-17 11:30:00'),
            datosCompromiso: null,
        ));
    }

    /** @return array{proyectoId:int, casoId:int, personaId:int, usuarioId:int} */
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

    private function idGlobal(string $tabla, string $codigo): int
    {
        return (int) DB::table($tabla)->where('codigo', $codigo)->value('id');
    }

    private function idProyecto(string $tabla, string $codigo, int $proyectoId): int
    {
        return (int) DB::table($tabla)->where('proyecto_id', $proyectoId)->where('codigo', $codigo)->value('id');
    }
}
