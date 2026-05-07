<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalogos;

use App\Models\User;
use App\Modules\Catalogos\Infrastructure\Http\Livewire\AdminCausasGestion;
use App\Modules\Catalogos\Infrastructure\Http\Livewire\AdminEstadosCaso;
use App\Modules\Catalogos\Infrastructure\Http\Livewire\AdminMotivosNoContacto;
use App\Modules\Catalogos\Infrastructure\Http\Livewire\AdminResultadosProyecto;
use App\Modules\Catalogos\Infrastructure\Http\Livewire\AdminTiposGestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminCatalogosProyectoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_supervisor_crea_resultado(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        Livewire::test(AdminResultadosProyecto::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'RES_TEST')
            ->set('form.nombre', 'Resultado test')
            ->set('form.es_contacto_efectivo', true)
            ->set('form.requiere_compromiso', true)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('formVisible', false);

        $this->assertDatabaseHas('resultados', [
            'proyecto_id' => $proyectoId,
            'codigo' => 'RES_TEST',
            'es_contacto_efectivo' => true,
            'requiere_compromiso' => true,
        ]);
    }

    public function test_supervisor_rechaza_codigo_duplicado_resultado(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        Livewire::test(AdminResultadosProyecto::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'PROMESA_PAGO')    // ya sembrado
            ->set('form.nombre', 'Duplicado')
            ->call('guardar')
            ->assertHasErrors(['form.codigo']);
    }

    public function test_supervisor_crea_tipo_gestion(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        Livewire::test(AdminTiposGestion::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'SMS')
            ->set('form.nombre', 'SMS saliente')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tipos_gestion', [
            'proyecto_id' => $proyectoId,
            'codigo' => 'SMS',
        ]);
    }

    public function test_supervisor_crea_causa_con_metadata_tipo(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        Livewire::test(AdminCausasGestion::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'CAUSA_TEST')
            ->set('form.nombre', 'Causa de prueba')
            ->set('form.tipo', 'mora')
            ->call('guardar')
            ->assertHasNoErrors();

        $row = DB::table('causas_gestion')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'CAUSA_TEST')->first();
        $this->assertNotNull($row);
        $meta = json_decode((string) $row->metadata, true);
        $this->assertSame('mora', $meta['tipo'] ?? null);
    }

    public function test_supervisor_crea_motivo_no_contacto(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        Livewire::test(AdminMotivosNoContacto::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'NUEVO_MOTIVO')
            ->set('form.nombre', 'Motivo nuevo')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('motivos_no_contacto', [
            'proyecto_id' => $proyectoId,
            'codigo' => 'NUEVO_MOTIVO',
        ]);
    }

    public function test_supervisor_crea_estado_caso_con_es_terminal(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        Livewire::test(AdminEstadosCaso::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'EN_REVISION')
            ->set('form.nombre', 'En revisión')
            ->set('form.es_terminal', false)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('estados_caso', [
            'proyecto_id' => $proyectoId,
            'codigo' => 'EN_REVISION',
            'es_terminal' => false,
        ]);
    }

    public function test_no_puede_desactivar_estado_en_uso(): void
    {
        $proyectoId = $this->proyectoId();
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));

        $estadoUsadoId = (int) DB::table('estados_caso')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'ABIERTO')->value('id');
        $usando = DB::table('casos')->where('estado_caso_id', $estadoUsadoId)->exists();
        $this->assertTrue($usando, 'El estado ABIERTO debe estar en uso por los casos demo');

        Livewire::test(AdminEstadosCaso::class)->call('desactivar', $estadoUsadoId);

        $this->assertTrue(
            (bool) DB::table('estados_caso')->where('id', $estadoUsadoId)->value('activo'),
            'El estado no debe quedar desactivado si tiene casos que lo usan'
        );
    }

    public function test_gestor_recibe_403_en_ruta_catalogos(): void
    {
        $proyectoId = $this->proyectoId();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.catalogos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_supervisor_accede_ruta_catalogos(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');

        $this->actingAs($supervisor)
            ->get(route('proyectos.catalogos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);
    }

    private function proyectoId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
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
            'email' => strtolower($codigoRol).'.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId, 'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
