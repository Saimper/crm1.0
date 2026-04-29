<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\Exceptions\ReglaViolada;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use Database\Seeders\Tenancy\CarterasDemoSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CamposPersonalizadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            MandantesDemoSeeder::class,
            ProyectosDemoSeeder::class,
            CarterasDemoSeeder::class,
        ]);
    }

    public function test_guarda_y_recupera_valor_texto_corto(): void
    {
        $proyectoId = $this->idProyecto();
        $carteraId  = $this->idCartera($proyectoId);
        $campoId    = $this->definirCampo($proyectoId, $carteraId, 'referencia_pago', 'texto_corto', obligatorio: false);

        $servicio = $this->app->make(ServicioCamposPersonalizados::class);
        $servicio->guardarValores($proyectoId, AmbitoCampo::CASO, $carteraId, entidadId: 999, valoresPorCodigo: [
            'referencia_pago' => 'TRX-ABC-123',
        ]);

        $this->assertDatabaseHas('valores_campo_personalizado', [
            'campo_personalizado_id' => $campoId,
            'entidad_id'             => 999,
            'valor_texto_corto'      => 'TRX-ABC-123',
        ]);
    }

    public function test_obligatorio_no_enviado_throws(): void
    {
        $proyectoId = $this->idProyecto();
        $carteraId  = $this->idCartera($proyectoId);
        $this->definirCampo($proyectoId, $carteraId, 'campo_obligatorio', 'texto_corto', obligatorio: true);

        $this->expectException(ReglaViolada::class);
        $this->app->make(ServicioCamposPersonalizados::class)
            ->guardarValores($proyectoId, AmbitoCampo::CASO, $carteraId, entidadId: 1, valoresPorCodigo: []);
    }

    public function test_regla_regex_rechaza_valor_invalido(): void
    {
        $proyectoId = $this->idProyecto();
        $carteraId  = $this->idCartera($proyectoId);
        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyectoId,
            'ambito'      => 'caso',
            'ambito_id'   => $carteraId,
            'tipo'        => 'texto_corto',
            'codigo'      => 'numero_plan',
            'etiqueta'    => 'Número plan',
            'obligatorio' => false,
            'activo'      => true,
            'orden'       => 0,
            'reglas'      => json_encode(['regex' => '^PL-\d{6}$']),
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
        $proyectoA = $this->idProyecto();
        $carteraA  = $this->idCartera($proyectoA);
        $this->definirCampo($proyectoA, $carteraA, 'campo_a', 'texto_corto', obligatorio: false);

        // Un proyecto B adicional del mismo mandante.
        $mandanteId = (int) DB::table('mandantes')->where('codigo', 'BPO_DEMO')->value('id');
        $proyectoB = (int) DB::table('proyectos')->insertGetId([
            'public_id'      => '01HXOTHER0000000000000B',
            'mandante_id'    => $mandanteId,
            'codigo'         => 'OTRO_P',
            'nombre'         => 'Otro proyecto',
            'tipo_operacion' => 'cobranza',
            'activo'         => true,
        ]);
        $carteraB = (int) DB::table('carteras')->insertGetId([
            'public_id'   => '01HXOTHER0000000000000CB',
            'proyecto_id' => $proyectoB,
            'codigo'      => 'OTRA_CART',
            'nombre'      => 'Otra cartera',
            'activo'      => true,
        ]);

        $servicio = $this->app->make(ServicioCamposPersonalizados::class);

        $this->assertCount(1, $servicio->campos($proyectoA, AmbitoCampo::CASO, $carteraA));
        $this->assertCount(0, $servicio->campos($proyectoB, AmbitoCampo::CASO, $carteraB));
    }

    private function idProyecto(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function idCartera(int $proyectoId): int
    {
        return (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'CONSUMO')->value('id');
    }

    private function definirCampo(int $proyectoId, int $carteraId, string $codigo, string $tipo, bool $obligatorio): int
    {
        return (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyectoId,
            'ambito'      => 'caso',
            'ambito_id'   => $carteraId,
            'tipo'        => $tipo,
            'codigo'      => $codigo,
            'etiqueta'    => ucfirst(str_replace('_', ' ', $codigo)),
            'obligatorio' => $obligatorio,
            'activo'      => true,
            'orden'       => 0,
            'reglas'      => json_encode([]),
        ]);
    }
}
