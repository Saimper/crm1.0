<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notificaciones;

use App\Models\User;
use App\Modules\Notificaciones\Application\Services\GeneradorNotificaciones;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class GeneradorSlaTest extends TestCase
{
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

        $proyectoId = $this->proyectoCxId();
        $gestor = $this->crearUsuarioConRol($proyectoId, 'GESTOR');

        $casoId = (int) DB::table('casos')
            ->where('proyecto_id', $proyectoId)
            ->where('tipo_caso', 'ticket_cx')
            ->value('id');

        // Compromiso CX con SLA a 3 horas — dentro de umbral de 4h
        $compId = $this->crearCompromisoCx($proyectoId, $casoId, $gestor->id, $ahora->copy()->addHours(3));

        // Otro compromiso CX con SLA a 10 horas — fuera de umbral
        $lejanoId = $this->crearCompromisoCx($proyectoId, $casoId, $gestor->id, $ahora->copy()->addHours(10));

        app(GeneradorNotificaciones::class)->ejecutar(umbralDias: 0, umbralHorasSla: 4);

        $this->assertSame(1, (int) DB::table('notificaciones')
            ->where('tipo', 'sla_en_riesgo')->count());
        $this->assertDatabaseHas('notificaciones', [
            'proyecto_id' => $proyectoId,
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

        $proyectoId = $this->proyectoCxId();
        $gestor = $this->crearUsuarioConRol($proyectoId, 'GESTOR');
        $casoId = (int) DB::table('casos')->where('proyecto_id', $proyectoId)->where('tipo_caso', 'ticket_cx')->value('id');
        $this->crearCompromisoCx($proyectoId, $casoId, $gestor->id, $ahora->copy()->addHours(2));

        Artisan::call('notificaciones:generar', ['--horas-sla' => 4]);
        $this->assertSame(1, (int) DB::table('notificaciones')->where('tipo', 'sla_en_riesgo')->count());

        Carbon::setTestNow();
    }

    public function test_sla_es_idempotente(): void
    {
        $ahora = Carbon::create(2026, 4, 18, 10, 0, 0);
        Carbon::setTestNow($ahora);

        $proyectoId = $this->proyectoCxId();
        $gestor = $this->crearUsuarioConRol($proyectoId, 'GESTOR');
        $casoId = (int) DB::table('casos')->where('proyecto_id', $proyectoId)->where('tipo_caso', 'ticket_cx')->value('id');
        $this->crearCompromisoCx($proyectoId, $casoId, $gestor->id, $ahora->copy()->addHours(2));

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

    private function proyectoCxId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
    }

    private function crearCompromisoCx(int $proyectoId, int $casoId, int $usuarioId, Carbon $fechaLimite): int
    {
        $compId = (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'caso_id' => $casoId,
            'gestion_origen_id' => null,
            'tipo_compromiso' => 'resolucion_ticket',
            'estado' => 'pendiente',
            'fecha_vencimiento' => $fechaLimite->toDateString(),
            'usuario_id' => $usuarioId,
        ]);

        DB::table('compromisos_resolucion_ticket')->insert([
            'compromiso_id' => $compId,
            'proyecto_id' => $proyectoId,
            'nivel_escalamiento_id' => null,
            'accion_comprometida' => 'Resolver ticket de prueba',
            'fecha_limite_sla' => $fechaLimite->toDateTimeString(),
        ]);

        return $compId;
    }

    private function crearUsuarioConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
