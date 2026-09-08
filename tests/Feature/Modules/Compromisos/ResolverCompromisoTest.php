<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Compromisos;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Compromisos\Application\UseCases\CancelarCompromiso;
use App\Modules\Compromisos\Application\UseCases\MarcarCompromisoCumplido;
use App\Modules\Compromisos\Application\UseCases\MarcarCompromisoRoto;
use App\Modules\Compromisos\Domain\Exceptions\TransicionCompromisoInvalida;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ResolverCompromisoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
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
        $proyecto = $this->crearProyectoCobranza();
        $usuario = $this->crearGestor($proyecto);

        $casoId = $this->crearCasoEn($proyecto, ['fecha_ingreso' => '2026-04-17']);

        $compromisoId = (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId, 'usuario_id' => $usuario->id,
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
            'caso_id' => $casoId,
            'usuario_id' => DB::table('compromisos')->where('caso_id', $casoId)->value('usuario_id'),
            'tipo_compromiso' => 'promesa_pago', 'estado' => 'pendiente',
            'fecha_vencimiento' => '2026-05-05',
        ]);
    }
}
