<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El rótulo por tipo de operación, fuera de las pantallas de Casos.
 *
 * La primera tanda rotuló las 5 vistas del módulo Casos. Esta cubre el resto de
 * la superficie operativa —bandejas, asignación, campañas, reportes, personas,
 * importaciones, notificaciones y la portada del proyecto— donde el gestor leía
 * «caso» mientras el menú lateral ya decía «Cuentas».
 *
 * Dos redes distintas y las dos hacen falta:
 *
 *  - `test_ninguna_pantalla_operativa_deja_marcadores_sin_resolver` recorre
 *    todas las rutas del proyecto y falla si alguna publica un `:entidad` en
 *    crudo. Es la que caza la vista nueva que olvida pasar el parámetro.
 *  - Las de texto exacto cazan el fallo gemelo, que la de marcadores no ve:
 *    pasar `entidad` donde la cadena pide `entidades` produce «cuentaes», sin
 *    dejar ningún dos puntos detrás.
 */
final class RotuloEntidadEnTodaLaAppTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    /** @var array<string, array{0: string, 1: string}> singular, plural */
    private const PALABRA = [
        'cobranza' => ['cuenta', 'cuentas'],
        'cx' => ['ticket', 'tickets'],
        'venta' => ['oportunidad', 'oportunidades'],
        'servicio' => ['servicio', 'servicios'],
    ];

    /** Rutas del proyecto que renderizan alguna cadena rotulada. */
    private const RUTAS = [
        '',                          // portada: tiles de asignación, reasignación e importaciones
        '/personas',                 // columna de conteo + buscador global del layout
        '/bandeja',                  // «Estado :entidad»
        '/bandeja/equipo',
        '/asignaciones/masiva',
        '/asignaciones/reasignar',
        '/campanas',                 // incluye el trans_choice de «sin repartir»
        '/compromisos',
        '/importaciones',
        '/notificaciones',
        '/reportes/operativos',
        '/reportes/analiticos',
        '/reportes/equipos',
        '/reportes/custom/nuevo',    // constructor: selector de entidad raíz
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * La red barata: ninguna pantalla del proyecto puede publicar un marcador
     * sin resolver. Cubre de golpe las 14 rutas × 4 tipos.
     */
    /**
     * Tres de las cadenas rotuladas viven en cabeceras de tabla que sólo se
     * pintan cuando hay filas: sin datos, la pantalla muestra el estado vacío y
     * el assert de texto no probaría nada. Esto crea lo mínimo para que salgan.
     */
    private function poblar(stdClass $proyecto, User $usuario): int
    {
        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $persona = $this->crearPersonaEn($proyecto);

        $casoId = DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => $proyecto->tipo_operacion === 'cx' ? 'ticket_cx'
                : ($proyecto->tipo_operacion === 'venta' ? 'lead_venta' : $proyecto->tipo_operacion),
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => Carbon::today(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $campanaId = DB::table('campanas')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'codigo' => 'CAMP_'.strtoupper(Str::random(6)),
            'nombre' => 'Campaña de prueba',
            'fecha_inicio' => Carbon::today(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        DB::table('asignaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'campana_id' => $campanaId,
            'caso_id' => $casoId,
            'usuario_id' => $usuario->id,
            'fecha_asignacion' => Carbon::today(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $equipoId = DB::table('equipos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'codigo' => 'EQ_'.strtoupper(Str::random(6)),
            'nombre' => 'Equipo de prueba',
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        DB::table('equipo_usuario')->insert([
            'equipo_id' => $equipoId,
            'usuario_id' => $usuario->id,
            'proyecto_id' => $proyecto->id,
            'activo' => true,
        ]);

        DB::table('gestiones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'persona_id' => $persona->id,
            'canal_id' => (int) DB::table('canales')->value('id'),
            'tipo_gestion_id' => $this->catalogoDelProyecto($proyecto, 'tipos_gestion'),
            'resultado_id' => $this->catalogoDelProyecto($proyecto, 'resultados'),
            'usuario_id' => $usuario->id,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return $equipoId;
    }

    /** Los catálogos son por proyecto: si el seeder no dejó ninguno, se crea. */
    private function catalogoDelProyecto(stdClass $proyecto, string $tabla): int
    {
        $id = DB::table($tabla)->where('proyecto_id', $proyecto->id)->value('id');

        return $id !== null ? (int) $id : (int) DB::table($tabla)->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => strtoupper(Str::random(8)),
            'nombre' => 'Catálogo de prueba',
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    public function test_ninguna_pantalla_operativa_deja_marcadores_sin_resolver(): void
    {
        foreach (array_keys(self::PALABRA) as $tipo) {
            $proyecto = $this->crearProyecto($tipo);
            $supervisor = $this->crearSupervisor($proyecto);

            foreach (self::RUTAS as $ruta) {
                $html = $this->actingAs($supervisor)
                    ->get("/proyectos/{$proyecto->id}{$ruta}")
                    ->assertOk()
                    ->getContent();

                $this->assertIsString($html);

                foreach ([':entidades', ':entidad', ':Entidades', ':Entidad', ':un '] as $marcador) {
                    $this->assertStringNotContainsString(
                        $marcador,
                        $html,
                        "/proyectos/{id}{$ruta} publica {$marcador} en un proyecto de {$tipo}"
                    );
                }
            }
        }
    }

    public function test_la_portada_del_proyecto_rotula_los_tiles(): void
    {
        foreach (self::PALABRA as $tipo => [, $plural]) {
            $proyecto = $this->crearProyecto($tipo);
            $supervisor = $this->crearSupervisor($proyecto);

            $html = $this->actingAs($supervisor)->get("/proyectos/{$proyecto->id}")->assertOk()->getContent();

            $this->assertIsString($html);
            $this->assertStringContainsString("Distribuir {$plural} pendientes", $html, "tile de asignación en {$tipo}");
            $this->assertStringContainsString("respetando {$plural} en trabajo", $html, "tile de reasignación en {$tipo}");
            $this->assertStringContainsString("CSVs de personas, {$plural},", $html, "tile de importaciones en {$tipo}");
        }
    }

    public function test_el_buscador_global_y_la_ficha_de_persona_rotulan_la_entidad(): void
    {
        foreach (self::PALABRA as $tipo => [$singular, $plural]) {
            $proyecto = $this->crearProyecto($tipo);
            $supervisor = $this->crearSupervisor($proyecto);

            $html = $this->actingAs($supervisor)->get("/proyectos/{$proyecto->id}/personas")->assertOk()->getContent();

            $this->assertIsString($html);
            $this->assertStringContainsString("Buscar persona, {$singular}, gestión", $html, "buscador global en {$tipo}");
            $this->assertStringContainsString('>'.ucfirst($plural).'<', $html, "columna de conteo en {$tipo}");
        }
    }

    public function test_la_bandeja_rotula_la_columna_de_estado(): void
    {
        foreach (self::PALABRA as $tipo => [$singular]) {
            $proyecto = $this->crearProyecto($tipo);
            $supervisor = $this->crearSupervisor($proyecto);
            $equipoId = $this->poblar($proyecto, $supervisor);

            foreach (['/bandeja', "/bandeja/equipo?equipo={$equipoId}"] as $ruta) {
                $html = $this->actingAs($supervisor)->get("/proyectos/{$proyecto->id}{$ruta}")->assertOk()->getContent();

                $this->assertIsString($html);
                $this->assertStringContainsString("Estado {$singular}", $html, "{$ruta} en {$tipo}");
            }
        }
    }

    public function test_la_asignacion_masiva_concuerda_el_articulo(): void
    {
        foreach (self::PALABRA as $tipo => [$singular, $plural]) {
            $proyecto = $this->crearProyecto($tipo);
            $supervisor = $this->crearSupervisor($proyecto);

            $html = $this->actingAs($supervisor)
                ->get("/proyectos/{$proyecto->id}/asignaciones/masiva")->assertOk()->getContent();

            $this->assertIsString($html);
            $this->assertStringContainsString("Asignar {$plural} en batch", $html, "título en {$tipo}");
            $this->assertStringContainsString("Cada {$singular} del proyecto", $html, "descripción en {$tipo}");
        }
    }

    /**
     * La pantalla de campañas es el caso al revés: decía «cuentas» en duro, así
     * que en soporte mentía. Y su contador es la única cadena pluralizada del
     * lote, donde el marcador tiene que sobrevivir al corte por `|`.
     */
    public function test_campanas_rotula_tambien_su_contador_pluralizado(): void
    {
        foreach (self::PALABRA as $tipo => [, $plural]) {
            $proyecto = $this->crearProyecto($tipo);
            $supervisor = $this->crearSupervisor($proyecto);

            $html = $this->actingAs($supervisor)->get("/proyectos/{$proyecto->id}/campanas")->assertOk()->getContent();

            $this->assertIsString($html);
            $this->assertStringContainsString("no se pueden repartir {$plural}", $html, "ayuda del vacío en {$tipo}");
            $this->assertStringContainsString("No quedan {$plural} sin repartir", $html, "contador en cero en {$tipo}");
        }
    }

    /**
     * El panel del día elegía la etiqueta con un `match` cuya rama `default` era
     * la de cobranza: un proyecto sin tipo leía «Casos intentados». Además decía
     * «intentados»/«gestionados», que no es lo que la consulta cuenta.
     */
    public function test_los_reportes_rotulan_y_dicen_lo_que_de_verdad_cuentan(): void
    {
        foreach (self::PALABRA as $tipo => [$singular, $plural]) {
            $proyecto = $this->crearProyecto($tipo);
            $supervisor = $this->crearSupervisor($proyecto);
            $this->poblar($proyecto, $supervisor);

            $operativos = $this->actingAs($supervisor)
                ->get("/proyectos/{$proyecto->id}/reportes/operativos")->assertOk()->getContent();

            $this->assertIsString($operativos);
            $this->assertStringContainsString(ucfirst($plural).' con gestión', $operativos, "intentadas en {$tipo}");
            $this->assertStringContainsString(ucfirst($plural).' con contacto efectivo', $operativos, "gestionadas en {$tipo}");
            $this->assertStringContainsString("Tipo {$singular}", $operativos, "columna de tipo en {$tipo}");
            $this->assertStringNotContainsString('intentados', $operativos, "en {$tipo} sigue el participio con género");

            $analiticos = $this->actingAs($supervisor)
                ->get("/proyectos/{$proyecto->id}/reportes/analiticos")->assertOk()->getContent();

            $this->assertIsString($analiticos);
            $this->assertStringContainsString("Distribución por tipo de {$singular}", $analiticos, "gráfico en {$tipo}");
        }
    }

    /**
     * Fuera del proyecto no hay tipo que consultar. Las pantallas admin siguen
     * diciendo «caso», que es el nombre correcto en el modelo.
     */
    public function test_sin_proyecto_activo_el_rotulo_cae_en_la_palabra_generica(): void
    {
        $this->crearProyectoCobranza();
        $admin = $this->crearAdminGlobal();

        $html = $this->actingAs($admin)->get('/admin/proyectos')->assertOk()->getContent();

        $this->assertIsString($html);
        $this->assertStringNotContainsString(':entidades', $html);
        $this->assertStringNotContainsString(':entidad', $html);
    }
}
