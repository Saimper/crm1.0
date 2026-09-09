<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auditoria;

use App\Modules\Auditoria\Domain\Contracts\RegistroDeExportaciones;
use App\Modules\Auditoria\Infrastructure\Http\Livewire\ListadoAuditoria;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Sacar datos del sistema deja huella: quién, qué tabla, con qué recorte y
 * cuántas filas, atribuido al cliente dueño de los datos.
 */
final class RegistroDeExportacionesTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_una_exportacion_queda_en_la_auditoria_del_cliente(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $supervisor = $this->crearSupervisor($proyecto);
        $this->actingAs($supervisor);

        app(RegistroDeExportaciones::class)->registrar(
            entidadTipo: 'personas',
            filtros: ['q' => 'perez', 'tipo' => 'fisica'],
            totalFilas: 42,
            proyectoId: (int) $proyecto->id,
        );

        $fila = DB::table('auditorias')->where('evento', 'exportado')->first();

        self::assertNotNull($fila);
        self::assertSame((int) $proyecto->id, (int) $fila->proyecto_id);
        self::assertSame((int) $mandante->id, (int) $fila->mandante_id, 'El mandante se deduce del proyecto.');
        self::assertSame((int) $supervisor->id, (int) $fila->usuario_id);
        self::assertSame('personas', $fila->entidad_tipo);

        $cambios = json_decode((string) $fila->cambios, true);
        self::assertSame(['q' => 'perez', 'tipo' => 'fisica'], $cambios['filtros']['despues']);
        self::assertSame(42, $cambios['total_filas']['despues']);
    }

    public function test_el_listado_de_auditoria_filtra_por_el_evento_nuevo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $auditor = $this->crearAuditor($proyecto);

        app(RegistroDeExportaciones::class)->registrar('casos', [], 3, (int) $proyecto->id, null, (int) $auditor->id);
        app(RegistroDeExportaciones::class)->registrar('personas', [], 1, (int) $proyecto->id, null, (int) $auditor->id);
        $this->crearCasoEn($proyecto);

        $this->activarProyecto($proyecto);
        $this->actingAs($auditor);

        // La palabra «exportado» está en el desplegable de filtros aunque no
        // haya ni una fila, así que se cuentan las filas del listado.
        $eventos = array_map(
            static fn (object $f): string => (string) $f->evento,
            iterator_to_array(Livewire::test(ListadoAuditoria::class)->set('evento', 'exportado')->viewData('registros')),
        );

        self::assertSame(['exportado', 'exportado'], $eventos);
        self::assertNotSame([], $eventos, 'El filtro nuevo tiene que devolver las descargas.');
    }

    /**
     * Una descarga que se cortó a la mitad —el cliente cerró el navegador, o
     * el servidor la tumbó por tiempo— deja huella igual, marcada como
     * incompleta: el recuento del CSV y el de la auditoría no cuadran, y sin
     * la marca parecería que se llevó menos de lo que se llevó.
     */
    public function test_una_descarga_interrumpida_queda_marcada_como_incompleta(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $this->actingAs($supervisor);

        app(RegistroDeExportaciones::class)->registrar(
            entidadTipo: 'casos',
            filtros: [],
            totalFilas: 120,
            proyectoId: (int) $proyecto->id,
            completa: false,
        );

        $cambios = json_decode((string) DB::table('auditorias')->where('evento', 'exportado')->value('cambios'), true);

        self::assertFalse($cambios['completa']['despues']);
        self::assertSame(120, $cambios['total_filas']['despues'], 'Lo que se llegó a escribir sigue siendo lo que se llevó.');
    }
}
