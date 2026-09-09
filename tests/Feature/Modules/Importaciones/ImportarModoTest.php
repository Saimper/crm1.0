<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\Importar;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El modo que elige el supervisor en el paso 3 era el que NO corría.
 *
 * El wizard persistía el esquema al terminar el paso 2 con el modo de por
 * defecto (`upsert`); `ejecutar()` escribía el elegido en `importaciones.modo`,
 * pero el motor leía el del JSON. «Completar vacíos» pisaba saldos igual que
 * «insertar y actualizar».
 */
final class ImportarModoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    private const CSV = "CUENTA,CEDULA,NOMBRE DEL TITULAR,CAPITAL\nPR-MODO,5500009999,Sync,100\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_completar_vacios_respeta_el_saldo_que_ya_existia(): void
    {
        [$proyecto, $wizard] = $this->wizardEnElPaso3ConCasoDeSaldo500();

        $wizard->set('modo', 'merge')->call('ejecutar')->assertHasNoErrors()->assertSet('paso', 4);

        $this->assertSame('500.00', $this->saldoCapitalDe($proyecto));
        $this->assertSame('completada', (string) DB::table('importaciones')->where('id', $wizard->get('importacionId'))->value('estado'));
    }

    public function test_insertar_y_actualizar_pisa_el_saldo_con_el_del_archivo(): void
    {
        [$proyecto, $wizard] = $this->wizardEnElPaso3ConCasoDeSaldo500();

        $wizard->set('modo', 'upsert')->call('ejecutar')->assertHasNoErrors()->assertSet('paso', 4);

        $this->assertSame('100.00', $this->saldoCapitalDe($proyecto));
    }

    public function test_tras_encolar_la_columna_modo_y_el_json_del_esquema_dicen_lo_mismo(): void
    {
        [, $wizard] = $this->wizardEnElPaso3ConCasoDeSaldo500();

        $wizard->set('modo', 'merge')->call('ejecutar')->assertHasNoErrors();

        $importacion = DB::table('importaciones')->where('id', $wizard->get('importacionId'))->first(['modo', 'esquema']);

        $this->assertSame('merge', (string) $importacion->modo);
        $this->assertSame('merge', json_decode((string) $importacion->esquema, true)['modo']);
    }

    /**
     * Deja el wizard en el paso 3 con un caso de cobranza ya cargado (saldo 500)
     * al que el archivo trae saldo 100. Lo prepara un ADMIN_GLOBAL porque la
     * columna CUENTA se crea como campo personalizado y eso pide `campos.definir`.
     *
     * @return array{0: stdClass, 1: Testable}
     */
    private function wizardEnElPaso3ConCasoDeSaldo500(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto, 'CART_T');
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO');
        $persona = $this->crearPersonaEn($proyecto, '5500009999');

        $casoId = $this->crearCasoEn($proyecto, ['cartera' => $cartera, 'persona' => $persona, 'estado' => $estado]);
        DB::table('casos_cobranza')->insert([
            'caso_id' => $casoId,
            'proyecto_id' => $proyecto->id,
            'numero_prestamo' => 'PR-MODO',
            'monto_original' => 1000.00,
            'saldo_capital' => 500.00,
            'saldo_total' => 500.00,
            'cuota_mensual' => 50.00,
            'cuotas_totales' => 12,
            'fecha_desembolso' => Carbon::today()->subYear(),
            'fecha_vencimiento' => Carbon::today()->addYear(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearAdminGlobal());

        $wizard = Livewire::test(Importar::class)
            ->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->set('carteraId', (int) $cartera->id)
            ->set('archivo', UploadedFile::fake()->createWithContent('modo_'.Str::random(4).'.csv', self::CSV))
            ->call('subirArchivo')
            ->assertHasNoErrors()
            ->assertSet('paso', 2);

        $wizard->call('marcarComoIdentificador', 'CEDULA')
            ->call('marcarComoIdentificadorCaso', 'CUENTA')
            ->call('confirmarMapeo')
            ->assertHasNoErrors()
            ->assertSet('paso', 3);

        return [$proyecto, $wizard];
    }

    private function saldoCapitalDe(stdClass $proyecto): string
    {
        return (string) DB::table('casos_cobranza')
            ->where('proyecto_id', $proyecto->id)
            ->where('numero_prestamo', 'PR-MODO')
            ->value('saldo_capital');
    }
}
