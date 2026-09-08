<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Models\User;
use App\Modules\Reportes\Application\DTOs\EntradaDefinicionReporte;
use App\Modules\Reportes\Application\Servicios\ServicioCamposPersonalizadosReporte;
use App\Modules\Reportes\Application\UseCases\CrearDefinicionReporte;
use App\Modules\Reportes\Infrastructure\Persistence\Repositories\RepositorioDefinicionReporteEloquent;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ConstructorRutasTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    private stdClass $mandante;

    private stdClass $proyecto;

    private int $proyectoId;

    private int $autorId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->mandante = $this->crearMandante();
        $this->proyecto = $this->crearProyectoCobranza($this->mandante);
        $this->proyectoId = (int) $this->proyecto->id;
        $this->autorId = (int) $this->crearAdminGlobal()->id;
    }

    public function test_supervisor_accede_listado_custom(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom', ['proyecto_id' => $this->proyectoId]))
            ->assertStatus(200);
    }

    public function test_gestor_recibe_403_en_listado_custom(): void
    {
        $u = $this->usuarioConRol('GESTOR');
        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom', ['proyecto_id' => $this->proyectoId]))
            ->assertStatus(403);
    }

    public function test_supervisor_accede_constructor_nuevo(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom.nuevo', ['proyecto_id' => $this->proyectoId]))
            ->assertStatus(200);
    }

    public function test_auditor_no_accede_constructor_nuevo(): void
    {
        $u = $this->usuarioConRol('AUDITOR');
        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom.nuevo', ['proyecto_id' => $this->proyectoId]))
            ->assertStatus(403);
    }

    public function test_auditor_si_accede_listado_pero_no_export(): void
    {
        $u = $this->usuarioConRol('AUDITOR');
        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom', ['proyecto_id' => $this->proyectoId]))
            ->assertStatus(200);

        $defId = $this->crearDefinicionDePrueba();

        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom.exportar', [
                'proyecto_id' => $this->proyectoId, 'definicion_id' => $defId, 'formato' => 'csv',
            ]))
            ->assertStatus(403);
    }

    public function test_supervisor_exporta_csv(): void
    {
        $defId = $this->crearDefinicionDePrueba();
        $u = $this->usuarioConRol('SUPERVISOR');

        $resp = $this->actingAs($u)->get(route('proyectos.reportes.custom.exportar', [
            'proyecto_id' => $this->proyectoId, 'definicion_id' => $defId, 'formato' => 'csv',
        ]));

        $resp->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $resp->headers->get('Content-Type'));
        $contenido = $resp->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido); // BOM
        $this->assertStringContainsString('ID', $contenido); // header de la columna
    }

    public function test_supervisor_exporta_xlsx(): void
    {
        $defId = $this->crearDefinicionDePrueba();
        $u = $this->usuarioConRol('SUPERVISOR');

        $resp = $this->actingAs($u)->get(route('proyectos.reportes.custom.exportar', [
            'proyecto_id' => $this->proyectoId, 'definicion_id' => $defId, 'formato' => 'xlsx',
        ]));

        $resp->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml', (string) $resp->headers->get('Content-Type'));
        $contenido = $resp->streamedContent();
        // XLSX = ZIP archive: magic bytes "PK"
        $this->assertStringStartsWith('PK', $contenido);
    }

    /**
     * La descarga del constructor deja la MISMA huella que las cuatro
     * descargas de listado: quién, qué tabla, con qué recorte y cuántas filas.
     * `reportes_ejecuciones` es la métrica del módulo; esto es lo que el
     * cliente mira cuando pregunta quién sacó sus datos.
     */
    public function test_export_deja_huella_en_la_auditoria_del_cliente(): void
    {
        $defId = $this->crearDefinicionDePrueba();
        $u = $this->usuarioConRol('SUPERVISOR');

        $this->actingAs($u)->get(route('proyectos.reportes.custom.exportar', [
            'proyecto_id' => $this->proyectoId, 'definicion_id' => $defId, 'formato' => 'csv',
        ]))->streamedContent();

        $huella = DB::table('auditorias')->where('evento', 'exportado')->first();

        $this->assertNotNull($huella, 'Sacar datos con un reporte custom también es sacarlos.');
        $this->assertSame('casos', $huella->entidad_tipo, 'Se atribuye a la entidad raíz, que es de donde salieron las filas.');
        $this->assertSame($this->proyectoId, (int) $huella->proyecto_id);
        $this->assertSame((int) $u->id, (int) $huella->usuario_id);

        $cambios = json_decode((string) $huella->cambios, true);
        $this->assertSame('csv', $cambios['filtros']['despues']['formato']);
        $this->assertTrue($cambios['completa']['despues']);
    }

    public function test_export_registra_ejecucion(): void
    {
        $defId = $this->crearDefinicionDePrueba();
        $u = $this->usuarioConRol('SUPERVISOR');

        $resp = $this->actingAs($u)->get(route('proyectos.reportes.custom.exportar', [
            'proyecto_id' => $this->proyectoId, 'definicion_id' => $defId, 'formato' => 'csv',
        ]));
        $resp->streamedContent(); // fuerza ejecución del closure

        $this->assertDatabaseHas('reportes_ejecuciones', [
            'definicion_id' => $defId,
            'proyecto_id' => $this->proyectoId,
            'usuario_id' => $u->id,
            'formato' => 'csv',
        ]);
    }

    public function test_export_definicion_otro_proyecto_da_404(): void
    {
        $defId = $this->crearDefinicionDePrueba();

        // Otro proyecto del MISMO mandante: la definición no debe ser visible
        // desde él aunque el usuario sea supervisor allí.
        $otroProyecto = $this->crearProyectoCx($this->mandante);
        $u = $this->crearSupervisor($otroProyecto);

        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom.exportar', [
                'proyecto_id' => (int) $otroProyecto->id, 'definicion_id' => $defId, 'formato' => 'csv',
            ]))
            ->assertStatus(404);
    }

    public function test_export_formato_invalido_da_422(): void
    {
        $defId = $this->crearDefinicionDePrueba();
        $u = $this->usuarioConRol('SUPERVISOR');

        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom.exportar', [
                'proyecto_id' => $this->proyectoId, 'definicion_id' => $defId, 'formato' => 'pdf',
            ]))
            ->assertStatus(422);
    }

    private function crearDefinicionDePrueba(): int
    {
        $repo = new RepositorioDefinicionReporteEloquent;
        $cp = new ServicioCamposPersonalizadosReporte;
        $crear = new CrearDefinicionReporte($repo, $cp);

        return $crear->execute(
            new EntradaDefinicionReporte(
                proyectoId: $this->proyectoId,
                codigo: 'test_export',
                nombre: 'Test Export',
                entidadRaiz: 'casos',
                columnas: [
                    ['campo' => 'casos.public_id', 'etiqueta' => 'ID'],
                    ['campo' => 'casos.tipo_caso', 'etiqueta' => 'Tipo'],
                ],
            ),
            $this->autorId,
        );
    }

    private function usuarioConRol(string $codigoRol): User
    {
        return $this->crearUsuarioConRol($this->proyecto, $codigoRol);
    }
}
