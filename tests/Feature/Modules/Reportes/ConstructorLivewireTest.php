<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Models\User;
use App\Modules\Reportes\Infrastructure\Http\Livewire\ConstructorReporte;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ConstructorLivewireTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    private stdClass $proyecto;

    private int $proyectoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->proyecto = $this->crearProyectoCobranza();
        $this->proyectoId = (int) $this->proyecto->id;
        $this->activarProyecto($this->proyecto);
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

    // ---------------------------------------------------------------------
    // Autorización por commit, no por página.
    //
    // El `can:reportes.constructor.gestionar` de la ruta corre una vez, al
    // pintar la pantalla. `guardar()` y `preview()` llegan después, como POST
    // sueltos a /livewire/update que no repasan el middleware.
    // ---------------------------------------------------------------------

    public function test_permiso_revocado_tras_montar_no_puede_guardar(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u);

        $componente = Livewire::test(ConstructorReporte::class)
            ->set('codigo', 'def_revocada')
            ->set('nombre', 'Revocada')
            ->call('agregarColumna', 'casos.public_id');

        // Entre el mount y el commit le quitan el rol. La pantalla sigue abierta
        // en su navegador y el botón «Guardar» sigue ahí.
        $this->revocarRolesEnProyecto($u);

        $componente->call('guardar')->assertForbidden();

        $this->assertDatabaseMissing('reportes_definiciones', [
            'codigo' => 'def_revocada',
            'proyecto_id' => $this->proyectoId,
        ]);
    }

    public function test_permiso_revocado_tras_montar_no_puede_previsualizar(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u);

        $componente = Livewire::test(ConstructorReporte::class)
            ->call('agregarColumna', 'casos.public_id');

        $this->revocarRolesEnProyecto($u);

        // El preview lee datos operativos del proyecto: es lectura, pero de la
        // cartera del mandante.
        $componente->call('preview')->assertForbidden();
    }

    public function test_gestor_no_puede_guardar_una_definicion(): void
    {
        $this->actingAs($this->usuarioConRol('GESTOR'));

        Livewire::test(ConstructorReporte::class)->assertStatus(403);

        $this->assertDatabaseCount('reportes_definiciones', 0);
    }

    public function test_supervisor_si_edita_su_propia_definicion(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u);

        $definicionId = $this->crearDefinicionEn($this->proyectoId, $u->id, 'def_propia');

        Livewire::test(ConstructorReporte::class, ['definicionId' => $definicionId])
            ->assertSet('codigo', 'def_propia')
            ->set('nombre', 'Nombre corregido')
            ->call('guardar')
            ->assertSet('errorGuardar', null);

        $this->assertSame(
            'Nombre corregido',
            DB::table('reportes_definiciones')->where('id', $definicionId)->value('nombre'),
            'El camino legítimo tiene que seguir vivo: esto no es un endurecimiento que rompa la operación.'
        );
    }

    public function test_no_se_puede_montar_el_constructor_sobre_una_definicion_de_otro_mandante(): void
    {
        $ajeno = $this->crearProyectoCobranza();          // crearProyecto crea su propio mandante
        $ajenoId = (int) $ajeno->id;
        $autorAjeno = $this->crearSupervisor($ajeno);
        $definicionAjena = $this->crearDefinicionEn($ajenoId, $autorAjeno->id, 'def_ajena');

        // Supervisor del proyecto propio: tiene `reportes.constructor.gestionar`
        // EN EL SUYO. Sin comprobar pertenencia, lo arrastraría al ajeno.
        $this->actingAs($this->usuarioConRol('SUPERVISOR'));

        Livewire::test(ConstructorReporte::class, ['definicionId' => $definicionAjena])
            ->assertNotFound();

        $this->assertSame(
            'def_ajena',
            DB::table('reportes_definiciones')->where('id', $definicionAjena)->value('codigo'),
            'FUGA: se tocó la definición de otro mandante.'
        );
    }

    public function test_el_id_de_la_definicion_no_se_puede_reapuntar_desde_el_cliente(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u);

        $otra = $this->crearDefinicionEn($this->proyectoId, $u->id, 'def_otra');

        // Sin `#[Locked]`, esto convertía el formulario de «crear» en un
        // «actualizar» contra la fila que eligiera el cliente.
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(ConstructorReporte::class)->set('definicionId', $otra);
    }

    private function revocarRolesEnProyecto(User $usuario): void
    {
        DB::table('usuario_proyecto_rol')
            ->where('usuario_id', $usuario->id)
            ->where('proyecto_id', $this->proyectoId)
            ->update(['activo' => false]);
    }

    private function crearDefinicionEn(int $proyectoId, int $usuarioId, string $codigo): int
    {
        return (int) DB::table('reportes_definiciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'codigo' => $codigo,
            'nombre' => 'Definición '.$codigo,
            'descripcion' => null,
            'entidad_raiz' => 'casos',
            'columnas' => json_encode([
                ['campo' => 'casos.public_id', 'etiqueta' => 'Caso', 'agregacion' => null],
            ]),
            'filtros' => json_encode([]),
            'agrupaciones' => json_encode([]),
            'orden' => json_encode([]),
            'activo' => true,
            'creado_por_usuario_id' => $usuarioId,
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);
    }

    private function usuarioConRol(string $codigoRol): User
    {
        return $this->crearUsuarioConRol($this->proyecto, $codigoRol);
    }
}
