<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Compromisos;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Compromisos\Application\UseCases\CancelarCompromiso;
use App\Modules\Compromisos\Application\UseCases\MarcarCompromisoCumplido;
use App\Modules\Compromisos\Application\UseCases\MarcarCompromisoRoto;
use App\Modules\Compromisos\Domain\Exceptions\TransicionCompromisoInvalida;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ResolverCompromisoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_cumplir_compromiso_baja_bandera_del_caso(): void
    {
        [$casoId, $compromisoId] = $this->crearCasoConCompromisoVigente();

        // Bandera activa manualmente (simulando al listener de CompromisoCreado).
        DB::table('casos')->where('id', $casoId)->update(['tiene_compromiso_vigente' => true]);

        $this->app->make(MarcarCompromisoCumplido::class)->execute(
            new ResolverCompromisoInput($compromisoId, new DateTimeImmutable('2026-04-25 10:00:00'))
        );

        $this->assertDatabaseHas('compromisos', [
            'id' => $compromisoId,
            'estado' => 'cumplido',
        ]);
        $this->assertSame(0, (int) DB::table('casos')->where('id', $casoId)->value('tiene_compromiso_vigente'));
    }

    public function test_romper_compromiso_baja_bandera(): void
    {
        [$casoId, $compromisoId] = $this->crearCasoConCompromisoVigente();
        DB::table('casos')->where('id', $casoId)->update(['tiene_compromiso_vigente' => true]);

        $this->app->make(MarcarCompromisoRoto::class)->execute(
            new ResolverCompromisoInput($compromisoId, new DateTimeImmutable('2026-04-26'))
        );

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'roto']);
        $this->assertSame(0, (int) DB::table('casos')->where('id', $casoId)->value('tiene_compromiso_vigente'));
    }

    public function test_cancelar_compromiso_baja_bandera(): void
    {
        [$casoId, $compromisoId] = $this->crearCasoConCompromisoVigente();
        DB::table('casos')->where('id', $casoId)->update(['tiene_compromiso_vigente' => true]);

        $this->app->make(CancelarCompromiso::class)->execute(
            new ResolverCompromisoInput($compromisoId, new DateTimeImmutable('2026-04-26'))
        );

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'cancelado']);
        $this->assertSame(0, (int) DB::table('casos')->where('id', $casoId)->value('tiene_compromiso_vigente'));
    }

    public function test_con_dos_compromisos_vigentes_cumplir_uno_mantiene_bandera(): void
    {
        [$casoId, $compromisoA] = $this->crearCasoConCompromisoVigente();
        $compromisoB = $this->crearSegundoCompromisoVigente($casoId);
        DB::table('casos')->where('id', $casoId)->update(['tiene_compromiso_vigente' => true]);

        $this->app->make(MarcarCompromisoCumplido::class)->execute(
            new ResolverCompromisoInput($compromisoA, new DateTimeImmutable('2026-04-25'))
        );

        $this->assertSame(1, (int) DB::table('casos')->where('id', $casoId)->value('tiene_compromiso_vigente'));
        $this->assertDatabaseHas('compromisos', ['id' => $compromisoB, 'estado' => 'pendiente']);
    }

    public function test_rechaza_cumplir_dos_veces(): void
    {
        [, $compromisoId] = $this->crearCasoConCompromisoVigente();
        $useCase = $this->app->make(MarcarCompromisoCumplido::class);

        $useCase->execute(new ResolverCompromisoInput($compromisoId, new DateTimeImmutable('2026-04-25')));

        $this->expectException(TransicionCompromisoInvalida::class);
        $useCase->execute(new ResolverCompromisoInput($compromisoId, new DateTimeImmutable('2026-04-26')));
    }

    /** @return array{0:int,1:int}  [casoId, compromisoId] */
    private function crearCasoConCompromisoVigente(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'CONSUMO')->value('id');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estadoAbiertoId = (int) DB::table('estados_caso')->where('codigo', 'ABIERTO')->value('id');

        $usuarioId = (int) DB::table('users')->insertGetId([
            'name' => 'Gestor Test', 'email' => 'gestor.test.'.Str::random(6).'@crm.local',
            'password' => bcrypt('x'), 'activo' => true,
        ]);

        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Test', 'apellidos' => 'User',
        ]);

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'cartera_id' => $carteraId, 'persona_id' => $personaId,
            'tipo_caso' => 'cobranza', 'estado_caso_id' => $estadoAbiertoId,
            'fecha_ingreso' => '2026-04-17', 'prioridad' => 100,
        ]);

        $compromisoId = (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'caso_id' => $casoId, 'usuario_id' => $usuarioId,
            'tipo_compromiso' => 'promesa_pago', 'estado' => 'pendiente',
            'fecha_vencimiento' => '2026-04-25',
        ]);

        return [$casoId, $compromisoId];
    }

    private function crearSegundoCompromisoVigente(int $casoId): int
    {
        $caso = DB::table('casos')->find($casoId);

        return (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $caso->proyecto_id,
            'caso_id' => $casoId, 'usuario_id' => DB::table('users')->value('id'),
            'tipo_compromiso' => 'promesa_pago', 'estado' => 'pendiente',
            'fecha_vencimiento' => '2026-05-05',
        ]);
    }
}
