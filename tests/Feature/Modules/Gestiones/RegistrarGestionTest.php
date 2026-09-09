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
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class RegistrarGestionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
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
            canalId: $ctx['contactoTitular']['canal_id'],
            tipoGestionId: $ctx['contactoTitular']['tipo_gestion_id'],
            resultadoId: $ctx['contactoTitular']['resultado_id'],
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
            canalId: $ctx['negociacion']['canal_id'],
            tipoGestionId: $ctx['negociacion']['tipo_gestion_id'],
            resultadoId: $ctx['negociacion']['resultado_id'],
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
        $causaId = $ctx['promesaPago']['causa_id'];

        $output = $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $ctx['promesaPago']['canal_id'],
            tipoGestionId: $ctx['promesaPago']['tipo_gestion_id'],
            resultadoId: $ctx['promesaPago']['resultado_id'],
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
            'resultado_id' => $ctx['promesaPago']['resultado_id'],
            'causa_id' => $causaId,
        ]);
    }

    public function test_throws_cuando_resultado_requiere_compromiso_y_no_llegan_datos(): void
    {
        $ctx = $this->contexto();
        $causaId = $ctx['promesaPago']['causa_id'];

        $this->expectException(PromesaRequerida::class);
        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $ctx['promesaPago']['canal_id'],
            tipoGestionId: $ctx['promesaPago']['tipo_gestion_id'],
            resultadoId: $ctx['promesaPago']['resultado_id'],
            motivoNoContactoId: null,
            causaId: $causaId,
            usuarioId: $ctx['usuarioId'],
            notas: null,
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-17 11:30:00'),
            datosCompromiso: null,
        ));
    }

    /**
     * Escenario de cobranza equivalente al que daban los seeders demo: un caso
     * abierto y tres resultados con las mismas banderas que tenían
     * CONTACTO_TITULAR (nada obligatorio), NEGOCIACION (exige causa) y
     * PROMESA_PAGO (exige causa y compromiso).
     *
     * @return array{
     *     proyectoId:int, casoId:int, personaId:int, usuarioId:int,
     *     contactoTitular:array{tipo_gestion_id:int, resultado_id:int, canal_id:int, motivo_no_contacto_id:int, causa_id:int},
     *     negociacion:array{tipo_gestion_id:int, resultado_id:int, canal_id:int, motivo_no_contacto_id:int, causa_id:int},
     *     promesaPago:array{tipo_gestion_id:int, resultado_id:int, canal_id:int, motivo_no_contacto_id:int, causa_id:int}
     * }
     */
    private function contexto(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, [
            'persona' => $persona,
            'fecha_ingreso' => '2026-04-17',
        ]);
        $usuario = $this->crearGestor($proyecto);

        return [
            'proyectoId' => (int) $proyecto->id,
            'casoId' => $casoId,
            'personaId' => (int) $persona->id,
            'usuarioId' => (int) $usuario->id,
            'contactoTitular' => $this->crearCascadaGestionEn($proyecto, [
                'es_contacto_efectivo' => true,
                'requiere_compromiso' => false,
                'requiere_causa' => false,
            ]),
            'negociacion' => $this->crearCascadaGestionEn($proyecto, [
                'es_contacto_efectivo' => true,
                'requiere_compromiso' => false,
                'requiere_causa' => true,
            ]),
            'promesaPago' => $this->crearCascadaGestionEn($proyecto, [
                'es_contacto_efectivo' => true,
                'requiere_compromiso' => true,
                'requiere_causa' => true,
            ]),
        ];
    }
}
