<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use App\Models\User;
use App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire\FormularioCamposPersonalizados;
use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaInput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class FormularioCamposPersonalizadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_admin_global_puede_guardar_valor(): void
    {
        [$casoId, $proyectoId, $carteraId] = $this->crearContexto();
        $this->actingAs($this->obtenerAdminGlobal());

        Livewire::test(FormularioCamposPersonalizados::class, [
            'proyectoId' => $proyectoId,
            'ambito' => 'caso',
            'ambitoId' => $carteraId,
            'entidadId' => $casoId,
        ])
            ->set('valores.operador_externo', 'Agente Admin')
            ->call('guardar')
            ->assertHasNoErrors();

        $campoId = (int) DB::table('campos_personalizados')
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', 'operador_externo')
            ->value('id');

        $this->assertDatabaseHas('valores_campo_personalizado', [
            'campo_personalizado_id' => $campoId,
            'entidad_id' => $casoId,
            'valor_texto_corto' => 'Agente Admin',
        ]);
    }

    public function test_supervisor_con_permiso_puede_guardar_valor(): void
    {
        [$casoId, $proyectoId, $carteraId] = $this->crearContexto();
        $this->actingAs($this->crearUsuarioConRol($proyectoId, 'SUPERVISOR'));

        Livewire::test(FormularioCamposPersonalizados::class, [
            'proyectoId' => $proyectoId,
            'ambito' => 'caso',
            'ambitoId' => $carteraId,
            'entidadId' => $casoId,
        ])
            ->set('valores.operador_externo', 'Supervisor editó')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('valores_campo_personalizado', [
            'entidad_id' => $casoId,
            'valor_texto_corto' => 'Supervisor editó',
        ]);
    }

    public function test_gestor_con_permiso_puede_guardar_valor(): void
    {
        [$casoId, $proyectoId, $carteraId] = $this->crearContexto();
        $this->actingAs($this->crearUsuarioConRol($proyectoId, 'GESTOR'));

        Livewire::test(FormularioCamposPersonalizados::class, [
            'proyectoId' => $proyectoId,
            'ambito' => 'caso',
            'ambitoId' => $carteraId,
            'entidadId' => $casoId,
        ])
            ->set('valores.operador_externo', 'Gestor editó')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('valores_campo_personalizado', [
            'entidad_id' => $casoId,
            'valor_texto_corto' => 'Gestor editó',
        ]);
    }

    public function test_auditor_sin_permiso_campos_editar_se_monta_bloqueado_y_guardar_aborta(): void
    {
        [$casoId, $proyectoId, $carteraId] = $this->crearContexto();
        $this->actingAs($this->crearUsuarioConRol($proyectoId, 'AUDITOR'));

        Livewire::test(FormularioCamposPersonalizados::class, [
            'proyectoId' => $proyectoId,
            'ambito' => 'caso',
            'ambitoId' => $carteraId,
            'entidadId' => $casoId,
        ])
            ->assertSet('bloqueado', true)
            ->set('valores.operador_externo', 'Intento auditor')
            ->call('guardar')
            ->assertStatus(403);

        $this->assertSame(0, DB::table('valores_campo_personalizado')->count());
    }

    public function test_usuario_sin_rol_en_proyecto_bloqueado_y_aborta(): void
    {
        [$casoId, $proyectoId, $carteraId] = $this->crearContexto();
        $this->actingAs(User::factory()->create());

        Livewire::test(FormularioCamposPersonalizados::class, [
            'proyectoId' => $proyectoId,
            'ambito' => 'caso',
            'ambitoId' => $carteraId,
            'entidadId' => $casoId,
        ])
            ->assertSet('bloqueado', true)
            ->call('guardar')
            ->assertStatus(403);

        $this->assertSame(0, DB::table('valores_campo_personalizado')->count());
    }

    private function obtenerAdminGlobal(): User
    {
        /** @var User $u */
        $u = User::query()->where('email', 'admin@crm.local')->firstOrFail();

        return $u;
    }

    private function crearUsuarioConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)).' '.Str::random(4),
            'email' => strtolower($codigoRol).'.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);

        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id,
            'proyecto_id' => $proyectoId,
            'rol_id' => $rolId,
            'equipo_id' => null,
            'activo' => true,
        ]);

        return $u;
    }

    /** @return array{int,int,int} */
    private function crearContexto(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'CONSUMO')->value('id');
        $estado = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', 'ABIERTO')->value('id');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');

        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));

        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Test', 'apellidos' => 'User',
        ]);

        $out = $this->app->make(RegistrarCasoCobranza::class)->execute(new RegistrarCasoCobranzaInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estado,
            fechaIngreso: new DateTimeImmutable('2026-04-17'),
            prioridad: 100,
            numeroPrestamo: 'PRST-CP-'.Str::random(4),
            moneda: 'USD',
            montoOriginal: '1000.00',
            saldoCapital: '900.00',
            saldoInteres: '10.00',
            saldoTotal: '910.00',
            cuotaMensual: '100.00',
            cuotasTotales: 10,
            cuotasPagadas: 1,
            diasMora: 0,
            fechaDesembolso: new DateTimeImmutable('2026-02-01'),
            fechaVencimiento: new DateTimeImmutable('2026-12-01'),
        ));

        return [$out->casoId, $proyectoId, $carteraId];
    }
}
