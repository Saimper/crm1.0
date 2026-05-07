<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\EntidadesConfigurables;

use App\Modules\EntidadesConfigurables\Application\Services\ServicioEntidades;
use App\Modules\EntidadesConfigurables\Domain\ValueObjects\RelacionEntidad;
use App\Modules\EntidadesConfigurables\Infrastructure\Http\Livewire\PanelEntidadesVinculadas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class PanelEntidadesVinculadasTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_no_renderiza_nada_si_proyecto_sin_entidades_caso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $caso = $this->crearCasoMinimo($proyecto);
        $this->actuarComoSupervisor($proyecto);

        Livewire::test(PanelEntidadesVinculadas::class, [
            'proyectoId' => (int) $proyecto->id,
            'vinculo' => 'caso',
            'vinculoId' => $caso['casoId'],
        ])->assertViewHas('bloques', []);
    }

    public function test_renderiza_entidades_relacion_caso_filtradas_por_proyecto(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCobranza();
        $servicio = $this->app->make(ServicioEntidades::class);

        $servicio->crearEntidad(
            proyectoId: (int) $proyectoA->id,
            codigo: 'POLIZA_A',
            nombre: 'Póliza A',
            relacion: RelacionEntidad::CASO,
        );
        $servicio->crearEntidad(
            proyectoId: (int) $proyectoB->id,
            codigo: 'POLIZA_B',
            nombre: 'Póliza B',
            relacion: RelacionEntidad::CASO,
        );

        $casoA = $this->crearCasoMinimo($proyectoA);
        $this->actuarComoSupervisor($proyectoA);

        $c = Livewire::test(PanelEntidadesVinculadas::class, [
            'proyectoId' => (int) $proyectoA->id,
            'vinculo' => 'caso',
            'vinculoId' => $casoA['casoId'],
        ]);

        $bloques = $c->viewData('bloques');
        $this->assertCount(1, $bloques);
        $this->assertSame('POLIZA_A', (string) $bloques[0]['entidad']->codigo);
    }

    public function test_no_muestra_entidades_relacion_persona_en_vinculo_caso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $servicio = $this->app->make(ServicioEntidades::class);
        $servicio->crearEntidad(
            proyectoId: (int) $proyecto->id,
            codigo: 'CONTACTO_EXTRA',
            nombre: 'Contacto extra',
            relacion: RelacionEntidad::PERSONA,
        );

        $caso = $this->crearCasoMinimo($proyecto);
        $this->actuarComoSupervisor($proyecto);

        Livewire::test(PanelEntidadesVinculadas::class, [
            'proyectoId' => (int) $proyecto->id,
            'vinculo' => 'caso',
            'vinculoId' => $caso['casoId'],
        ])->assertViewHas('bloques', []);
    }

    public function test_lista_solo_registros_del_caso_actual(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $servicio = $this->app->make(ServicioEntidades::class);
        $entidadId = $servicio->crearEntidad(
            proyectoId: (int) $proyecto->id,
            codigo: 'POLIZA',
            nombre: 'Póliza',
            relacion: RelacionEntidad::CASO,
        );

        $casoA = $this->crearCasoMinimo($proyecto);
        $casoB = $this->crearCasoMinimo($proyecto);

        $servicio->crearRegistro(
            proyectoId: (int) $proyecto->id,
            entidadId: $entidadId,
            titulo: 'Reg A',
            valoresPorCodigo: [],
            casoId: $casoA['casoId'],
        );
        $servicio->crearRegistro(
            proyectoId: (int) $proyecto->id,
            entidadId: $entidadId,
            titulo: 'Reg B',
            valoresPorCodigo: [],
            casoId: $casoB['casoId'],
        );

        $this->actuarComoSupervisor($proyecto);

        $c = Livewire::test(PanelEntidadesVinculadas::class, [
            'proyectoId' => (int) $proyecto->id,
            'vinculo' => 'caso',
            'vinculoId' => $casoA['casoId'],
        ]);

        $bloques = $c->viewData('bloques');
        $this->assertCount(1, $bloques);
        $titulos = collect($bloques[0]['registros'])->pluck('titulo')->all();
        $this->assertSame(['Reg A'], $titulos);
    }

    public function test_filtra_por_cartera_si_entidad_carterizada(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $carteraA = $this->crearCarteraEn($proyecto);
        $carteraB = $this->crearCarteraEn($proyecto);
        $servicio = $this->app->make(ServicioEntidades::class);

        $servicio->crearEntidad(
            proyectoId: (int) $proyecto->id,
            codigo: 'EXCLUSIVA_A',
            nombre: 'Solo cartera A',
            relacion: RelacionEntidad::CASO,
            carteraId: (int) $carteraA->id,
        );

        $caso = $this->crearCasoMinimo($proyecto, $carteraB);
        $this->actuarComoSupervisor($proyecto);

        Livewire::test(PanelEntidadesVinculadas::class, [
            'proyectoId' => (int) $proyecto->id,
            'vinculo' => 'caso',
            'vinculoId' => $caso['casoId'],
            'carteraId' => (int) $carteraB->id,
        ])->assertViewHas('bloques', []);

        $casoA = $this->crearCasoMinimo($proyecto, $carteraA);
        $c = Livewire::test(PanelEntidadesVinculadas::class, [
            'proyectoId' => (int) $proyecto->id,
            'vinculo' => 'caso',
            'vinculoId' => $casoA['casoId'],
            'carteraId' => (int) $carteraA->id,
        ]);
        $this->assertCount(1, $c->viewData('bloques'));
    }

    public function test_permite_crear_registro_via_panel(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $servicio = $this->app->make(ServicioEntidades::class);
        $entidadId = $servicio->crearEntidad(
            proyectoId: (int) $proyecto->id,
            codigo: 'POLIZA',
            nombre: 'Póliza',
            relacion: RelacionEntidad::CASO,
        );

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'entidad_configurable',
            'ambito_id' => $entidadId,
            'codigo' => 'numero_poliza',
            'etiqueta' => 'Número de póliza',
            'tipo' => 'texto_corto',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 1,
            'reglas' => json_encode([]),
        ]);

        $caso = $this->crearCasoMinimo($proyecto);
        $this->actuarComoSupervisor($proyecto);

        Livewire::test(PanelEntidadesVinculadas::class, [
            'proyectoId' => (int) $proyecto->id,
            'vinculo' => 'caso',
            'vinculoId' => $caso['casoId'],
        ])
            ->call('abrirFormCrear', $entidadId)
            ->set('titulo', 'Póliza 2026-001')
            ->set('valores.numero_poliza', 'POL-12345')
            ->call('guardar')
            ->assertHasNoErrors();

        $registro = DB::table('entidades_registros')
            ->where('proyecto_id', $proyecto->id)
            ->where('entidad_configurable_id', $entidadId)
            ->where('caso_id', $caso['casoId'])
            ->first();
        $this->assertNotNull($registro);
        $this->assertSame('Póliza 2026-001', (string) $registro->titulo);

        $valor = DB::table('valores_campo_personalizado as v')
            ->join('campos_personalizados as c', 'c.id', '=', 'v.campo_personalizado_id')
            ->where('v.entidad_id', $registro->id)
            ->where('c.codigo', 'numero_poliza')
            ->value('v.valor_texto_corto');
        $this->assertSame('POL-12345', (string) $valor);
    }

    public function test_carga_valores_seleccion_unica_y_multiple_al_editar(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $servicio = $this->app->make(ServicioEntidades::class);
        $entidadId = $servicio->crearEntidad(
            proyectoId: (int) $proyecto->id,
            codigo: 'POLIZA',
            nombre: 'Póliza',
            relacion: RelacionEntidad::CASO,
        );

        $campoUnicaId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'entidad_configurable',
            'ambito_id' => $entidadId,
            'codigo' => 'tipo_poliza',
            'etiqueta' => 'Tipo',
            'tipo' => 'seleccion_unica',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 1,
            'reglas' => json_encode([]),
        ]);
        $campoMulId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'entidad_configurable',
            'ambito_id' => $entidadId,
            'codigo' => 'coberturas',
            'etiqueta' => 'Coberturas',
            'tipo' => 'seleccion_multiple',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 2,
            'reglas' => json_encode([]),
        ]);

        $opcionUnicaId = (int) DB::table('opciones_campo_personalizado')->insertGetId([
            'campo_personalizado_id' => $campoUnicaId,
            'codigo' => 'VEHICULAR',
            'etiqueta' => 'Vehicular',
            'activo' => true,
            'orden' => 1,
        ]);
        $opMulIds = [];
        foreach (['ROBO', 'INCENDIO', 'COLISION'] as $i => $cod) {
            $opMulIds[] = (int) DB::table('opciones_campo_personalizado')->insertGetId([
                'campo_personalizado_id' => $campoMulId,
                'codigo' => $cod,
                'etiqueta' => ucfirst(strtolower($cod)),
                'activo' => true,
                'orden' => $i + 1,
            ]);
        }

        $caso = $this->crearCasoMinimo($proyecto);
        $registroId = $servicio->crearRegistro(
            proyectoId: (int) $proyecto->id,
            entidadId: $entidadId,
            titulo: 'Reg con selección',
            valoresPorCodigo: [
                'tipo_poliza' => $opcionUnicaId,
                'coberturas' => $opMulIds,
            ],
            casoId: $caso['casoId'],
        );

        $this->actuarComoSupervisor($proyecto);

        $c = Livewire::test(PanelEntidadesVinculadas::class, [
            'proyectoId' => (int) $proyecto->id,
            'vinculo' => 'caso',
            'vinculoId' => $caso['casoId'],
        ])->call('abrirFormEditar', $entidadId, $registroId);

        $valores = $c->get('valores');
        $this->assertSame($opcionUnicaId, $valores['tipo_poliza']);
        $this->assertSame($opMulIds, $valores['coberturas']);
    }

    /**
     * @return array{casoId:int}
     */
    private function crearCasoMinimo(stdClass $proyecto, ?stdClass $cartera = null): array
    {
        $cartera ??= $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto);

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => Carbon::now()->toDateString(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return ['casoId' => $casoId];
    }

    private function actuarComoSupervisor(stdClass $proyecto): void
    {
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));
    }
}
