<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * «Caso» no significa nada para quien opera. Según el tipo del proyecto, las
 * pantallas deben decir cuenta, ticket, oportunidad o servicio.
 *
 * Se prueba por HTTP y contra el texto exacto, no contra la ausencia de `:`.
 * Las cadenas llevan marcadores (`:entidad`, `:entidades`, `:un`) y una vista
 * que se olvide de pasarlos deja el marcador crudo en pantalla — pero el fallo
 * gemelo, pasar `entidad` donde la cadena pide `entidades`, produce «cuentaes»
 * sin dejar ningún dos puntos detrás. Sólo el texto esperado detecta los dos.
 */
final class RotuloEntidadPorTipoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** @var array<string, array{0: string, 1: string, 2: string}> singular, plural, artículo */
    private const PALABRA = [
        'cobranza' => ['cuenta', 'cuentas', 'una'],
        'cx' => ['ticket', 'tickets', 'un'],
        'venta' => ['oportunidad', 'oportunidades', 'una'],
        'servicio' => ['servicio', 'servicios', 'un'],
    ];

    public function test_el_listado_rotula_la_entidad_segun_el_tipo_de_proyecto(): void
    {
        foreach (self::PALABRA as $tipo => [, $plural]) {
            $proyecto = $this->crearProyecto($tipo);
            $supervisor = $this->crearSupervisor($proyecto);

            $html = $this->actingAs($supervisor)
                ->get("/proyectos/{$proyecto->id}/casos")
                ->assertOk()
                ->getContent();

            $this->assertIsString($html);
            $this->assertStringContainsString(ucfirst($plural).' del proyecto', $html, "título del listado en {$tipo}");
            $this->assertStringContainsString('Sin '.$plural, $html, "estado vacío en {$tipo}");
            $this->assertStringContainsString("Aún no hay {$plural} en este proyecto.", $html, "descripción del vacío en {$tipo}");
        }
    }

    public function test_la_pantalla_de_alta_rotula_la_entidad_y_concuerda_el_articulo(): void
    {
        foreach (self::PALABRA as $tipo => [$singular, , $articulo]) {
            $proyecto = $this->crearProyecto($tipo);
            $supervisor = $this->crearSupervisor($proyecto);

            // Sin ?persona la pantalla muestra el aviso, que es justo la cadena
            // que necesita a la vez la palabra y su artículo.
            $html = $this->actingAs($supervisor)
                ->get("/proyectos/{$proyecto->id}/casos/crear")
                ->assertOk()
                ->getContent();

            $this->assertIsString($html);
            $this->assertStringContainsString('Crear '.$singular, $html, "título del alta en {$tipo}");
            $this->assertStringContainsString("para crear {$articulo} {$singular}.", $html, "artículo del aviso en {$tipo}");
        }
    }

    public function test_el_menu_lateral_unifica_clientes_y_cuentas_en_todos_los_tipos(): void
    {
        foreach (self::PALABRA as $tipo => [, $plural]) {
            $proyecto = $this->crearProyecto($tipo);
            $supervisor = $this->crearSupervisor($proyecto);

            $html = $this->actingAs($supervisor)
                ->get("/proyectos/{$proyecto->id}/casos")
                ->assertOk()
                ->getContent();

            $this->assertIsString($html);
            $this->assertStringContainsString(
                '<span>Clientes</span>',
                $html,
                "entrada del menú lateral en {$tipo}"
            );
        }
    }

    /**
     * Red de seguridad barata: ninguna pantalla de Casos puede publicar un
     * marcador sin resolver. Complementa —no sustituye— a los asserts de texto
     * exacto de arriba.
     */
    public function test_ninguna_pantalla_de_casos_deja_marcadores_sin_resolver(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);

        foreach (["/proyectos/{$proyecto->id}/casos", "/proyectos/{$proyecto->id}/casos/crear"] as $ruta) {
            $html = $this->actingAs($supervisor)->get($ruta)->assertOk()->getContent();

            $this->assertIsString($html);
            foreach ([':entidades', ':entidad', ':Entidades', ':Entidad', ':un '] as $marcador) {
                $this->assertStringNotContainsString($marcador, $html, "{$ruta} publica {$marcador}");
            }
        }
    }

    /**
     * Fuera de un proyecto no hay tipo que consultar. La pantalla no puede
     * romperse por eso: cae en la palabra genérica.
     */
    public function test_sin_proyecto_activo_el_rotulo_cae_en_la_palabra_generica(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $admin = $this->crearAdminGlobal();

        $html = $this->actingAs($admin)->get('/admin/proyectos')->assertOk()->getContent();

        $this->assertIsString($html);
        $this->assertStringNotContainsString(':entidades', $html);
        $this->assertStringNotContainsString('Cuentas del proyecto', $html);
    }
}
