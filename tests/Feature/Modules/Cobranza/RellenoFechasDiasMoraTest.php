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
 * El relleno histórico de las fechas de la mora toma la fecha de la última
 * importación que escribió la fila, no `actualizada_en`, que la pisa cualquier
 * UPDATE —el cron de tramos, sin ir más lejos—.
 */
final class RellenoFechasDiasMoraTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_la_fecha_sale_de_la_importacion_que_trajo_la_fila_y_en_el_calendario_del_cliente(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);
        $proyecto = $this->crearProyectoCobranza($mandante);

        $importado = $this->casoCobranza($proyecto, 120);
        $manual = $this->casoCobranza($proyecto, 30);
        $sinMora = $this->casoCobranza($proyecto, null);

        // La importación terminó a las 02:30 UTC del día 3: en Panamá todavía
        // era el día 2 por la noche.
        $importacionId = (int) DB::table('importaciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'tipo_entidad' => 'caso_cobranza',
            'modo' => 'upsert',
            'estado' => 'completada',
            'usuario_id' => $this->crearSupervisor($proyecto)->id,
            'nombre_archivo' => 'cartera.csv',
            'total_filas' => 1,
            'terminado_en' => '2026-09-03 02:30:00',
            'creada_en' => '2026-09-03 02:00:00',
            'actualizada_en' => '2026-09-03 02:30:00',
        ]);
        DB::table('importacion_filas')->insert([
            'importacion_id' => $importacionId,
            'proyecto_id' => $proyecto->id,
            'numero_fila' => 1,
            'estado' => 'procesada',
            'payload' => '{}',
            'entidad_id' => $importado,
        ]);

        // Un UPDATE posterior cualquiera pisa actualizada_en: no debe contar.
        DB::table('casos_cobranza')->whereIn('caso_id', [$importado, $manual])->update(['tramo_mora_id' => null]);
        DB::table('casos_cobranza')->where('caso_id', $manual)->update(['creada_en' => '2026-08-20 23:30:00']);

        $this->reejecutarRelleno();

        $fila = DB::table('casos_cobranza')->where('caso_id', $importado)->first();
        self::assertSame('2026-09-02', (string) $fila->dias_mora_actualizado_en, 'Día 3 a las 02:30 UTC es día 2 en Panamá.');
        self::assertSame('2026-09-02', (string) $fila->dias_mora_confirmado_en);

        $fila = DB::table('casos_cobranza')->where('caso_id', $manual)->first();
        self::assertSame('2026-08-20', (string) $fila->dias_mora_actualizado_en, 'Sin importación, cuenta el alta.');

        $fila = DB::table('casos_cobranza')->where('caso_id', $sinMora)->first();
        self::assertNull($fila->dias_mora_actualizado_en, 'Sin valor no hay fecha que ponerle.');
        self::assertNull($fila->dias_mora_confirmado_en);
    }

    /**
     * Sin `down()`/`up()`: sería DDL dentro de la transacción del test, que en
     * MySQL hace COMMIT implícito y deja los datos del test en la base para los
     * que vienen detrás. Las columnas ya existen; se vacían y se rellenan.
     */
    private function reejecutarRelleno(): void
    {
        DB::table('casos_cobranza')->update([
            'dias_mora_actualizado_en' => null,
            'dias_mora_confirmado_en' => null,
        ]);

        /** @var object{rellenar: callable} $migracion */
        $migracion = require database_path('migrations/2026_09_09_120000_cobranza_casos_cobranza_add_fechas_dias_mora.php');
        $migracion->rellenar();
    }

    private function casoCobranza(stdClass $proyecto, ?int $diasMora): int
    {
        $casoId = $this->crearCasoEn($proyecto);

        DB::table('casos_cobranza')->insert([
            'caso_id' => $casoId,
            'proyecto_id' => $proyecto->id,
            'numero_prestamo' => "P-{$casoId}",
            'dias_mora' => $diasMora,
        ]);

        return $casoId;
    }
}
