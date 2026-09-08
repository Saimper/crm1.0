<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use App\Modules\CamposPersonalizados\Application\Services\ServicioCamposPersonalizados;
use App\Modules\CamposPersonalizados\Domain\Exceptions\ReglaViolada;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\ContextoUsuarioProyecto;
use App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire\AdminCamposPersonalizados;
use App\Modules\CamposPersonalizados\Infrastructure\Http\Livewire\FormularioCamposPersonalizados;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ReglasAvanzadasF30Test extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        CarbonImmutable::setTestNow('2026-04-30 09:15:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_admin_persiste_reglas_avanzadas_en_json(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $tipoGestionId = $this->crearCascadaGestionEn($proyecto, ['codigo_tipo' => 'LLAMADA_SALIENTE'])['tipo_gestion_id'];

        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(AdminCamposPersonalizados::class)
            ->call('abrirFormCrear')
            ->set('form.proyecto_id', (int) $proyecto->id)
            ->set('form.ambito', 'gestion')
            ->set('form.ambito_id', $tipoGestionId)
            ->set('form.codigo', 'fecha_promesa')
            ->set('form.etiqueta', 'Fecha de promesa')
            ->set('form.tipo', 'fecha')
            ->set('form.fecha_minima_preset', 'hoy')
            ->set('form.solo_lectura_tras_guardar', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $row = DB::table('campos_personalizados')->where('codigo', 'fecha_promesa')->first();
        $this->assertNotNull($row);
        $reglas = json_decode((string) $row->reglas, true);
        $this->assertSame('hoy', $reglas['fecha_minima']);
        $this->assertTrue((bool) $reglas['solo_lectura_tras_guardar']);
    }

    public function test_servicio_lanza_regla_violada_cuando_fecha_es_anterior_a_minima(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $proyectoId = (int) $proyecto->id;
        $tipoGestionId = $this->crearCascadaGestionEn($proyecto, ['codigo_tipo' => 'LLAMADA_SALIENTE'])['tipo_gestion_id'];

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyectoId,
            'ambito' => 'gestion',
            'ambito_id' => $tipoGestionId,
            'tipo' => 'fecha',
            'codigo' => 'fecha_promesa',
            'etiqueta' => 'Fecha de promesa',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 0,
            'reglas' => json_encode(['fecha_minima' => 'hoy']),
        ]);

        $servicio = $this->app->make(ServicioCamposPersonalizados::class);

        $this->expectException(ReglaViolada::class);
        $this->expectExceptionMessage('debe ser igual o posterior a hoy');
        $servicio->guardarValores($proyectoId, AmbitoCampo::GESTION, $tipoGestionId, entidadId: 99, valoresPorCodigo: [
            'fecha_promesa' => '2026-04-29',
        ]);
    }

    public function test_auto_fill_now_precarga_form_con_timestamp_actual(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $proyectoId = (int) $proyecto->id;
        $tipoGestionId = $this->crearCascadaGestionEn($proyecto, ['codigo_tipo' => 'NOTA'])['tipo_gestion_id'];

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyectoId,
            'ambito' => 'gestion',
            'ambito_id' => $tipoGestionId,
            'tipo' => 'fecha_hora',
            'codigo' => 'fecha_comentario',
            'etiqueta' => 'Fecha del comentario',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 0,
            'reglas' => json_encode(['auto_fill' => 'now', 'solo_lectura_tras_guardar' => true]),
        ]);

        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(FormularioCamposPersonalizados::class, [
            'proyectoId' => $proyectoId,
            'ambito' => 'gestion',
            'ambitoId' => $tipoGestionId,
            'entidadId' => 12345,
        ])
            ->assertSet('valores.fecha_comentario', '2026-04-30T09:15');
    }

    public function test_auto_fill_proyecto_codigo_se_aplica_al_renderizar(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $proyectoId = (int) $proyecto->id;
        $carteraId = (int) $this->crearCarteraEn($proyecto, 'CONSUMO')->id;

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyectoId,
            'ambito' => 'caso',
            'ambito_id' => $carteraId,
            'tipo' => 'texto_corto',
            'codigo' => 'origen_proyecto',
            'etiqueta' => 'Origen',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 0,
            'reglas' => json_encode(['auto_fill' => 'proyecto_codigo']),
        ]);

        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(FormularioCamposPersonalizados::class, [
            'proyectoId' => $proyectoId,
            'ambito' => 'caso',
            'ambitoId' => $carteraId,
            'entidadId' => 5555,
        ])
            ->assertSet('valores.origen_proyecto', (string) $proyecto->codigo);
    }

    public function test_solo_lectura_tras_guardar_marca_campo_disabled_cuando_existe_valor(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $proyectoId = (int) $proyecto->id;
        $carteraId = (int) $this->crearCarteraEn($proyecto, 'CONSUMO')->id;

        $campoId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyectoId,
            'ambito' => 'caso',
            'ambito_id' => $carteraId,
            'tipo' => 'texto_corto',
            'codigo' => 'firma_inicial',
            'etiqueta' => 'Firma',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 0,
            'reglas' => json_encode(['solo_lectura_tras_guardar' => true]),
        ]);
        DB::table('valores_campo_personalizado')->insert([
            'campo_personalizado_id' => $campoId,
            'entidad_id' => 7777,
            'valor_texto_corto' => 'firma original',
        ]);

        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(FormularioCamposPersonalizados::class, [
            'proyectoId' => $proyectoId,
            'ambito' => 'caso',
            'ambitoId' => $carteraId,
            'entidadId' => 7777,
        ])
            ->assertSet('valores.firma_inicial', 'firma original')
            ->assertSet('camposSoloLectura.firma_inicial', true);
    }

    public function test_multi_tenancy_reglas_de_proyecto_a_no_aplican_a_proyecto_b(): void
    {
        // Dos proyectos del MISMO mandante, como en el escenario demo original
        // (COBRANZA_DEMO_2026 y SOPORTE_DEMO_2026 colgaban de BPO_DEMO): lo que
        // aísla las reglas es el proyecto, no el mandante.
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCx($mandante);

        $proyA = (int) $proyectoA->id;
        $proyB = (int) $proyectoB->id;
        $tgA = $this->crearCascadaGestionEn($proyectoA)['tipo_gestion_id'];
        $tgB = $this->crearCascadaGestionEn($proyectoB)['tipo_gestion_id'];

        DB::table('campos_personalizados')->insert([
            [
                'proyecto_id' => $proyA, 'ambito' => 'gestion', 'ambito_id' => $tgA,
                'tipo' => 'fecha', 'codigo' => 'fecha_x', 'etiqueta' => 'Fecha A',
                'obligatorio' => false, 'activo' => true, 'orden' => 0,
                'reglas' => json_encode(['fecha_minima' => 'hoy']),
            ],
            [
                'proyecto_id' => $proyB, 'ambito' => 'gestion', 'ambito_id' => $tgB,
                'tipo' => 'fecha', 'codigo' => 'fecha_x', 'etiqueta' => 'Fecha B',
                'obligatorio' => false, 'activo' => true, 'orden' => 0,
                'reglas' => json_encode([]),
            ],
        ]);

        $servicio = $this->app->make(ServicioCamposPersonalizados::class);

        // Proyecto B sin regla acepta fecha de ayer.
        $servicio->guardarValores($proyB, AmbitoCampo::GESTION, $tgB, entidadId: 1, valoresPorCodigo: [
            'fecha_x' => '2026-04-29',
        ]);
        $this->assertDatabaseHas('valores_campo_personalizado', [
            'entidad_id' => 1,
            'valor_fecha' => '2026-04-29',
        ]);

        // Proyecto A con regla rechaza la misma fecha.
        $this->expectException(ReglaViolada::class);
        $servicio->guardarValores($proyA, AmbitoCampo::GESTION, $tgA, entidadId: 2, valoresPorCodigo: [
            'fecha_x' => '2026-04-29',
        ]);
    }

    public function test_campo_existente_sin_reglas_nuevas_sigue_funcionando(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $proyectoId = (int) $proyecto->id;
        $carteraId = (int) $this->crearCarteraEn($proyecto, 'CONSUMO')->id;

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyectoId,
            'ambito' => 'caso',
            'ambito_id' => $carteraId,
            'tipo' => 'texto_corto',
            'codigo' => 'legacy',
            'etiqueta' => 'Legacy',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 0,
            'reglas' => json_encode(['longitud_max' => 50]),
        ]);

        $servicio = $this->app->make(ServicioCamposPersonalizados::class);
        $servicio->guardarValores($proyectoId, AmbitoCampo::CASO, $carteraId, entidadId: 42, valoresPorCodigo: [
            'legacy' => 'valor previo',
        ]);

        $this->assertDatabaseHas('valores_campo_personalizado', [
            'entidad_id' => 42,
            'valor_texto_corto' => 'valor previo',
        ]);

        // valoresAutoRelleno sobre campo legacy retorna vacío.
        $ctx = new ContextoUsuarioProyecto(1, 'X', 'x@x.io', 'COB');
        $auto = $servicio->valoresAutoRelleno($proyectoId, AmbitoCampo::CASO, $carteraId, $ctx);
        $this->assertSame([], $auto);
    }
}
