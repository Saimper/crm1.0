<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalogos;

use App\Models\User;
use App\Modules\Cobranza\Infrastructure\Http\Livewire\AdminTiposPago;
use App\Modules\Cobranza\Infrastructure\Http\Livewire\AdminTramosMora;
use App\Modules\Cx\Infrastructure\Http\Livewire\AdminCategoriasTicket;
use App\Modules\Cx\Infrastructure\Http\Livewire\AdminNivelesEscalamiento;
use App\Modules\Cx\Infrastructure\Http\Livewire\AdminNivelesSla;
use App\Modules\Cx\Infrastructure\Http\Livewire\AdminPrioridadesTicket;
use App\Modules\Servicio\Infrastructure\Http\Livewire\AdminEstadosTecnicos;
use App\Modules\Servicio\Infrastructure\Http\Livewire\AdminTiposAccionServicio;
use App\Modules\Venta\Infrastructure\Http\Livewire\AdminEtapasEmbudo;
use App\Modules\Venta\Infrastructure\Http\Livewire\AdminProductosVenta;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminCatalogosTipoEspecificoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_cobranza_tramo_mora_crear_y_validar_rango(): void
    {
        $this->loginSupervisorEn('COBRANZA_DEMO_2026');

        Livewire::test(AdminTramosMora::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'MORA_NUEVA')
            ->set('form.nombre', 'Nueva franja')
            ->set('form.dias_desde', 200)
            ->set('form.dias_hasta', 300)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tramos_mora', ['codigo' => 'MORA_NUEVA', 'dias_desde' => 200, 'dias_hasta' => 300]);
    }

    public function test_cobranza_tramo_mora_rechaza_hasta_menor_que_desde(): void
    {
        $this->loginSupervisorEn('COBRANZA_DEMO_2026');

        Livewire::test(AdminTramosMora::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'MORA_MAL')
            ->set('form.nombre', 'Mal rango')
            ->set('form.dias_desde', 100)
            ->set('form.dias_hasta', 50)
            ->call('guardar')
            ->assertHasErrors(['form.dias_hasta']);
    }

    public function test_cobranza_tipo_pago_crear(): void
    {
        $this->loginSupervisorEn('COBRANZA_DEMO_2026');

        Livewire::test(AdminTiposPago::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'CRIPTO')
            ->set('form.nombre', 'Cripto')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tipos_pago', ['codigo' => 'CRIPTO']);
    }

    public function test_cx_prioridad_crear_con_peso(): void
    {
        $this->loginSupervisorEn('SOPORTE_DEMO_2026');

        Livewire::test(AdminPrioridadesTicket::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'CRITICA')
            ->set('form.nombre', 'Crítica')
            ->set('form.peso', 50)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('prioridades_ticket', ['codigo' => 'CRITICA', 'peso' => 50]);
    }

    public function test_cx_categoria_crear(): void
    {
        $this->loginSupervisorEn('SOPORTE_DEMO_2026');

        Livewire::test(AdminCategoriasTicket::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'CANCELACION')
            ->set('form.nombre', 'Cancelación de servicio')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categorias_ticket', ['codigo' => 'CANCELACION']);
    }

    public function test_cx_nivel_sla_crear(): void
    {
        $this->loginSupervisorEn('SOPORTE_DEMO_2026');

        Livewire::test(AdminNivelesSla::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'SLA_12H')
            ->set('form.nombre', '12 horas')
            ->set('form.horas_resolucion', 12)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('niveles_sla', ['codigo' => 'SLA_12H', 'horas_resolucion' => 12]);
    }

    public function test_cx_nivel_escalamiento_rechaza_nivel_duplicado(): void
    {
        $this->loginSupervisorEn('SOPORTE_DEMO_2026');

        Livewire::test(AdminNivelesEscalamiento::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'OTRO_N1')
            ->set('form.nombre', 'Otro nivel 1')
            ->set('form.nivel', 1)                        // N1 ya existe en seeder
            ->call('guardar')
            ->assertHasErrors(['form.nivel']);
    }

    public function test_venta_producto_crear(): void
    {
        $this->loginSupervisorEn('VENTA_DEMO_2026');

        Livewire::test(AdminProductosVenta::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'FONDO_INV')
            ->set('form.nombre', 'Fondo de inversión')
            ->set('form.descripcion', 'Producto de ahorro estructurado')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('productos_venta', ['codigo' => 'FONDO_INV']);
    }

    public function test_venta_etapa_embudo_rechaza_nivel_duplicado(): void
    {
        $this->loginSupervisorEn('VENTA_DEMO_2026');

        Livewire::test(AdminEtapasEmbudo::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'OTRA_ETAPA')
            ->set('form.nombre', 'Otra etapa 1')
            ->set('form.nivel', 1)                       // PROSPECCION ya tiene nivel 1
            ->set('form.probabilidad_cierre', 5)
            ->call('guardar')
            ->assertHasErrors(['form.nivel']);
    }

    public function test_servicio_tipo_accion_crear_con_duracion(): void
    {
        $this->loginSupervisorEn('SERVICIO_DEMO_2026');

        Livewire::test(AdminTiposAccionServicio::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'DIAGNOSTICO')
            ->set('form.nombre', 'Diagnóstico en sitio')
            ->set('form.duracion_estimada_horas', 1)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tipos_accion_servicio', [
            'codigo' => 'DIAGNOSTICO',
            'duracion_estimada_horas' => 1,
        ]);
    }

    public function test_servicio_estado_tecnico_crear(): void
    {
        $this->loginSupervisorEn('SERVICIO_DEMO_2026');

        Livewire::test(AdminEstadosTecnicos::class)
            ->call('abrirFormCrear')
            ->set('form.codigo', 'REAGENDADO')
            ->set('form.nombre', 'Reagendado')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('estados_tecnicos', ['codigo' => 'REAGENDADO']);
    }

    public function test_pagina_catalogos_muestra_tabs_tipo_cobranza(): void
    {
        $proyectoId = $this->proyectoId('COBRANZA_DEMO_2026');
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'))
            ->get(route('proyectos.catalogos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200)
            ->assertSee('Tramos de mora')
            ->assertSee('Tipos de pago')
            ->assertDontSee('Niveles SLA');       // No debe mostrar tabs de CX
    }

    public function test_pagina_catalogos_muestra_tabs_tipo_cx(): void
    {
        $proyectoId = $this->proyectoId('SOPORTE_DEMO_2026');
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'))
            ->get(route('proyectos.catalogos', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200)
            ->assertSee('Niveles SLA')
            ->assertSee('Escalamiento')
            ->assertDontSee('Tramos de mora');
    }

    private function proyectoId(string $codigo): int
    {
        return (int) DB::table('proyectos')->where('codigo', $codigo)->value('id');
    }

    private function loginSupervisorEn(string $codigoProyecto): void
    {
        $proyectoId = $this->proyectoId($codigoProyecto);
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
        $this->actingAs($this->crearConRol($proyectoId, 'SUPERVISOR'));
    }

    private function crearConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name'     => ucfirst(strtolower($codigoRol)),
            'email'    => strtolower($codigoRol).'.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo'   => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId, 'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
