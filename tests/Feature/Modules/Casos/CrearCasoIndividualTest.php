<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Models\User;
use App\Modules\Casos\Infrastructure\Http\Livewire\CrearCasoIndividual;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F34B — alta de caso individual desde UI con multiplexor por tipo_operacion.
 */
final class CrearCasoIndividualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_crea_caso_cobranza_individual(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $persona = (object) DB::table('personas')->where('proyecto_id', $proyectoId)->first();
        $cartera = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->value('id');
        $estado = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->value('id');

        $countAntes = (int) DB::table('casos')->where('proyecto_id', $proyectoId)->count();

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('carteraId', (string) $cartera)
            ->set('estadoCasoId', (string) $estado)
            ->set('fechaIngreso', '2026-04-30')
            ->set('numeroPrestamo', 'F34B-PRST-0001')
            ->set('moneda', 'USD')
            ->set('montoOriginal', '5000.00')
            ->set('saldoCapital', '4000.00')
            ->set('saldoInteres', '100.00')
            ->set('saldoTotal', '4100.00')
            ->set('cuotaMensual', '450.00')
            ->set('cuotasTotales', 12)
            ->set('cuotasPagadas', 1)
            ->set('diasMora', 0)
            ->set('fechaDesembolso', '2026-01-01')
            ->set('fechaVencimiento', '2027-01-01')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame($countAntes + 1, (int) DB::table('casos')->where('proyecto_id', $proyectoId)->count());
        $this->assertDatabaseHas('casos_cobranza', [
            'proyecto_id' => $proyectoId,
            'numero_prestamo' => 'F34B-PRST-0001',
        ]);
    }

    public function test_supervisor_crea_caso_cx_individual(): void
    {
        $proyectoId = $this->proyectoCx();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $persona = (object) DB::table('personas')->where('proyecto_id', $proyectoId)->first();
        $cartera = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->value('id');
        $estado = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->value('id');

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $persona->public_id])
            ->set('carteraId', (string) $cartera)
            ->set('estadoCasoId', (string) $estado)
            ->set('fechaIngreso', '2026-04-30')
            ->set('codigoTicket', 'F34B-TICK-0001')
            ->set('asunto', 'Reclamo facturación')
            ->set('descripcion', 'Cliente reporta cargos duplicados.')
            ->set('fechaReporte', '2026-04-30T10:00')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('casos_ticket_cx', [
            'proyecto_id' => $proyectoId,
            'codigo_ticket' => 'F34B-TICK-0001',
            'asunto' => 'Reclamo facturación',
        ]);
    }

    public function test_persona_invalida_no_crea_caso(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        // ULID válido pero inexistente.
        $ulidFalso = (string) Str::ulid();

        Livewire::test(CrearCasoIndividual::class, ['personaPublicId' => $ulidFalso])
            ->call('guardar')
            ->assertHasErrors();
    }

    public function test_gestor_recibe_403_en_ruta(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.casos.crear', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_supervisor_accede_ruta(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');

        $this->actingAs($supervisor)
            ->get(route('proyectos.casos.crear', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
    }

    private function proyectoCobranza(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function proyectoCx(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
    }

    private function bindProyectoActivo(int $proyectoId): void
    {
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
    }

    private function crearConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.cci.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
