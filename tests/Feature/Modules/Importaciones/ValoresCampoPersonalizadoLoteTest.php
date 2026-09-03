<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Domain\Contracts\CampoPersonalizadoImportacionRepository;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Regresión producción 2026-09-02: un lote que mezcla tipos (texto + moneda +
 * entero) rompía el upsert masivo con "Column count doesn't match value count",
 * porque cada tipo aporta columnas distintas y el INSERT tomaba las de la
 * primera fila.
 */
final class ValoresCampoPersonalizadoLoteTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    private const ENTIDAD = 9461;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_lote_con_tipos_mezclados_persiste_cada_valor_en_su_columna(): void
    {
        [$repo, $texto, $moneda, $entero] = $this->camposDePrueba();

        $repo->guardarValoresEnLote([
            ['campo_id' => $texto, 'entidad_id' => self::ENTIDAD, 'valor' => 'MICHELANGELO GONZALEZ', 'tipo' => 'texto_corto'],
            ['campo_id' => $moneda, 'entidad_id' => self::ENTIDAD, 'valor' => '272.32', 'tipo' => 'moneda'],
            ['campo_id' => $entero, 'entidad_id' => self::ENTIDAD, 'valor' => '21', 'tipo' => 'numero_entero'],
        ]);

        $this->assertDatabaseCount('valores_campo_personalizado', 3);
        $this->assertDatabaseHas('valores_campo_personalizado', [
            'campo_personalizado_id' => $texto, 'entidad_id' => self::ENTIDAD,
            'valor_texto_corto' => 'MICHELANGELO GONZALEZ', 'valor_moneda_monto' => null,
        ]);
        $this->assertDatabaseHas('valores_campo_personalizado', [
            'campo_personalizado_id' => $moneda, 'entidad_id' => self::ENTIDAD,
            'valor_moneda_monto' => '272.32', 'valor_moneda_codigo' => 'USD', 'valor_texto_corto' => null,
        ]);
        $this->assertDatabaseHas('valores_campo_personalizado', [
            'campo_personalizado_id' => $entero, 'entidad_id' => self::ENTIDAD, 'valor_numero_entero' => 21,
        ]);
    }

    public function test_segundo_lote_actualiza_valores_sin_duplicar_filas(): void
    {
        [$repo, $texto, $moneda] = $this->camposDePrueba();

        $repo->guardarValoresEnLote([
            ['campo_id' => $texto, 'entidad_id' => self::ENTIDAD, 'valor' => 'ANTES', 'tipo' => 'texto_corto'],
            ['campo_id' => $moneda, 'entidad_id' => self::ENTIDAD, 'valor' => '100', 'tipo' => 'moneda'],
        ]);
        $repo->guardarValoresEnLote([
            ['campo_id' => $moneda, 'entidad_id' => self::ENTIDAD, 'valor' => '300.5', 'tipo' => 'moneda'],
            ['campo_id' => $texto, 'entidad_id' => self::ENTIDAD, 'valor' => 'DESPUES', 'tipo' => 'texto_corto'],
        ]);

        $this->assertDatabaseCount('valores_campo_personalizado', 2);
        $this->assertDatabaseHas('valores_campo_personalizado', [
            'campo_personalizado_id' => $texto, 'entidad_id' => self::ENTIDAD, 'valor_texto_corto' => 'DESPUES',
        ]);
        $this->assertDatabaseHas('valores_campo_personalizado', [
            'campo_personalizado_id' => $moneda, 'entidad_id' => self::ENTIDAD, 'valor_moneda_monto' => '300.50',
        ]);
    }

    /** @return array{0: CampoPersonalizadoImportacionRepository, 1: int, 2: int, 3: int} */
    private function camposDePrueba(): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $repo = app(CampoPersonalizadoImportacionRepository::class);
        $proyectoId = (int) $proyecto->id;
        $carteraId = (int) $cartera->id;

        return [
            $repo,
            $repo->crearCampo($proyectoId, $carteraId, 'nombre_del_titular', 'Nombre del titular', TipoCampo::TEXTO_CORTO),
            $repo->crearCampo($proyectoId, $carteraId, 'saldo_total', 'Saldo total', TipoCampo::MONEDA),
            $repo->crearCampo($proyectoId, $carteraId, 'dias_en_atraso', 'Días en atraso', TipoCampo::NUMERO_ENTERO),
        ];
    }
}
