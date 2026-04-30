<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Gestiones;

use App\Models\User;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use App\Modules\Gestiones\Domain\Exceptions\CausaMoraRequerida;
use App\Modules\Gestiones\Domain\Exceptions\PromesaRequerida;
use App\Modules\Gestiones\Domain\ValueObjects\DatosPromesa;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use App\Modules\Promesas\Domain\ValueObjects\FechaPromesa;
use App\Modules\Promesas\Domain\ValueObjects\MontoPromesa;
use Database\Seeders\CatalogosSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RegistrarGestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);
    }

    public function test_registra_gestion_con_resultado_efectivo_sin_promesa(): void
    {
        $ctx = $this->contextoBase();
        Event::fake([GestionRegistrada::class]);

        $input = new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            productoId: $ctx['productoId'],
            clienteId: $ctx['clienteId'],
            contactoId: null,
            canalId: $this->catId('canales', 'TELEFONO'),
            tipoGestionId: $this->catId('tipos_gestion', 'LLAMADA_SALIENTE'),
            resultadoId: $this->catId('resultados', 'CONTACTO_TITULAR'),
            causaMoraId: null,
            motivoNoContactoId: null,
            usuarioId: $ctx['usuarioId'],
            notas: 'Cliente confirma recepción.',
            duracion: new DuracionSegundos(180),
            snapshot: null,
            datosPromesa: null,
            creadaEn: new DateTimeImmutable('2026-04-17 10:00:00'),
        );

        $output = $this->useCase()->execute($input);

        $this->assertGreaterThan(0, $output->id);
        $this->assertDatabaseHas('gestiones', [
            'id' => $output->id,
            'producto_id' => $ctx['productoId'],
            'resultado_id' => $this->catId('resultados', 'CONTACTO_TITULAR'),
        ]);
        Event::assertDispatched(GestionRegistrada::class);
    }

    public function test_registra_gestion_con_promesa_crea_promesa_y_actualiza_producto(): void
    {
        $ctx = $this->contextoBase();

        $hoy = new DateTimeImmutable('2026-04-17');
        $datosPromesa = new DatosPromesa(
            monto: new MontoPromesa('1500.00'),
            fecha: FechaPromesa::futura(new DateTimeImmutable('2026-04-25'), $hoy),
        );

        $input = new RegistrarGestionInput(
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
            notas: 'Promete pagar el viernes.',
            duracion: new DuracionSegundos(240),
            snapshot: null,
            datosPromesa: $datosPromesa,
            creadaEn: $hoy->setTime(11, 30),
        );

        $output = $this->useCase()->execute($input);

        $this->assertDatabaseHas('gestiones', [
            'id' => $output->id,
            'resultado_id' => $this->catId('resultados', 'PROMESA_PAGO'),
            'causa_mora_id' => $this->catId('causas_mora', 'DESEMPLEO'),
        ]);

        $this->assertDatabaseHas('promesas', [
            'producto_id' => $ctx['productoId'],
            'gestion_origen_id' => $output->id,
            'monto_promesa' => '1500.00',
            'fecha_promesa' => '2026-04-25',
            'estado' => 'pendiente',
        ]);

        $this->assertDatabaseHas('productos', [
            'id' => $ctx['productoId'],
            'tiene_promesa_vigente' => 1,
            'resultado_ultima_gestion_id' => $this->catId('resultados', 'PROMESA_PAGO'),
            'usuario_ultima_gestion_id' => $ctx['usuarioId'],
        ]);
    }

    public function test_registra_gestion_sin_promesa_actualiza_desnormalizados_pero_no_crea_promesa(): void
    {
        $ctx = $this->contextoBase();
        $resultadoId = $this->catId('resultados', 'CONTACTO_TITULAR');

        $input = new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            productoId: $ctx['productoId'],
            clienteId: $ctx['clienteId'],
            contactoId: null,
            canalId: $this->catId('canales', 'TELEFONO'),
            tipoGestionId: $this->catId('tipos_gestion', 'LLAMADA_SALIENTE'),
            resultadoId: $resultadoId,
            causaMoraId: null,
            motivoNoContactoId: null,
            usuarioId: $ctx['usuarioId'],
            notas: null,
            duracion: new DuracionSegundos(90),
            snapshot: null,
            datosPromesa: null,
            creadaEn: new DateTimeImmutable('2026-04-17 09:15:00'),
        );

        $this->useCase()->execute($input);

        $this->assertDatabaseCount('promesas', 0);
        $this->assertDatabaseHas('productos', [
            'id' => $ctx['productoId'],
            'tiene_promesa_vigente' => 0,
            'resultado_ultima_gestion_id' => $resultadoId,
            'usuario_ultima_gestion_id' => $ctx['usuarioId'],
        ]);
    }

    public function test_throws_cuando_resultado_requiere_promesa_y_no_se_provee(): void
    {
        $ctx = $this->contextoBase();

        $input = new RegistrarGestionInput(
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
            duracion: null,
            snapshot: null,
            datosPromesa: null,
            creadaEn: new DateTimeImmutable('2026-04-17 10:00:00'),
        );

        $this->expectException(PromesaRequerida::class);
        $this->useCase()->execute($input);
    }

    public function test_throws_cuando_resultado_requiere_causa_mora_y_no_se_provee(): void
    {
        $ctx = $this->contextoBase();

        $input = new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            productoId: $ctx['productoId'],
            clienteId: $ctx['clienteId'],
            contactoId: null,
            canalId: $this->catId('canales', 'TELEFONO'),
            tipoGestionId: $this->catId('tipos_gestion', 'LLAMADA_SALIENTE'),
            resultadoId: $this->catId('resultados', 'NEGOCIACION'),
            causaMoraId: null,
            motivoNoContactoId: null,
            usuarioId: $ctx['usuarioId'],
            notas: null,
            duracion: null,
            snapshot: null,
            datosPromesa: null,
            creadaEn: new DateTimeImmutable('2026-04-17 10:00:00'),
        );

        $this->expectException(CausaMoraRequerida::class);
        $this->useCase()->execute($input);
    }

    private function useCase(): RegistrarGestion
    {
        return $this->app->make(RegistrarGestion::class);
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
            'public_id' => (string) Str::ulid(),
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $this->catId('tipos_identificacion', 'CED'),
            'identificacion' => '0102030405',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
        ]);

        $productoId = (int) DB::table('productos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'cliente_id' => $clienteId,
            'numero_prestamo' => 'P-TEST-001',
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
