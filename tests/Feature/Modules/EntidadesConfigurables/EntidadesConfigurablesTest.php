<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\EntidadesConfigurables;

use App\Models\User;
use App\Modules\EntidadesConfigurables\Application\Services\ServicioEntidades;
use App\Modules\EntidadesConfigurables\Domain\ValueObjects\RelacionEntidad;
use App\Modules\EntidadesConfigurables\Infrastructure\Http\Livewire\AdminEntidadesConfigurables;
use App\Modules\EntidadesConfigurables\Infrastructure\Http\Livewire\GestorRegistrosEntidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class EntidadesConfigurablesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    // ====== Admin (definir entidades) ======

    public function test_gestor_y_supervisor_no_pueden_entrar_admin_entidades(): void
    {
        $proyectoId = $this->proyectoId();

        $gestor = $this->crearConRol($proyectoId, 'GESTOR');
        $this->actingAs($gestor)
            ->get('/admin/entidades-configurables')
            ->assertStatus(403);

        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->actingAs($supervisor)
            ->get('/admin/entidades-configurables')
            ->assertStatus(403);
    }

    public function test_admin_global_accede_admin_entidades(): void
    {
        $admin = $this->crearAdminGlobal();

        $this->actingAs($admin)
            ->get('/admin/entidades-configurables')
            ->assertStatus(200);
    }

    public function test_livewire_admin_aborta_para_gestor(): void
    {
        $proyectoId = $this->proyectoId();
        $this->actingAs($this->crearConRol($proyectoId, 'GESTOR'));

        Livewire::test(AdminEntidadesConfigurables::class)
            ->assertStatus(403);
    }

    public function test_admin_crea_entidad_y_campos(): void
    {
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);

        $proyectoId = $this->proyectoId();

        $c = Livewire::test(AdminEntidadesConfigurables::class)
            ->set('proyectoSeleccionadoId', $proyectoId)
            ->call('abrirFormCrear')
            ->set('formCodigo', 'POLIZAS')
            ->set('formNombre', 'Pólizas de seguro')
            ->set('formRelacion', 'caso')
            ->call('guardarEntidad')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('entidades_configurables', [
            'proyecto_id' => $proyectoId,
            'codigo' => 'POLIZAS',
            'relacion_con' => 'caso',
        ]);

        $entidadId = (int) DB::table('entidades_configurables')
            ->where('proyecto_id', $proyectoId)->where('codigo', 'POLIZAS')->value('id');

        $c->call('abrirCamposDe', $entidadId)
            ->call('abrirFormCampoCrear')
            ->set('formCampoCodigo', 'numero_poliza')
            ->set('formCampoEtiqueta', 'Número de póliza')
            ->set('formCampoTipo', 'texto_corto')
            ->set('formCampoObligatorio', true)
            ->call('guardarCampo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('campos_personalizados', [
            'proyecto_id' => $proyectoId,
            'ambito' => 'entidad_configurable',
            'ambito_id' => $entidadId,
            'codigo' => 'numero_poliza',
            'obligatorio' => true,
        ]);
    }

    public function test_admin_no_permite_codigo_duplicado_en_proyecto(): void
    {
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);
        $proyectoId = $this->proyectoId();

        $c = Livewire::test(AdminEntidadesConfigurables::class)
            ->set('proyectoSeleccionadoId', $proyectoId);

        $c->call('abrirFormCrear')
            ->set('formCodigo', 'DUP')
            ->set('formNombre', 'Primera')
            ->call('guardarEntidad')
            ->assertHasNoErrors();

        $c->call('abrirFormCrear')
            ->set('formCodigo', 'DUP')
            ->set('formNombre', 'Segunda')
            ->call('guardarEntidad')
            ->assertHasErrors(['formCodigo']);
    }

    // ====== Operativo (CRUD registros) ======

    public function test_crear_y_listar_registro_como_supervisor(): void
    {
        $admin = $this->crearAdminGlobal();
        $proyectoId = $this->proyectoId();

        $entidadId = app(ServicioEntidades::class)->crearEntidad(
            proyectoId: $proyectoId,
            codigo: 'VEHICULOS',
            nombre: 'Vehículos embargables',
            relacion: RelacionEntidad::NINGUNA,
        );

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyectoId,
            'ambito' => 'entidad_configurable',
            'ambito_id' => $entidadId,
            'codigo' => 'placa',
            'etiqueta' => 'Placa',
            'tipo' => 'texto_corto',
            'obligatorio' => true,
            'activo' => true,
            'orden' => 10,
        ]);

        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        Livewire::test(GestorRegistrosEntidad::class, [
            'proyectoId' => $proyectoId,
            'entidadId' => $entidadId,
        ])
            ->call('abrirFormCrear')
            ->set('titulo', 'Camioneta principal')
            ->set('valores.placa', 'ABC-1234')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('entidades_registros', [
            'proyecto_id' => $proyectoId,
            'entidad_configurable_id' => $entidadId,
            'titulo' => 'Camioneta principal',
        ]);
        $registroId = (int) DB::table('entidades_registros')
            ->where('proyecto_id', $proyectoId)->where('titulo', 'Camioneta principal')->value('id');

        $valor = DB::table('valores_campo_personalizado as v')
            ->join('campos_personalizados as c', 'c.id', '=', 'v.campo_personalizado_id')
            ->where('v.entidad_id', $registroId)
            ->where('c.ambito', 'entidad_configurable')
            ->where('c.codigo', 'placa')
            ->value('v.valor_texto_corto');
        $this->assertSame('ABC-1234', $valor);
    }

    public function test_gestor_sin_permiso_crear_es_rechazado(): void
    {
        $admin = $this->crearAdminGlobal();
        $proyectoId = $this->proyectoId();

        $entidadId = app(ServicioEntidades::class)->crearEntidad(
            proyectoId: $proyectoId, codigo: 'X', nombre: 'X',
        );

        // GESTOR en el seeder tiene entidades.crear. Para este test creamos un usuario SIN rol.
        $sinRol = User::query()->create([
            'name' => 'SinRol', 'email' => 'sin.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($sinRol);

        Livewire::test(GestorRegistrosEntidad::class, [
            'proyectoId' => $proyectoId,
            'entidadId' => $entidadId,
        ])
            ->assertStatus(403);
    }

    public function test_scope_cross_proyecto(): void
    {
        $admin = $this->crearAdminGlobal();

        $pA = $this->proyectoId();
        $pB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        $entidadA = app(ServicioEntidades::class)->crearEntidad(
            proyectoId: $pA, codigo: 'ENT_A', nombre: 'A',
        );
        $entidadB = app(ServicioEntidades::class)->crearEntidad(
            proyectoId: $pB, codigo: 'ENT_B', nombre: 'B',
        );

        app(ServicioEntidades::class)->crearRegistro(
            proyectoId: $pA, entidadId: $entidadA, titulo: 'Reg A', valoresPorCodigo: [],
        );
        app(ServicioEntidades::class)->crearRegistro(
            proyectoId: $pB, entidadId: $entidadB, titulo: 'Reg B', valoresPorCodigo: [],
        );

        $regsA = app(ServicioEntidades::class)->registros($pA, $entidadA);
        $regsB = app(ServicioEntidades::class)->registros($pB, $entidadB);

        $this->assertCount(1, $regsA);
        $this->assertCount(1, $regsB);
        $this->assertSame('Reg A', (string) $regsA->first()->titulo);
        $this->assertSame('Reg B', (string) $regsB->first()->titulo);
    }

    public function test_eliminar_registro_marca_eliminado_en(): void
    {
        $admin = $this->crearAdminGlobal();
        $proyectoId = $this->proyectoId();

        $entidadId = app(ServicioEntidades::class)->crearEntidad(
            proyectoId: $proyectoId, codigo: 'X', nombre: 'X',
        );
        $regId = app(ServicioEntidades::class)->crearRegistro(
            proyectoId: $proyectoId, entidadId: $entidadId, titulo: 'Para borrar', valoresPorCodigo: [],
        );

        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        Livewire::test(GestorRegistrosEntidad::class, [
            'proyectoId' => $proyectoId,
            'entidadId' => $entidadId,
        ])
            ->call('eliminar', $regId);

        $this->assertNotNull(DB::table('entidades_registros')->where('id', $regId)->value('eliminado_en'));
    }

    // ====== Helpers ======

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

    private function crearAdminGlobal(): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => 'Admin', 'email' => 'admin.ent.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolAdminId = (int) DB::table('roles')->where('codigo', 'ADMIN_GLOBAL')->value('id');
        DB::table('usuario_global_rol')->insert([
            'usuario_id' => $u->id, 'rol_id' => $rolAdminId,
        ]);

        return $u;
    }
}
