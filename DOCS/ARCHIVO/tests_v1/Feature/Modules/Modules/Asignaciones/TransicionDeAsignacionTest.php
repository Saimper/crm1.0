<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Asignaciones;

use App\Models\User;
use App\Modules\Asignaciones\Application\UseCases\CerrarAsignacion;
use App\Modules\Asignaciones\Domain\Exceptions\TransicionAsignacionInvalida;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use Database\Seeders\CatalogosSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TransicionDeAsignacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);
    }

    public function test_registrar_gestion_transiciona_asignacion_pendiente_a_en_trabajo(): void
    {
        [$ctx, $asignacionId] = $this->ctxConAsignacion();

        $this->app->make(RegistrarGestion::class)->execute($this->gestionInput($ctx));

        $this->assertDatabaseHas('asignaciones', [
            'id'     => $asignacionId,
            'estado' => 'en_trabajo',
        ]);
    }

    public function test_cerrar_asignacion_pendiente_la_marca_cerrada(): void
    {
        [, $asignacionId] = $this->ctxConAsignacion();

        $this->app->make(CerrarAsignacion::class)->execute($asignacionId);

        $row = DB::table('asignaciones')->where('id', $asignacionId)->first();
        $this->assertSame('cerrada', $row->estado);
        $this->assertNotNull($row->cerrada_en);
    }

    public function test_no_permite_cerrar_una_asignacion_ya_cerrada(): void
    {
        [, $asignacionId] = $this->ctxConAsignacion();
        $useCase = $this->app->make(CerrarAsignacion::class);
        $useCase->execute($asignacionId);

        $this->expectException(TransicionAsignacionInvalida::class);
        $useCase->execute($asignacionId);
    }

    /** @return array{0: array{clienteId:int,productoId:int,usuarioId:int}, 1:int} */
    private function ctxConAsignacion(): array
    {
        $ctx = $this->contextoBase();

        $campanaId = (int) DB::table('campanas')->insertGetId([
            'public_id'    => (string) Str::ulid(),
            'codigo'       => 'CAMP-TEST',
            'nombre'       => 'Campaña de prueba',
            'fecha_inicio' => '2026-04-01',
            'estado'       => 'activa',
        ]);

        $asignacionId = (int) DB::table('asignaciones')->insertGetId([
            'public_id'        => (string) Str::ulid(),
            'campana_id'       => $campanaId,
            'producto_id'      => $ctx['productoId'],
            'usuario_id'       => $ctx['usuarioId'],
            'fecha_asignacion' => '2026-04-17',
            'prioridad'        => 100,
            'estado'           => 'pendiente',
        ]);

        return [$ctx, $asignacionId];
    }

    /** @param array{clienteId:int,productoId:int,usuarioId:int} $ctx */
    private function gestionInput(array $ctx): RegistrarGestionInput
    {
        return new RegistrarGestionInput(
            publicId:           (string) Str::ulid(),
            productoId:         $ctx['productoId'],
            clienteId:          $ctx['clienteId'],
            contactoId:         null,
            canalId:            $this->catId('canales', 'TELEFONO'),
            tipoGestionId:      $this->catId('tipos_gestion', 'LLAMADA_SALIENTE'),
            resultadoId:        $this->catId('resultados', 'CONTACTO_TITULAR'),
            causaMoraId:        null,
            motivoNoContactoId: null,
            usuarioId:          $ctx['usuarioId'],
            notas:              null,
            duracion:           new DuracionSegundos(60),
            snapshot:           null,
            datosPromesa:       null,
            creadaEn:           new DateTimeImmutable('2026-04-17 10:00:00'),
        );
    }

    private function catId(string $tabla, string $codigo): int
    {
        return (int) DB::table($tabla)->where('codigo', $codigo)->value('id');
    }

    /** @return array{clienteId:int, productoId:int, usuarioId:int} */
    private function contextoBase(): array
    {
        $usuario = User::factory()->create();

        $clienteId = (int) DB::table('clientes')->insertGetId([
            'public_id'              => (string) Str::ulid(),
            'tipo_persona'           => 'fisica',
            'tipo_identificacion_id' => $this->catId('tipos_identificacion', 'CED'),
            'identificacion'         => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres'                => 'Test',
            'apellidos'              => 'User',
        ]);

        $productoId = (int) DB::table('productos')->insertGetId([
            'public_id'          => (string) Str::ulid(),
            'cliente_id'         => $clienteId,
            'numero_prestamo'    => 'P-TRANS-'.Str::random(6),
            'cartera_id'         => $this->catId('carteras', 'CONSUMO'),
            'estado_producto_id' => $this->catId('estados_producto', 'MORA'),
            'tramo_mora_id'      => $this->catId('tramos_mora', 'TRAMO_31_60'),
            'monto_original'     => 5000,
            'saldo_capital'      => 4000,
            'saldo_total'        => 4500,
            'cuota_mensual'      => 250,
            'dias_mora'          => 45,
            'cuotas_totales'     => 24,
            'cuotas_pagadas'     => 6,
            'moneda'             => 'USD',
            'fecha_desembolso'   => '2026-01-15',
            'fecha_vencimiento'  => '2028-01-15',
        ]);

        return [
            'clienteId'  => $clienteId,
            'productoId' => $productoId,
            'usuarioId'  => $usuario->id,
        ];
    }
}
