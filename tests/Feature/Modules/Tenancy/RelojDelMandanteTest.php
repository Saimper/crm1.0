<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Modules\Tenancy\Application\Services\RelojDelMandante;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El día se corta donde opera el cliente, no donde está el servidor.
 *
 * Todo se guarda en UTC y así sigue —`app.timezone` no se toca, porque
 * cambiarlo haría que Eloquent reinterpretara como hora local lo ya escrito—.
 * La conversión va en los dos bordes: al mostrar una fecha y al cortar un rango.
 */
final class RelojDelMandanteTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_el_inicio_del_dia_se_calcula_en_la_zona_del_cliente(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);

        // 8 de septiembre, 02:00 UTC = 7 de septiembre, 21:00 en Panamá.
        // Para el servidor ya es día 8; para la operación sigue siendo el 7.
        Carbon::setTestNow(Carbon::parse('2026-09-08 02:00:00', 'UTC'));

        $desde = app(RelojDelMandante::class)->inicioDe('hoy', (int) $mandante->id);

        $this->assertSame(
            '2026-09-07 05:00:00',
            $desde->toDateTimeString(),
            'Las 00:00 del 7 en Panamá son las 05:00 UTC del 7.'
        );

        Carbon::setTestNow();
    }

    public function test_una_gestion_de_la_tarde_cuenta_en_su_propio_dia(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);

        // El caso que rompía el informe: una gestión a las 20:30 hora de Panamá
        // se guarda como 01:30 UTC del día siguiente. Con el corte en UTC caía
        // en el día de después, y el supervisor no la veía en su informe diario.
        $gestion = Carbon::parse('2026-09-08 01:30:00', 'UTC');

        Carbon::setTestNow(Carbon::parse('2026-09-08 03:00:00', 'UTC')); // 22:00 del 7 en Panamá

        $reloj = app(RelojDelMandante::class);
        $desde = $reloj->inicioDe('hoy', (int) $mandante->id);

        $this->assertTrue(
            $gestion->greaterThanOrEqualTo($desde),
            'La gestión de las 20:30 locales pertenece al día que el gestor estaba trabajando.'
        );

        $this->assertSame('2026-09-07', $reloj->hoy((int) $mandante->id));

        Carbon::setTestNow();
    }

    public function test_sin_zona_configurada_se_comporta_como_antes(): void
    {
        $mandante = $this->crearMandante();

        Carbon::setTestNow(Carbon::parse('2026-09-08 02:00:00', 'UTC'));

        // El default es UTC, así que estrenar la configuración regional no mueve
        // ni un número para quien no la ajuste.
        $this->assertSame(
            '2026-09-08 00:00:00',
            app(RelojDelMandante::class)->inicioDe('hoy', (int) $mandante->id)->toDateTimeString(),
        );

        Carbon::setTestNow();
    }

    public function test_cada_mandante_tiene_su_propio_dia(): void
    {
        $panama = $this->crearMandante();
        $madrid = $this->crearMandante();
        DB::table('mandantes')->where('id', $panama->id)->update(['zona_horaria' => 'America/Panama']);
        DB::table('mandantes')->where('id', $madrid->id)->update(['zona_horaria' => 'Europe/Madrid']);

        Carbon::setTestNow(Carbon::parse('2026-09-08 02:00:00', 'UTC'));

        $reloj = app(RelojDelMandante::class);

        // El mismo instante es día 7 para uno y día 8 para el otro. Es
        // exactamente por esto que la configuración va en el mandante.
        $this->assertSame('2026-09-07', $reloj->hoy((int) $panama->id));
        $this->assertSame('2026-09-08', $reloj->hoy((int) $madrid->id));

        Carbon::setTestNow();
    }

    public function test_la_moneda_tambien_es_del_cliente(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['moneda' => 'COP']);

        $this->assertSame('COP', app(RelojDelMandante::class)->monedaDe((int) $mandante->id));
    }

    public function test_la_semana_empieza_donde_diga_el_cliente(): void
    {
        $lunes = $this->crearMandante();
        $domingo = $this->crearMandante();
        DB::table('mandantes')->where('id', $domingo->id)->update(['inicio_semana' => 7]);

        // Miércoles 9 de septiembre de 2026.
        Carbon::setTestNow(Carbon::parse('2026-09-09 12:00:00', 'UTC'));

        $reloj = app(RelojDelMandante::class);

        $this->assertSame('2026-09-07', $reloj->inicioDe('semana', (int) $lunes->id)->toDateString());
        $this->assertSame('2026-09-06', $reloj->inicioDe('semana', (int) $domingo->id)->toDateString());

        Carbon::setTestNow();
    }

    public function test_el_rango_preestablecido_de_hoy_es_el_dia_del_cliente_en_utc(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);

        // 02:00 UTC del 8 = 21:00 del 7 en Panamá: «hoy» es el 7, de 05:00 UTC
        // del 7 a 04:59:59 UTC del 8.
        Carbon::setTestNow(Carbon::parse('2026-09-08 02:00:00', 'UTC'));

        $rango = app(RelojDelMandante::class)->rangoPreestablecido('hoy', (int) $mandante->id);

        $this->assertSame('2026-09-07 05:00:00', $rango['desde']->toDateTimeString());
        $this->assertSame('2026-09-08 04:59:59', $rango['hasta']->toDateTimeString());
        $this->assertSame('UTC', $rango['desde']->timezoneName);

        Carbon::setTestNow();
    }

    public function test_ayer_es_el_dia_anterior_completo_del_cliente(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);

        Carbon::setTestNow(Carbon::parse('2026-09-08 02:00:00', 'UTC'));

        $rango = app(RelojDelMandante::class)->rangoPreestablecido('ayer', (int) $mandante->id);

        $this->assertSame('2026-09-06 05:00:00', $rango['desde']->toDateTimeString());
        $this->assertSame('2026-09-07 04:59:59', $rango['hasta']->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_la_semana_preestablecida_son_los_ultimos_siete_dias_incluido_hoy(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama', 'inicio_semana' => 7]);

        Carbon::setTestNow(Carbon::parse('2026-09-08 02:00:00', 'UTC'));

        // Es el rango de Reportes operativos, no la semana natural de
        // `inicioDe`: del 1 al 7 aunque el cliente empiece la semana en domingo.
        $rango = app(RelojDelMandante::class)->rangoPreestablecido('semana', (int) $mandante->id);

        $this->assertSame('2026-09-01 05:00:00', $rango['desde']->toDateTimeString());
        $this->assertSame('2026-09-08 04:59:59', $rango['hasta']->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_el_mes_preestablecido_es_el_mes_en_curso_hasta_hoy(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);

        Carbon::setTestNow(Carbon::parse('2026-09-08 02:00:00', 'UTC'));

        $rango = app(RelojDelMandante::class)->rangoPreestablecido('mes', (int) $mandante->id);

        $this->assertSame('2026-09-01 05:00:00', $rango['desde']->toDateTimeString());
        $this->assertSame('2026-09-08 04:59:59', $rango['hasta']->toDateTimeString());

        // Y cualquier clave desconocida cuenta como «hoy».
        $desconocido = app(RelojDelMandante::class)->rangoPreestablecido('trimestre', (int) $mandante->id);
        $this->assertSame('2026-09-07 05:00:00', $desconocido['desde']->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_un_rango_de_fechas_se_corta_en_el_calendario_del_cliente(): void
    {
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update(['zona_horaria' => 'America/Panama']);

        $rango = app(RelojDelMandante::class)->rangoDeFechas('2026-09-07', '2026-09-08', (int) $mandante->id);

        // Del inicio del 7 al final del 8 en Panamá, expresados en UTC.
        $this->assertSame('2026-09-07 05:00:00', $rango['desde']->toDateTimeString());
        $this->assertSame('2026-09-09 04:59:59', $rango['hasta']->toDateTimeString());
        $this->assertSame('UTC', $rango['hasta']->timezoneName);
    }

    public function test_sin_zona_configurada_un_rango_de_fechas_es_el_de_siempre(): void
    {
        $mandante = $this->crearMandante();

        $rango = app(RelojDelMandante::class)->rangoDeFechas('2026-09-07', '2026-09-07', (int) $mandante->id);

        $this->assertSame('2026-09-07 00:00:00', $rango['desde']->toDateTimeString());
        $this->assertSame('2026-09-07 23:59:59', $rango['hasta']->toDateTimeString());
    }
}
