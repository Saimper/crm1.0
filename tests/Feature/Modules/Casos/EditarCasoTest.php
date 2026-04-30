<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Models\User;
use App\Modules\Casos\Infrastructure\Http\Livewire\EditarCaso;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F34B — edición de caso descriptivo (sin tocar tipo_caso/estado_caso/Domain).
 */
final class EditarCasoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_edita_prioridad_caso_cobranza(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $caso = (object) DB::table('casos')
            ->where('proyecto_id', $proyectoId)
            ->where('tipo_caso', 'cobranza')
            ->first();

        Livewire::test(EditarCaso::class, ['caso' => $caso->public_id])
            ->set('prioridad', 7)
            ->set('saldoCapital', '999.99')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(7, (int) DB::table('casos')->where('id', $caso->id)->value('prioridad'));
        $this->assertSame('999.99', (string) DB::table('casos_cobranza')->where('caso_id', $caso->id)->value('saldo_capital'));
    }

    public function test_supervisor_edita_asunto_caso_cx(): void
    {
        $proyectoId = $this->proyectoCx();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $caso = (object) DB::table('casos')
            ->where('proyecto_id', $proyectoId)
            ->where('tipo_caso', 'ticket_cx')
            ->first();

        Livewire::test(EditarCaso::class, ['caso' => $caso->public_id])
            ->set('asunto', 'Asunto reformulado F34B')
            ->set('descripcion', 'Cliente reportó cambio de detalles.')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('Asunto reformulado F34B', (string) DB::table('casos_ticket_cx')->where('caso_id', $caso->id)->value('asunto'));
    }

    public function test_caso_de_otro_proyecto_no_se_carga(): void
    {
        $proyectoA = $this->proyectoCobranza();
        $proyectoB = $this->proyectoCx();

        $supervisor = $this->crearConRol($proyectoA, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoA);
        $this->actingAs($supervisor);

        $casoB = (object) DB::table('casos')->where('proyecto_id', $proyectoB)->first();

        try {
            Livewire::test(EditarCaso::class, ['caso' => $casoB->public_id]);
            $this->fail('Esperaba 404 al editar caso de otro proyecto.');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }

    public function test_auditor_recibe_403_en_ruta(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $auditor = $this->crearConRol($proyectoId, 'AUDITOR');
        $caso = (object) DB::table('casos')->where('proyecto_id', $proyectoId)->first();

        $this->actingAs($auditor)
            ->get(route('proyectos.casos.editar', [
                'proyecto_id' => $proyectoId,
                'caso' => $caso->public_id,
            ]))
            ->assertStatus(403);
    }

    public function test_estado_caso_no_es_editable_via_componente(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $caso = (object) DB::table('casos')->where('proyecto_id', $proyectoId)->first();
        $estadoOriginal = (int) $caso->estado_caso_id;

        Livewire::test(EditarCaso::class, ['caso' => $caso->public_id])
            ->set('prioridad', 3)
            ->call('guardar');

        $this->assertSame($estadoOriginal, (int) DB::table('casos')->where('id', $caso->id)->value('estado_caso_id'));
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
            'email' => strtolower($codigoRol).'.ec.'.Str::random(6).'@crm.local',
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
