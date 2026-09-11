<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cobranza;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * `casos_cobranza.tramo_mora_id` existía y nada lo calculaba: la columna estaba
 * al 100% en NULL en producción y la cartera no se podía segmentar por mora.
 */
final class AsignarTramosMoraTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function crearTramo(stdClass $proyecto, string $codigo, int $desde, ?int $hasta, int $orden): int
    {
        return (int) DB::table('tramos_mora')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => $codigo,
            'dias_desde' => $desde,
            'dias_hasta' => $hasta,
            'activo' => true,
            'orden' => $orden,
        ]);
    }

    private function crearCasoCobranza(stdClass $proyecto, ?int $diasMora): int
    {
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto);

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => '2026-09-01',
        ]);

        DB::table('casos_cobranza')->insert([
            'caso_id' => $casoId,
            'proyecto_id' => $proyecto->id,
            'numero_prestamo' => "P-{$casoId}",
            'dias_mora' => $diasMora,
        ]);

        return $casoId;
    }

    public function test_clasifica_cada_caso_en_el_tramo_que_le_toca(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $alDia = $this->crearTramo($proyecto, 'AL_DIA', 0, 0, 10);
        $temprana = $this->crearTramo($proyecto, 'MORA_1_30', 1, 30, 20);
        $tardia = $this->crearTramo($proyecto, 'MORA_31_MAS', 31, null, 30);

        $casos = [
            $this->crearCasoCobranza($proyecto, 0) => $alDia,
            $this->crearCasoCobranza($proyecto, 1) => $temprana,
            $this->crearCasoCobranza($proyecto, 30) => $temprana,
            $this->crearCasoCobranza($proyecto, 31) => $tardia,
            $this->crearCasoCobranza($proyecto, 5000) => $tardia,
        ];

        $this->artisan('cobranza:asignar-tramos-mora')->assertSuccessful();

        foreach ($casos as $casoId => $tramoEsperado) {
            $this->assertSame(
                $tramoEsperado,
                (int) DB::table('casos_cobranza')->where('caso_id', $casoId)->value('tramo_mora_id'),
                "El caso {$casoId} quedó en el tramo equivocado."
            );
        }
    }

    public function test_un_caso_sin_dias_de_mora_se_queda_sin_tramo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearTramo($proyecto, 'TODO', 0, null, 10);
        $casoId = $this->crearCasoCobranza($proyecto, null);

        $this->artisan('cobranza:asignar-tramos-mora')->assertSuccessful();

        $this->assertNull(
            DB::table('casos_cobranza')->where('caso_id', $casoId)->value('tramo_mora_id'),
            'No se inventa un tramo sobre un dato que no existe.'
        );
    }

    public function test_la_simulacion_no_escribe(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearTramo($proyecto, 'TODO', 0, null, 10);
        $casoId = $this->crearCasoCobranza($proyecto, 45);

        $this->artisan('cobranza:asignar-tramos-mora --dry-run')->assertSuccessful();

        $this->assertNull(DB::table('casos_cobranza')->where('caso_id', $casoId)->value('tramo_mora_id'));
    }

    public function test_es_idempotente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->crearTramo($proyecto, 'TODO', 0, null, 10);
        $this->crearCasoCobranza($proyecto, 45);

        $this->artisan('cobranza:asignar-tramos-mora')->assertSuccessful();
        $this->artisan('cobranza:asignar-tramos-mora')->expectsOutputToContain('0 casos reclasificados')->assertSuccessful();
    }

    public function test_reclasifica_cuando_cambian_los_dias_de_mora(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $temprana = $this->crearTramo($proyecto, 'MORA_1_30', 1, 30, 10);
        $tardia = $this->crearTramo($proyecto, 'MORA_31_MAS', 31, null, 20);
        $casoId = $this->crearCasoCobranza($proyecto, 10);

        $this->artisan('cobranza:asignar-tramos-mora')->assertSuccessful();
        $this->assertSame($temprana, (int) DB::table('casos_cobranza')->where('caso_id', $casoId)->value('tramo_mora_id'));

        // Una importación posterior envejece la deuda.
        DB::table('casos_cobranza')->where('caso_id', $casoId)->update(['dias_mora' => 90]);

        $this->artisan('cobranza:asignar-tramos-mora')->assertSuccessful();
        $this->assertSame($tardia, (int) DB::table('casos_cobranza')->where('caso_id', $casoId)->value('tramo_mora_id'));
    }

    public function test_no_cruza_tramos_entre_proyectos(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCobranza();
        $tramoA = $this->crearTramo($proyectoA, 'TODO', 0, null, 10);
        $this->crearTramo($proyectoB, 'TODO', 0, null, 10);

        $casoA = $this->crearCasoCobranza($proyectoA, 45);

        $this->artisan('cobranza:asignar-tramos-mora --proyecto='.$proyectoA->id)->assertSuccessful();

        $this->assertSame($tramoA, (int) DB::table('casos_cobranza')->where('caso_id', $casoA)->value('tramo_mora_id'));
        $this->assertSame(
            0,
            DB::table('casos_cobranza as cc')->join('casos as k', 'k.id', '=', 'cc.caso_id')
                ->where('k.proyecto_id', $proyectoB->id)->whereNotNull('cc.tramo_mora_id')->count(),
            'Acotar por proyecto no debe tocar otro proyecto.'
        );
    }

    public function test_archived_portfolios_keep_their_last_classification(): void
    {
        $project = $this->crearProyectoCobranza();
        $old = $this->crearTramo($project, 'OLD', 0, 30, 10);
        $new = $this->crearTramo($project, 'NEW', 31, null, 20);
        $active = $this->crearCasoCobranza($project, 45);
        $archived = $this->crearCasoCobranza($project, 45);
        $inactive = $this->crearCasoCobranza($project, 45);
        DB::table('casos_cobranza')->whereIn('caso_id', [$active, $archived, $inactive])->update(['tramo_mora_id' => $old]);
        DB::table('carteras')->where('id', DB::table('casos')->where('id', $archived)->value('cartera_id'))->update(['eliminada_en' => now()]);
        DB::table('carteras')->where('id', DB::table('casos')->where('id', $inactive)->value('cartera_id'))->update(['activo' => false]);

        $this->artisan('cobranza:asignar-tramos-mora --dry-run')->expectsOutputToContain('1 casos cambiarían')->assertSuccessful();
        $this->artisan('cobranza:asignar-tramos-mora')->expectsOutputToContain('1 casos reclasificados')->assertSuccessful();
        $this->assertSame($new, (int) DB::table('casos_cobranza')->where('caso_id', $active)->value('tramo_mora_id'));
        foreach ([$archived, $inactive] as $caseId) {
            $this->assertSame($old, (int) DB::table('casos_cobranza')->where('caso_id', $caseId)->value('tramo_mora_id'));
        }
    }
}
