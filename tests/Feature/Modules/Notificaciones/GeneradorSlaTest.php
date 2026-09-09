<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notificaciones;

use App\Modules\Notificaciones\Application\Services\GeneradorNotificaciones;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class GeneradorSlaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_sla_en_riesgo_se_crea_si_fecha_limite_dentro_de_umbral(): void
    {
        $ahora = Carbon::create(2026, 4, 18, 10, 0, 0);
        Carbon::setTestNow($ahora);

        $proyecto = $this->crearProyectoCx();
        $gestor = $this->crearGestor($proyecto);
        $casoId = $this->crearCasoEn($proyecto);

        // Compromiso CX con SLA a 3 horas — dentro de umbral de 4h
        $compId = $this->crearCompromisoCx($proyecto, $casoId, (int) $gestor->id, $ahora->copy()->addHours(3));

        // Otro compromiso CX con SLA a 10 horas — fuera de umbral
        $lejanoId = $this->crearCompromisoCx($proyecto, $casoId, (int) $gestor->id, $ahora->copy()->addHours(10));

        app(GeneradorNotificaciones::class)->ejecutar(umbralDias: 0, umbralHorasSla: 4);

        $this->assertSame(1, (int) DB::table('notificaciones')
            ->where('tipo', 'sla_en_riesgo')->count());
        $this->assertDatabaseHas('notificaciones', [
            'proyecto_id' => $proyecto->id,
            'destinatario_usuario_id' => $gestor->id,
            'tipo' => 'sla_en_riesgo',
            'entidad_id' => $compId,
        ]);
        $this->assertDatabaseMissing('notificaciones', [
            'tipo' => 'sla_en_riesgo',
            'entidad_id' => $lejanoId,
        ]);

        Carbon::setTestNow();
    }

    public function test_comando_acepta_horas_sla(): void
    {
        $ahora = Carbon::create(2026, 4, 18, 10, 0, 0);
        Carbon::setTestNow($ahora);

        $proyecto = $this->crearProyectoCx();
        $gestor = $this->crearGestor($proyecto);
        $casoId = $this->crearCasoEn($proyecto);
        $this->crearCompromisoCx($proyecto, $casoId, (int) $gestor->id, $ahora->copy()->addHours(2));

        Artisan::call('notificaciones:generar', ['--horas-sla' => 4]);
        $this->assertSame(1, (int) DB::table('notificaciones')->where('tipo', 'sla_en_riesgo')->count());

        Carbon::setTestNow();
    }

    public function test_sla_es_idempotente(): void
    {
        $ahora = Carbon::create(2026, 4, 18, 10, 0, 0);
        Carbon::setTestNow($ahora);

        $proyecto = $this->crearProyectoCx();
        $gestor = $this->crearGestor($proyecto);
        $casoId = $this->crearCasoEn($proyecto);
        $this->crearCompromisoCx($proyecto, $casoId, (int) $gestor->id, $ahora->copy()->addHours(2));

        $generador = app(GeneradorNotificaciones::class);
        $generador->ejecutar(umbralDias: 0, umbralHorasSla: 4);
        $generador->ejecutar(umbralDias: 0, umbralHorasSla: 4);

        $this->assertSame(1, (int) DB::table('notificaciones')->where('tipo', 'sla_en_riesgo')->count());

        Carbon::setTestNow();
    }

    public function test_scheduler_tiene_entradas_de_notificaciones(): void
    {
        $exitCode = Artisan::call('schedule:list');
        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('notificaciones:generar', $output);
        $this->assertStringContainsString('horas-sla', $output);
    }

    private function crearCompromisoCx(stdClass $proyecto, int $casoId, int $usuarioId, Carbon $fechaLimite): int
    {
        $compId = (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'gestion_origen_id' => null,
            'tipo_compromiso' => 'resolucion_ticket',
            'estado' => 'pendiente',
            'fecha_vencimiento' => $fechaLimite->toDateString(),
            'usuario_id' => $usuarioId,
        ]);

        DB::table('compromisos_resolucion_ticket')->insert([
            'compromiso_id' => $compId,
            'proyecto_id' => $proyecto->id,
            'nivel_escalamiento_id' => null,
            'accion_comprometida' => 'Resolver ticket de prueba',
            'fecha_limite_sla' => $fechaLimite->toDateTimeString(),
        ]);

        return $compId;
    }
}
