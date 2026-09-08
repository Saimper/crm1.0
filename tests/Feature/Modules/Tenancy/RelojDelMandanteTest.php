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
}
