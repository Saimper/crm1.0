<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Models\User;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\Importar;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * `campos.definir` protege CREAR campos personalizados, no usarlos.
 *
 * Producción, 2026-09-16: la supervisora que iba a hacer la carga semanal
 * subió un archivo cuyos 31 campos ya existían en la cartera y el asistente
 * la cortó con «No tienes permiso para crear campos personalizados». El
 * UseCase ya distinguía crear de reutilizar; el asistente no, y cortaba antes
 * de llegar a él en cuanto veía una columna marcada como campo personalizado.
 */
final class ImportarCamposExistentesTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_sin_campos_definir_carga_un_archivo_cuyos_campos_ya_existen(): void
    {
        [$proyecto, $cartera] = $this->escenario();

        // Primera carga: la hace quien sí define campos, y los deja creados.
        $this->actingAs($this->crearAdminGlobal());
        $primera = $this->wizardHastaConfirmar($cartera, $this->csvConColumnas('Observacion,Segmento', 'texto libre,A'))
            ->assertHasNoErrors()
            ->assertSet('paso', 3);

        // El CSV base ya trae columnas que no son del sistema: cuenta lo que
        // creó el admin y exige que el supervisor reutilice exactamente eso.
        $creadosPorAdmin = (int) $primera->get('resultadoDryRun')['camposCreados'];
        self::assertGreaterThanOrEqual(2, $creadosPorAdmin);

        $this->assertDatabaseHas('campos_personalizados', [
            'proyecto_id' => $proyecto->id,
            'ambito_id' => $cartera->id,
            'codigo' => 'observacion',
        ]);

        // Segunda carga, mismo archivo, SUPERVISOR: no define nada, reutiliza.
        $this->actingAs($this->crearSupervisor($proyecto));
        $componente = $this->wizardHastaConfirmar($cartera, $this->csvConColumnas('Observacion,Segmento', 'otro texto,B'))
            ->assertHasNoErrors()
            ->assertSet('paso', 3)
            ->assertSet('resultadoDryRun.camposCreados', 0)
            ->assertSet('resultadoDryRun.camposReutilizados', $creadosPorAdmin);

        $this->assertSame(
            'preparada',
            (string) DB::table('importaciones')->where('id', (int) $componente->get('importacionId'))->value('estado'),
        );
        $this->assertSame(
            2,
            (int) DB::table('campos_personalizados')->where('proyecto_id', $proyecto->id)->where('ambito_id', $cartera->id)->whereIn('codigo', ['observacion', 'segmento'])->count(),
            'La segunda carga no debe haber duplicado ni creado campos.',
        );
    }

    public function test_supervisor_sin_campos_definir_no_crea_campos_ni_deja_filas_huerfanas(): void
    {
        [$proyecto, $cartera] = $this->escenario();

        $this->actingAs($this->crearSupervisor($proyecto));
        $componente = $this->wizardHastaConfirmar($cartera, $this->csvConColumnas('Observacion,Sin Definir', 'x,y'))
            ->assertHasErrors(['columnas'])
            ->assertSet('paso', 2);

        $mensaje = (string) ($componente->errors()->first('columnas'));
        self::assertStringContainsString('observacion', $mensaje);
        self::assertStringContainsString('sin_definir', $mensaje);

        $this->assertSame(0, (int) DB::table('importaciones')->count(), 'El «no» tiene que llegar antes de persistir la importación.');
        $this->assertSame(0, (int) DB::table('importacion_filas')->count());
        $this->assertDatabaseMissing('campos_personalizados', [
            'proyecto_id' => $proyecto->id,
            'codigo' => 'observacion',
        ]);
    }

    /**
     * `importaciones.crear_campos` (2026-09-16): el administrador del mandante
     * es el dueño de la carga semanal y el archivo cambia de columnas cada
     * semana. Crea los campos que trae el archivo sin ser quien diseña la
     * ficha: `campos.definir` sigue siendo de ADMIN_GLOBAL.
     */
    public function test_admin_de_mandante_crea_los_campos_que_falten_sin_campos_definir(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $cartera = $this->crearCarteraEn($proyecto, 'CART_CE');
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $this->activarProyecto($proyecto);

        $admin = $this->crearAdminDeMandante($mandante);
        self::assertTrue($admin->tienePermiso('importaciones.crear_campos', (int) $proyecto->id));
        self::assertFalse($admin->tienePermiso('campos.definir', (int) $proyecto->id));

        $this->actingAs($admin);
        $componente = $this->wizardHastaConfirmar($cartera, $this->csvConColumnas('Sem 39,2026-09-21', 'x,y'))
            ->assertHasNoErrors()
            ->assertSet('paso', 3);

        self::assertGreaterThanOrEqual(2, (int) $componente->get('resultadoDryRun')['camposCreados']);
        $this->assertDatabaseHas('campos_personalizados', ['ambito_id' => $cartera->id, 'codigo' => 'sem_39']);
        $this->assertDatabaseHas('campos_personalizados', ['ambito_id' => $cartera->id, 'codigo' => '2026_09_21']);
    }

    public function test_supervisor_con_rol_custom_que_lleva_el_permiso_tambien_crea_los_campos(): void
    {
        [$proyecto, $cartera] = $this->escenario();
        $supervisor = $this->crearSupervisor($proyecto);
        $this->darRolCustomConPermiso($supervisor, (int) $proyecto->id, 'importaciones.crear_campos');

        $this->actingAs($supervisor);
        $this->wizardHastaConfirmar($cartera, $this->csvConColumnas('Observacion', 'x'))
            ->assertHasNoErrors()
            ->assertSet('paso', 3);

        $this->assertDatabaseHas('campos_personalizados', ['ambito_id' => $cartera->id, 'codigo' => 'observacion']);
    }

    public function test_admin_global_sigue_creando_los_campos_que_falten(): void
    {
        [, $cartera] = $this->escenario();

        $this->actingAs($this->crearAdminGlobal());
        $componente = $this->wizardHastaConfirmar($cartera, $this->csvConColumnas('Observacion', 'x'))
            ->assertHasNoErrors()
            ->assertSet('paso', 3);

        self::assertGreaterThanOrEqual(1, (int) $componente->get('resultadoDryRun')['camposCreados']);
        $this->assertDatabaseHas('campos_personalizados', ['ambito_id' => $cartera->id, 'codigo' => 'observacion']);
    }

    /**
     * @return array{0: stdClass, 1: stdClass}
     */
    private function escenario(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto, 'CART_CE');
        $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $this->activarProyecto($proyecto);

        return [$proyecto, $cartera];
    }

    private function darRolCustomConPermiso(User $user, int $proyectoId, string $permiso): void
    {
        $rol = DB::table('roles_custom')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'codigo' => 'CARGADOR',
            'nombre' => 'Cargador',
            'activo' => true,
            'creado_por_usuario_id' => $user->id,
        ]);
        DB::table('rol_custom_permiso')->insert([
            'rol_custom_id' => $rol,
            'permiso_id' => DB::table('permisos')->where('codigo', $permiso)->value('id'),
        ]);
        DB::table('usuario_proyecto_rol_custom')->insert([
            'usuario_id' => $user->id,
            'proyecto_id' => $proyectoId,
            'rol_custom_id' => $rol,
            'activo' => true,
        ]);
    }

    private function wizardHastaConfirmar(stdClass $cartera, string $csv): Testable
    {
        return Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('carteraId', (int) $cartera->id)
            ->set('archivo', UploadedFile::fake()->createWithContent('carga.csv', $csv))
            ->call('subirArchivo')
            ->assertHasNoErrors()
            ->assertSet('paso', 2)
            ->call('marcarComoIdentificador', 'Identificacion')
            ->call('marcarComoIdentificadorCaso', 'NumeroPrestamo')
            ->call('confirmarMapeo');
    }

    /**
     * El CSV de cobranza de los otros tests, más las columnas que se piden.
     * Todo lo que el asistente no reconoce como campo del sistema lo propone
     * como campo personalizado, que es justo lo que hacía el archivo real.
     */
    private function csvConColumnas(string $cabecerasExtra, string $valoresExtra): string
    {
        return "Cartera,TipoIdentificacion,Identificacion,Nombres,Apellidos,NumeroPrestamo,Moneda,MO,SC,ST,FD,FV,Cu,{$cabecerasExtra}\n"
            ."CART_CE,CED,1700000555,Ana,Diaz,PR-CE-1,USD,1000,800,800,2025-10-01,2026-10-01,12,{$valoresExtra}\n";
    }
}
