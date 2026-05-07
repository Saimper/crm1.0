<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\AdminProyectos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminProyectosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_admin_crea_proyecto_nuevo(): void
    {
        $this->actingAs($this->admin());
        $mandanteId = (int) DB::table('mandantes')->where('codigo', 'BPO_DEMO')->value('id');

        Livewire::test(AdminProyectos::class)
            ->call('abrirFormCrear')
            ->set('form.mandante_id', $mandanteId)
            ->set('form.codigo', 'NUEVO_PROYECTO_2026')
            ->set('form.nombre', 'Proyecto nuevo')
            ->set('form.tipo_operacion', 'cobranza')
            ->set('form.fecha_inicio', '2026-05-01')
            ->set('form.fecha_fin', '2026-12-31')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('formVisible', false);

        $this->assertDatabaseHas('proyectos', [
            'mandante_id' => $mandanteId,
            'codigo' => 'NUEVO_PROYECTO_2026',
            'nombre' => 'Proyecto nuevo',
            'tipo_operacion' => 'cobranza',
            'activo' => true,
        ]);
    }

    public function test_admin_rechaza_codigo_duplicado_en_mismo_mandante(): void
    {
        $this->actingAs($this->admin());
        $mandanteId = (int) DB::table('mandantes')->where('codigo', 'BPO_DEMO')->value('id');

        Livewire::test(AdminProyectos::class)
            ->call('abrirFormCrear')
            ->set('form.mandante_id', $mandanteId)
            ->set('form.codigo', 'COBRANZA_DEMO_2026')     // ya existe
            ->set('form.nombre', 'Duplicado')
            ->set('form.tipo_operacion', 'cobranza')
            ->call('guardar')
            ->assertHasErrors(['form.codigo']);
    }

    public function test_admin_edita_proyecto_nombre_pero_no_tipo(): void
    {
        $this->actingAs($this->admin());
        $id = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');

        Livewire::test(AdminProyectos::class)
            ->call('abrirFormEditar', $id)
            ->set('form.nombre', 'Cobranza Demo EDITADO')
            ->set('form.tipo_operacion', 'venta')          // no se debe aplicar
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('proyectos', [
            'id' => $id,
            'nombre' => 'Cobranza Demo EDITADO',
            'tipo_operacion' => 'cobranza',                // se mantiene
        ]);
    }

    public function test_admin_desactiva_proyecto(): void
    {
        $this->actingAs($this->admin());
        $id = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');

        Livewire::test(AdminProyectos::class)->call('desactivar', $id);
        $this->assertFalse((bool) DB::table('proyectos')->where('id', $id)->value('activo'));
    }

    public function test_ruta_rechaza_no_admin_global(): void
    {
        $user = User::query()->create([
            'name' => 'X', 'email' => 'x.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $this->actingAs($user)->get(route('admin.proyectos'))->assertStatus(403);
    }

    public function test_ruta_200_admin_global(): void
    {
        $this->actingAs($this->admin())->get(route('admin.proyectos'))->assertStatus(200);
    }

    private function admin(): User
    {
        /** @var User $u */
        $u = User::query()->where('email', 'admin@crm.local')->firstOrFail();

        return $u;
    }
}
