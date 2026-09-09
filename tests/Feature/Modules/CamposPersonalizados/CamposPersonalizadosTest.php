<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\Exceptions\ReglaViolada;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class CamposPersonalizadosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guarda_y_recupera_valor_texto_corto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $proyectoId = (int) $proyecto->id;
        $carteraId = (int) $cartera->id;

        $campoId = $this->definirCampo($proyectoId, $carteraId, 'referencia_pago', 'texto_corto', obligatorio: false);

        $servicio = $this->app->make(ServicioCamposPersonalizados::class);
        $servicio->guardarValores($proyectoId, AmbitoCampo::CASO, $carteraId, entidadId: 999, valoresPorCodigo: [
            'referencia_pago' => 'TRX-ABC-123',
        ]);

        $this->assertDatabaseHas('valores_campo_personalizado', [
            'campo_personalizado_id' => $campoId,
            'entidad_id' => 999,
            'valor_texto_corto' => 'TRX-ABC-123',
        ]);
    }

    public function test_obligatorio_no_enviado_throws(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $proyectoId = (int) $proyecto->id;
        $carteraId = (int) $cartera->id;

        $this->definirCampo($proyectoId, $carteraId, 'campo_obligatorio', 'texto_corto', obligatorio: true);

        $this->expectException(ReglaViolada::class);
        $this->app->make(ServicioCamposPersonalizados::class)
            ->guardarValores($proyectoId, AmbitoCampo::CASO, $carteraId, entidadId: 1, valoresPorCodigo: []);
    }

    public function test_regla_regex_rechaza_valor_invalido(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $proyectoId = (int) $proyecto->id;
        $carteraId = (int) $cartera->id;

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyectoId,
            'ambito' => 'caso',
            'ambito_id' => $carteraId,
            'tipo' => 'texto_corto',
            'codigo' => 'numero_plan',
            'etiqueta' => 'Número plan',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 0,
            'reglas' => json_encode(['regex' => '^PL-\d{6}$']),
        ]);

        $servicio = $this->app->make(ServicioCamposPersonalizados::class);

        // Valor válido OK.
        $servicio->guardarValores($proyectoId, AmbitoCampo::CASO, $carteraId, entidadId: 5, valoresPorCodigo: [
            'numero_plan' => 'PL-123456',
        ]);
        $this->addToAssertionCount(1);

        // Valor inválido throws.
        $this->expectException(ReglaViolada::class);
        $servicio->guardarValores($proyectoId, AmbitoCampo::CASO, $carteraId, entidadId: 6, valoresPorCodigo: [
            'numero_plan' => 'XX-1',
        ]);
    }

    public function test_campos_otro_proyecto_no_se_cargan(): void
    {
        // Dos proyectos del MISMO mandante: el aislamiento es por proyecto (§2),
        // no por mandante, y con el mandante compartido el test lo demuestra.
        $mandante = $this->crearMandante();

        $proyectoA = $this->crearProyectoCobranza($mandante);
        $carteraA = $this->crearCarteraEn($proyectoA);
        $this->definirCampo((int) $proyectoA->id, (int) $carteraA->id, 'campo_a', 'texto_corto', obligatorio: false);

        $proyectoB = $this->crearProyectoCobranza($mandante);
        $carteraB = $this->crearCarteraEn($proyectoB);

        $servicio = $this->app->make(ServicioCamposPersonalizados::class);

        $this->assertCount(1, $servicio->campos((int) $proyectoA->id, AmbitoCampo::CASO, (int) $carteraA->id));
        $this->assertCount(0, $servicio->campos((int) $proyectoB->id, AmbitoCampo::CASO, (int) $carteraB->id));
    }

    private function definirCampo(int $proyectoId, int $carteraId, string $codigo, string $tipo, bool $obligatorio): int
    {
        return (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyectoId,
            'ambito' => 'caso',
            'ambito_id' => $carteraId,
            'tipo' => $tipo,
            'codigo' => $codigo,
            'etiqueta' => ucfirst(str_replace('_', ' ', $codigo)),
            'obligatorio' => $obligatorio,
            'activo' => true,
            'orden' => 0,
            'reglas' => json_encode([]),
        ]);
    }
}
