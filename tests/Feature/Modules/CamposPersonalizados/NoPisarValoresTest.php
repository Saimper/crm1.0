<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Un `null` entrante no borra lo que ya estaba guardado.
 *
 * Esto no es una precaución teórica. En la réplica de producción hay tres filas
 * de `valores_campo_personalizado` con las once columnas en blanco y
 * `actualizada_en` dos días posterior a la importación: son exactamente los tres
 * casos donde alguien registró una gestión. El formulario de gestión enviaba los
 * 34 campos del caso, incluidos los tres de tipo `moneda` cuyos valores viven en
 * `valor_texto_corto` desde que se les cambió el tipo desde la UI. Los leía como
 * `null` y los reescribía como `null`.
 *
 * Quien es dueño del formulario completo —Editar caso, el formulario de campos,
 * el gestor de registros de entidades— sí puede vaciar, y lo pide explícito.
 */
final class NoPisarValoresTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_un_null_entrante_no_borra_un_valor_ya_guardado(): void
    {
        [$proyecto, $cartera, $campoId] = $this->campoEn('texto_corto');

        $servicio = $this->app->make(ServicioCamposPersonalizados::class);

        $servicio->guardarValores(
            proyectoId: (int) $proyecto->id,
            ambito: AmbitoCampo::CASO,
            ambitoId: (int) $cartera->id,
            entidadId: 4242,
            valoresPorCodigo: ['dato' => 'TRX-ABC-123'],
        );

        $servicio->guardarValores(
            proyectoId: (int) $proyecto->id,
            ambito: AmbitoCampo::CASO,
            ambitoId: (int) $cartera->id,
            entidadId: 4242,
            valoresPorCodigo: ['dato' => null],
        );

        $this->assertSame('TRX-ABC-123', DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->where('entidad_id', 4242)
            ->value('valor_texto_corto'));
    }

    public function test_el_dueno_del_formulario_si_puede_vaciar(): void
    {
        [$proyecto, $cartera, $campoId] = $this->campoEn('texto_corto');

        $servicio = $this->app->make(ServicioCamposPersonalizados::class);

        $servicio->guardarValores(
            proyectoId: (int) $proyecto->id,
            ambito: AmbitoCampo::CASO,
            ambitoId: (int) $cartera->id,
            entidadId: 4242,
            valoresPorCodigo: ['dato' => 'TRX-ABC-123'],
        );

        $servicio->guardarValores(
            proyectoId: (int) $proyecto->id,
            ambito: AmbitoCampo::CASO,
            ambitoId: (int) $cartera->id,
            entidadId: 4242,
            valoresPorCodigo: ['dato' => null],
            permitirVaciar: true,
        );

        $this->assertNull(DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->where('entidad_id', 4242)
            ->value('valor_texto_corto'));
    }

    /**
     * El escenario exacto de producción: el campo es `moneda`, el valor está en
     * `valor_texto_corto` porque alguien cambió el tipo desde la UI, el lector
     * mira `valor_moneda_monto` y devuelve null.
     */
    public function test_un_valor_descolocado_por_un_cambio_de_tipo_sobrevive_a_una_gestion(): void
    {
        [$proyecto, $cartera, $campoId] = $this->campoEn('moneda');

        DB::table('valores_campo_personalizado')->insert([
            'campo_personalizado_id' => $campoId,
            'entidad_id' => 4242,
            'valor_texto_corto' => '390.00',
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $this->app->make(ServicioCamposPersonalizados::class)->guardarValores(
            proyectoId: (int) $proyecto->id,
            ambito: AmbitoCampo::CASO,
            ambitoId: (int) $cartera->id,
            entidadId: 4242,
            valoresPorCodigo: ['dato' => null],
        );

        $this->assertSame('390.00', DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)->where('entidad_id', 4242)
            ->value('valor_texto_corto'), 'el valor descolocado se borró al guardar una gestión');
    }

    public function test_sin_valor_previo_un_null_sigue_creando_la_fila_vacia(): void
    {
        [$proyecto, $cartera, $campoId] = $this->campoEn('texto_corto');

        $this->app->make(ServicioCamposPersonalizados::class)->guardarValores(
            proyectoId: (int) $proyecto->id,
            ambito: AmbitoCampo::CASO,
            ambitoId: (int) $cartera->id,
            entidadId: 4242,
            valoresPorCodigo: ['dato' => null],
        );

        $this->assertDatabaseHas('valores_campo_personalizado', [
            'campo_personalizado_id' => $campoId,
            'entidad_id' => 4242,
            'valor_texto_corto' => null,
        ]);
    }

    /** El guard no puede cruzar proyectos: mira sólo los campos del ámbito pedido. */
    public function test_el_guard_no_mira_valores_de_otro_proyecto(): void
    {
        [$proyectoA, $carteraA, $campoA] = $this->campoEn('texto_corto');
        [$proyectoB, $carteraB, $campoB] = $this->campoEn('texto_corto');

        $servicio = $this->app->make(ServicioCamposPersonalizados::class);

        // Misma entidadId en los dos proyectos, valor sólo en A.
        $servicio->guardarValores((int) $proyectoA->id, AmbitoCampo::CASO, (int) $carteraA->id, 4242, ['dato' => 'DE_A']);

        $servicio->guardarValores((int) $proyectoB->id, AmbitoCampo::CASO, (int) $carteraB->id, 4242, ['dato' => null]);

        $this->assertSame('DE_A', DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoA)->where('entidad_id', 4242)->value('valor_texto_corto'));
        $this->assertNull(DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoB)->where('entidad_id', 4242)->value('valor_texto_corto'));
    }

    /** @return array{0: stdClass, 1: stdClass, 2: int} */
    private function campoEn(string $tipo): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);

        $campoId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $cartera->id,
            'codigo' => 'dato',
            'etiqueta' => 'Dato',
            'tipo' => $tipo,
            'obligatorio' => false,
            'activo' => true,
            'orden' => 0,
            'reglas' => json_encode([]),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return [$proyecto, $cartera, $campoId];
    }
}
