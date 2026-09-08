<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Personas;

use App\Modules\Personas\Infrastructure\Http\Livewire\BuscadorGlobal;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El buscador de Ctrl+K, que es la puerta más usada de la aplicación.
 *
 * Enseña personas y casos por nombre o por cédula, así que respeta el mismo
 * recorte que la bandeja: el del proyecto activo y el de las carteras del rol
 * (F22). Sin el segundo, un supervisor acotado encontraba aquí, con el nombre
 * de la cartera al lado, justo a la gente que su bandeja le esconde.
 */
final class BuscadorGlobalTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_no_encuentra_personas_ni_casos_de_otro_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $ajeno = $this->crearProyectoCobranza();

        $this->crearPersonaEn($proyecto, '7300000001');
        $this->crearPersonaEn($ajeno, '7300000002');

        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $resultado = Livewire::test(BuscadorGlobal::class)->set('query', '73000000');

        $this->assertSame(['7300000001'], $this->identificaciones($resultado->viewData('personas')));
    }

    public function test_un_rol_acotado_por_cartera_no_encuentra_lo_que_no_puede_abrir(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $permitida = $this->crearCarteraEn($proyecto);
        $vetada = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto);

        $dentro = $this->crearPersonaEn($proyecto, '7400000001');
        $fuera = $this->crearPersonaEn($proyecto, '7400000002');
        $this->crearCasoEn($proyecto, ['cartera' => $permitida, 'estado' => $estado, 'persona' => $dentro]);
        $this->crearCasoEn($proyecto, ['cartera' => $vetada, 'estado' => $estado, 'persona' => $fuera]);

        $supervisor = $this->crearSupervisor($proyecto);
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $supervisor->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'),
            'cartera_id' => $permitida->id,
        ]);

        $this->activarProyecto($proyecto);
        $this->actingAs($supervisor);

        $resultado = Livewire::test(BuscadorGlobal::class)->set('query', '74000000');

        $this->assertSame(['7400000001'], $this->identificaciones($resultado->viewData('personas')));
        $this->assertSame(['7400000001'], $this->identificaciones($resultado->viewData('casos')));
    }

    public function test_un_rol_sin_acotar_lo_encuentra_todo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $estado = $this->crearEstadoCasoEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto, '7500000001');
        $this->crearCasoEn($proyecto, ['cartera' => $this->crearCarteraEn($proyecto), 'estado' => $estado, 'persona' => $persona]);

        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $resultado = Livewire::test(BuscadorGlobal::class)->set('query', '75000000');

        $this->assertSame(['7500000001'], $this->identificaciones($resultado->viewData('personas')));
        $this->assertCount(1, $resultado->viewData('casos'));
    }

    /**
     * @param  iterable<int, stdClass>  $filas
     * @return list<string>
     */
    private function identificaciones(iterable $filas): array
    {
        $valores = [];
        foreach ($filas as $fila) {
            $valores[] = (string) $fila->identificacion;
        }
        sort($valores);

        return $valores;
    }
}
