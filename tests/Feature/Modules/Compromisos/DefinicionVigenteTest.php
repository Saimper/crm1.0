<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Compromisos;

use App\Modules\Cobranza\Domain\Exceptions\DatosPromesaInvalidos;
use App\Modules\Cobranza\Domain\ValueObjects\DatosPromesaPago;
use App\Modules\Cobranza\Domain\ValueObjects\FechaPromesa;
use App\Modules\Cobranza\Domain\ValueObjects\MontoPromesa;
use App\Modules\Compromisos\Domain\Contracts\CompromisoRepository;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * «Vigente» es pendiente Y sin vencer (§6). Una sola definición, no dos.
 */
final class DefinicionVigenteTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_un_compromiso_pendiente_pero_vencido_no_cuenta_como_vigente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $casoId = $this->crearCasoEn($proyecto);

        DB::table('compromisos')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'tipo_compromiso' => 'promesa_pago',
            'estado' => 'pendiente',
            'fecha_vencimiento' => Carbon::today()->subDays(30)->toDateString(),
            'usuario_id' => $this->crearGestor($proyecto)->id,
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);

        // Era la única de las siete consultas del sistema que omitía la fecha, y
        // resulta que es la que alimenta la bandera del caso.
        $this->assertFalse(
            app(CompromisoRepository::class)->existenVigentesParaCaso($casoId),
            'Pendiente y vencido hace un mes no es vigente.'
        );
    }

    public function test_un_compromiso_que_vence_hoy_si_cuenta_como_vigente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $casoId = $this->crearCasoEn($proyecto);

        DB::table('compromisos')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'tipo_compromiso' => 'promesa_pago',
            'estado' => 'pendiente',
            'fecha_vencimiento' => Carbon::today()->toDateString(),
            'usuario_id' => $this->crearGestor($proyecto)->id,
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);

        $this->assertTrue(app(CompromisoRepository::class)->existenVigentesParaCaso($casoId));
    }

    public function test_no_se_puede_registrar_una_promesa_que_ya_nacio_vencida(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['persona' => $persona]);
        $cascada = $this->crearCascadaGestionEn($proyecto, ['requiere_compromiso' => true]);

        $this->expectException(DatosPromesaInvalidos::class);

        app(RegistrarGestion::class)->execute(new RegistrarGestionInput(
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
            usuarioId: (int) $this->crearGestor($proyecto)->id,
            notas: null,
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-06-01 10:00:00'),
            datosCompromiso: new DatosPromesaPago(
                monto: new MontoPromesa('100.00'),
                // Anterior a la propia gestión: imposible.
                fechaVencimiento: new FechaPromesa(new DateTimeImmutable('2026-05-01')),
            ),
        ));
    }

    public function test_una_promesa_historica_sigue_siendo_cargable(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['persona' => $persona]);
        $cascada = $this->crearCascadaGestionEn($proyecto, ['requiere_compromiso' => true]);

        // Gestión de hace dos meses con promesa a quince días: el vencimiento
        // está en el pasado visto desde hoy, y era válido cuando se tomó. La
        // regla se evalúa contra la fecha de la gestión, no contra hoy, o
        // importar un histórico sería imposible.
        $gestion = Carbon::today()->subDays(60);

        app(RegistrarGestion::class)->execute(new RegistrarGestionInput(
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
            usuarioId: (int) $this->crearGestor($proyecto)->id,
            notas: null,
            duracion: null,
            creadaEn: new DateTimeImmutable($gestion->toDateTimeString()),
            datosCompromiso: new DatosPromesaPago(
                monto: new MontoPromesa('100.00'),
                fechaVencimiento: new FechaPromesa(new DateTimeImmutable($gestion->copy()->addDays(15)->toDateString())),
            ),
        ));

        $this->assertDatabaseHas('compromisos', ['caso_id' => $casoId, 'estado' => 'pendiente']);
    }
}
