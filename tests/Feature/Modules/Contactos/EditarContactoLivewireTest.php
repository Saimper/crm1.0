<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Contactos;

use App\Modules\Contactos\Infrastructure\Http\Livewire\ListaContactos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * F34B — edición y eliminación de contactos vía Livewire.
 */
final class EditarContactoLivewireTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_edita_contacto_existente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $persona = $this->crearPersonaEn($proyecto);
        $contactoId = $this->insertarContacto($persona, 'telefono', '+593 11111111');

        Livewire::test(ListaContactos::class, ['persona' => $persona->public_id])
            ->call('abrirEditar', $contactoId)
            ->set('valor', '+593 22222222')
            ->set('etiqueta', 'Móvil')
            ->call('guardarEdicion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('contactos', [
            'id' => $contactoId,
            'valor' => '+593 22222222',
            'etiqueta' => 'Móvil',
        ]);
    }

    public function test_marcar_principal_degrada_otros_del_mismo_tipo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $persona = $this->crearPersonaEn($proyecto);
        $c1 = $this->insertarContacto($persona, 'correo', 'a@x.com', esPrincipal: true);
        $c2 = $this->insertarContacto($persona, 'correo', 'b@x.com');

        Livewire::test(ListaContactos::class, ['persona' => $persona->public_id])
            ->call('abrirEditar', $c2)
            ->set('esPrincipal', true)
            ->call('guardarEdicion')
            ->assertHasNoErrors();

        $this->assertFalse((bool) DB::table('contactos')->where('id', $c1)->value('es_principal'));
        $this->assertTrue((bool) DB::table('contactos')->where('id', $c2)->value('es_principal'));
    }

    public function test_eliminar_contacto_marca_eliminada_en(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $persona = $this->crearPersonaEn($proyecto);
        $cid = $this->insertarContacto($persona, 'telefono', '+593 99999999');

        Livewire::test(ListaContactos::class, ['persona' => $persona->public_id])
            ->call('eliminar', $cid);

        $this->assertNotNull(DB::table('contactos')->where('id', $cid)->value('eliminada_en'));
    }

    public function test_gestor_no_puede_eliminar(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $gestor = $this->crearGestor($proyecto);
        $this->activarProyecto($proyecto);
        $this->actingAs($gestor);

        $persona = $this->crearPersonaEn($proyecto);
        $cid = $this->insertarContacto($persona, 'telefono', '+593 88888888');

        try {
            Livewire::test(ListaContactos::class, ['persona' => $persona->public_id])
                ->call('eliminar', $cid);
        } catch (\Throwable $e) {
            // Ok — abort(403) lanza HttpException; aceptamos cualquier excepción.
        }

        // El contacto sigue activo (no se eliminó), independientemente de cómo
        // Livewire propague el abort.
        $this->assertNull(DB::table('contactos')->where('id', $cid)->value('eliminada_en'));
    }

    /**
     * `crearContactoEn` del trait fuerza `es_principal = true`, y estos tests
     * necesitan controlar esa bandera fila a fila.
     */
    private function insertarContacto(stdClass $persona, string $tipo, string $valor, bool $esPrincipal = false): int
    {
        return (int) DB::table('contactos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $persona->proyecto_id,
            'persona_id' => $persona->id,
            'tipo' => $tipo,
            'valor' => $valor,
            'es_principal' => $esPrincipal,
            'activo' => true,
            'origen' => 'manual',
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }
}
