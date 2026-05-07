<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Models\User;
use App\Modules\Reportes\Infrastructure\Http\Livewire\ConstructorReporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class ConstructorLivewireTest extends TestCase
{
    use RefreshDatabase;

    private int $proyectoId;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_supervisor_construye_y_guarda_definicion(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u);

        Livewire::test(ConstructorReporte::class)
            ->set('codigo', 'demo_def')
            ->set('nombre', 'Demo')
            ->set('entidadRaiz', 'casos')
            ->call('agregarColumna', 'casos.public_id')
            ->call('agregarColumna', 'casos.tipo_caso')
            ->call('preview')
            ->assertSet('errorGuardar', null)
            ->call('guardar');

        $this->assertDatabaseHas('reportes_definiciones', [
            'codigo' => 'demo_def',
            'proyecto_id' => $this->proyectoId,
        ]);
    }

    public function test_constructor_aborta_para_gestor(): void
    {
        $u = $this->usuarioConRol('GESTOR');
        $this->actingAs($u);

        Livewire::test(ConstructorReporte::class)
            ->assertStatus(403);
    }

    public function test_cambio_entidad_limpia_columnas(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u);

        Livewire::test(ConstructorReporte::class)
            ->call('agregarColumna', 'casos.public_id')
            ->assertCount('columnas', 1)
            ->set('entidadRaiz', 'gestiones')
            ->assertCount('columnas', 0);
    }

    public function test_agregar_filtro_y_quitar(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u);

        Livewire::test(ConstructorReporte::class)
            ->call('agregarFiltro', 'casos.tipo_caso')
            ->assertCount('filtros', 1)
            ->call('quitarFiltro', 0)
            ->assertCount('filtros', 0);
    }

    public function test_campo_invalido_en_agregar_no_rompe(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u);

        Livewire::test(ConstructorReporte::class)
            ->call('agregarColumna', "'; DROP TABLE casos; --")
            ->assertCount('columnas', 0);
    }

    public function test_campos_disponibles_filtra_busqueda(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u);

        $component = Livewire::test(ConstructorReporte::class)
            ->set('busquedaCampo', 'persona');
        $campos = $component->get('camposDisponibles');
        $this->assertNotEmpty($campos);
        foreach (array_keys($campos) as $clave) {
            $this->assertStringContainsString('persona', $clave);
        }
    }

    private function usuarioConRol(string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);

        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $this->proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
