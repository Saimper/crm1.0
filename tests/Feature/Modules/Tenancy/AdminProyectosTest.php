<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\AdminProyectos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class AdminProyectosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_crea_proyecto_nuevo(): void
    {
        $mandante = $this->crearMandante('BPO_DEMO');
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(AdminProyectos::class)
            ->call('abrirFormCrear')
            ->set('form.mandante_id', (int) $mandante->id)
            ->set('form.codigo', 'NUEVO_PROYECTO_2026')
            ->set('form.nombre', 'Proyecto nuevo')
            ->set('form.tipo_operacion', 'cobranza')
            ->set('form.fecha_inicio', '2026-05-01')
            ->set('form.fecha_fin', '2026-12-31')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('formVisible', false);

        $this->assertDatabaseHas('proyectos', [
            'mandante_id' => $mandante->id,
            'codigo' => 'NUEVO_PROYECTO_2026',
            'nombre' => 'Proyecto nuevo',
            'tipo_operacion' => 'cobranza',
            'activo' => true,
        ]);
    }

    /**
     * El invariante es el mismo que cuando se escribió el test —dentro de un
     * mandante no puede haber dos proyectos con el mismo `codigo`—, pero la
     * forma en que la aplicación lo garantiza cambió de manera deliberada: desde
     * la política B6 (`GeneradorCodigo::resolverConflicto`, invocado en
     * `guardar()`) el choque no se rechaza con un error de validación, se
     * resuelve sufijando `_2`, `_3`, … Por eso se afirma sobre las filas de la
     * base y no sobre el mensaje de error, que es lo que se movió.
     */
    public function test_admin_rechaza_codigo_duplicado_en_mismo_mandante(): void
    {
        $mandante = $this->crearMandante('BPO_DEMO');
        $existente = $this->crearProyecto('cobranza', $mandante, 'COBRANZA_DEMO_2026');
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(AdminProyectos::class)
            ->call('abrirFormCrear')
            ->set('form.mandante_id', (int) $mandante->id)
            ->set('form.codigo', 'COBRANZA_DEMO_2026')     // ya existe
            ->set('form.nombre', 'Duplicado')
            ->set('form.tipo_operacion', 'cobranza')
            ->call('guardar');

        $codigos = DB::table('proyectos')
            ->where('mandante_id', $mandante->id)
            ->orderBy('id')
            ->pluck('codigo')
            ->all();

        $this->assertSame(
            $codigos,
            array_values(array_unique($codigos)),
            'El código «COBRANZA_DEMO_2026» quedó duplicado dentro del mismo mandante.'
        );

        // Y la fila que ya existía no se tocó.
        $this->assertDatabaseHas('proyectos', [
            'id' => $existente->id,
            'codigo' => 'COBRANZA_DEMO_2026',
            'nombre' => $existente->nombre,
        ]);
    }

    public function test_admin_edita_proyecto_nombre_pero_no_tipo(): void
    {
        $proyecto = $this->crearProyecto('cobranza', null, 'COBRANZA_DEMO_2026');
        $id = (int) $proyecto->id;
        $this->actingAs($this->crearAdminGlobal());

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
        $proyecto = $this->crearProyecto('cobranza', null, 'COBRANZA_DEMO_2026');
        $id = (int) $proyecto->id;
        $this->actingAs($this->crearAdminGlobal());

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
        // La ruta va detrás de `mandante.activo`: sin ningún cliente en la base
        // no hay contexto que resolver y el middleware manda a elegir (302). Con
        // uno solo lo deriva sin preguntar, que es el escenario que este test
        // quiere comprobar — que el admin global entra en la pantalla.
        $this->crearMandante();

        $this->actingAs($this->crearAdminGlobal())->get(route('admin.proyectos'))->assertStatus(200);
    }
}
