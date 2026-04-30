<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Promesas;

use App\Models\User;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\ValueObjects\DatosPromesa;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use App\Modules\Promesas\Application\DTOs\ResolverPromesaInput;
use App\Modules\Promesas\Application\UseCases\CancelarPromesa;
use App\Modules\Promesas\Application\UseCases\MarcarPromesaCumplida;
use App\Modules\Promesas\Application\UseCases\MarcarPromesaRota;
use App\Modules\Promesas\Domain\Exceptions\TransicionPromesaInvalida;
use App\Modules\Promesas\Domain\ValueObjects\FechaPromesa;
use App\Modules\Promesas\Domain\ValueObjects\MontoPromesa;
use Database\Seeders\CatalogosSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CicloDeVidaPromesaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);
    }

    public function test_marcar_cumplida_apaga_bandera_cuando_no_quedan_vigentes(): void
    {
        [$ctx, $promesaId] = $this->registrarPromesa(monto: '1000.00', fechaFutura: '2026-04-25');

        $this->useCase(MarcarPromesaCumplida::class)->execute(
            new ResolverPromesaInput($promesaId, new DateTimeImmutable('2026-04-26 09:00:00'))
        );

        $this->assertDatabaseHas('promesas', [
            'id' => $promesaId,
            'estado' => 'cumplida',
        ]);
        $this->assertDatabaseHas('productos', [
            'id' => $ctx['productoId'],
            'tiene_promesa_vigente' => 0,
        ]);
    }

    public function test_marcar_rota_apaga_bandera(): void
    {
        [$ctx, $promesaId] = $this->registrarPromesa(monto: '750.00', fechaFutura: '2026-05-01');

        $this->useCase(MarcarPromesaRota::class)->execute(
            new ResolverPromesaInput($promesaId, new DateTimeImmutable('2026-05-02 10:00:00'))
        );

        $this->assertDatabaseHas('promesas', [
            'id' => $promesaId,
            'estado' => 'rota',
        ]);
        $this->assertDatabaseHas('productos', [
            'id' => $ctx['productoId'],
            'tiene_promesa_vigente' => 0,
        ]);
    }

    public function test_cancelar_apaga_bandera(): void
    {
        [$ctx, $promesaId] = $this->registrarPromesa(monto: '300.00', fechaFutura: '2026-04-30');

        $this->useCase(CancelarPromesa::class)->execute(
            new ResolverPromesaInput($promesaId, new DateTimeImmutable('2026-04-18 12:00:00'))
        );

        $this->assertDatabaseHas('promesas', [
            'id' => $promesaId,
            'estado' => 'cancelada',
        ]);
        $this->assertDatabaseHas('productos', [
            'id' => $ctx['productoId'],
            'tiene_promesa_vigente' => 0,
        ]);
    }

    public function test_con_dos_promesas_vigentes_cumplir_una_mantiene_bandera_encendida(): void
    {
        $ctx = $this->contextoBase();
        $primera = $this->registrarGestionConPromesa($ctx, '500.00', '2026-04-25', 'P-MULTI-1');
        $segunda = $this->registrarGestionConPromesa($ctx, '800.00', '2026-05-10', 'P-MULTI-1');

        $this->useCase(MarcarPromesaCumplida::class)->execute(
            new ResolverPromesaInput($primera, new DateTimeImmutable('2026-04-26 09:00:00'))
        );

        $this->assertDatabaseHas('productos', [
            'id' => $ctx['productoId'],
            'tiene_promesa_vigente' => 1,
        ]);
        $this->assertDatabaseHas('promesas', ['id' => $segunda, 'estado' => 'pendiente']);
    }

    public function test_throws_al_marcar_cumplida_una_promesa_ya_cumplida(): void
    {
        [, $promesaId] = $this->registrarPromesa(monto: '400.00', fechaFutura: '2026-04-25');

        $this->useCase(MarcarPromesaCumplida::class)->execute(
            new ResolverPromesaInput($promesaId, new DateTimeImmutable('2026-04-26'))
        );

        $this->expectException(TransicionPromesaInvalida::class);
        $this->useCase(MarcarPromesaCumplida::class)->execute(
            new ResolverPromesaInput($promesaId, new DateTimeImmutable('2026-04-27'))
        );
    }

    /** @return array{0: array{clienteId:int,productoId:int,usuarioId:int}, 1: int} */
    private function registrarPromesa(string $monto, string $fechaFutura, string $nroPrestamo = 'P-CICLO-1'): array
    {
        $ctx = $this->contextoBase($nroPrestamo);
        $promesaId = $this->registrarGestionConPromesa($ctx, $monto, $fechaFutura, $nroPrestamo);

        return [$ctx, $promesaId];
    }

    /** @param array{clienteId:int,productoId:int,usuarioId:int} $ctx */
    private function registrarGestionConPromesa(array $ctx, string $monto, string $fechaFutura, string $_nroPrestamo): int
    {
        $hoy = new DateTimeImmutable('2026-04-17');
        $datos = new DatosPromesa(
            monto: new MontoPromesa($monto),
            fecha: FechaPromesa::futura(new DateTimeImmutable($fechaFutura), $hoy),
        );

        $output = $this->useCase(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            productoId: $ctx['productoId'],
            clienteId: $ctx['clienteId'],
            contactoId: null,
            canalId: $this->catId('canales', 'TELEFONO'),
            tipoGestionId: $this->catId('tipos_gestion', 'LLAMADA_SALIENTE'),
            resultadoId: $this->catId('resultados', 'PROMESA_PAGO'),
            causaMoraId: $this->catId('causas_mora', 'DESEMPLEO'),
            motivoNoContactoId: null,
            usuarioId: $ctx['usuarioId'],
            notas: null,
            duracion: new DuracionSegundos(180),
            snapshot: null,
            datosPromesa: $datos,
            creadaEn: $hoy->setTime(10, 0),
        ));

        return (int) DB::table('promesas')->where('gestion_origen_id', $output->id)->value('id');
    }

    private function useCase(string $clase): object
    {
        return $this->app->make($clase);
    }

    private function catId(string $tabla, string $codigo): int
    {
        return (int) DB::table($tabla)->where('codigo', $codigo)->value('id');
    }

    /** @return array{clienteId:int, productoId:int, usuarioId:int} */
    private function contextoBase(string $nroPrestamo = 'P-CICLO-1'): array
    {
        $usuario = User::factory()->create();

        $clienteId = (int) DB::table('clientes')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $this->catId('tipos_identificacion', 'CED'),
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
        ]);

        $productoId = (int) DB::table('productos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'cliente_id' => $clienteId,
            'numero_prestamo' => $nroPrestamo,
            'cartera_id' => $this->catId('carteras', 'CONSUMO'),
            'estado_producto_id' => $this->catId('estados_producto', 'MORA'),
            'tramo_mora_id' => $this->catId('tramos_mora', 'TRAMO_31_60'),
            'monto_original' => 5000,
            'saldo_capital' => 4200,
            'saldo_total' => 4500,
            'cuota_mensual' => 250,
            'dias_mora' => 45,
            'cuotas_totales' => 24,
            'cuotas_pagadas' => 6,
            'moneda' => 'USD',
            'fecha_desembolso' => '2026-01-15',
            'fecha_vencimiento' => '2028-01-15',
        ]);

        return [
            'clienteId' => $clienteId,
            'productoId' => $productoId,
            'usuarioId' => $usuario->id,
        ];
    }
}
