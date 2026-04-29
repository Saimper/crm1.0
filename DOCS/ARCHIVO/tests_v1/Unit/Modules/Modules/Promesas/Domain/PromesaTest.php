<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Promesas\Domain;

use App\Modules\Promesas\Domain\Entities\Promesa;
use App\Modules\Promesas\Domain\Exceptions\TransicionPromesaInvalida;
use App\Modules\Promesas\Domain\ValueObjects\EstadoPromesa;
use App\Modules\Promesas\Domain\ValueObjects\FechaPromesa;
use App\Modules\Promesas\Domain\ValueObjects\MontoPromesa;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PromesaTest extends TestCase
{
    public function test_crear_promesa_arranca_en_pendiente(): void
    {
        $promesa = $this->crear();

        $this->assertSame(EstadoPromesa::PENDIENTE, $promesa->estado);
        $this->assertNull($promesa->fechaResolucion);
        $this->assertNull($promesa->id);
    }

    public function test_marcar_cumplida_cambia_estado_y_fija_fecha_resolucion(): void
    {
        $fechaRes = new DateTimeImmutable('2026-04-25 14:00:00');
        $cumplida = $this->crear()->marcarCumplida($fechaRes);

        $this->assertSame(EstadoPromesa::CUMPLIDA, $cumplida->estado);
        $this->assertSame($fechaRes, $cumplida->fechaResolucion);
    }

    public function test_throws_al_marcar_cumplida_una_promesa_rota(): void
    {
        $rota = $this->crear()->marcarRota(new DateTimeImmutable('2026-04-25'));

        $this->expectException(TransicionPromesaInvalida::class);
        $rota->marcarCumplida(new DateTimeImmutable('2026-04-26'));
    }

    public function test_throws_al_cancelar_una_promesa_cumplida(): void
    {
        $cumplida = $this->crear()->marcarCumplida(new DateTimeImmutable('2026-04-25'));

        $this->expectException(TransicionPromesaInvalida::class);
        $cumplida->cancelar(new DateTimeImmutable('2026-04-26'));
    }

    public function test_reconstituir_no_revalida_transiciones(): void
    {
        $hoy = new DateTimeImmutable('2026-04-17');

        $promesa = Promesa::reconstituir(
            id:              123,
            publicId:        '01HXRECONSTITUIDULIDSAMPLE',
            productoId:      5,
            gestionOrigenId: 8,
            usuarioId:       2,
            tipoPagoId:      null,
            monto:           new MontoPromesa('500.00'),
            fecha:           FechaPromesa::hidratar(new DateTimeImmutable('2026-04-20')),
            estado:          EstadoPromesa::CUMPLIDA,
            fechaResolucion: new DateTimeImmutable('2026-04-21'),
            creadaEn:        $hoy,
        );

        $this->assertSame(123, $promesa->id);
        $this->assertSame(EstadoPromesa::CUMPLIDA, $promesa->estado);
    }

    private function crear(): Promesa
    {
        $hoy = new DateTimeImmutable('2026-04-17');

        return Promesa::crear(
            publicId:        '01HXTESTPROMESAULIDSAMPLE',
            productoId:      42,
            gestionOrigenId: 99,
            usuarioId:       7,
            tipoPagoId:      null,
            monto:           new MontoPromesa('1500.00'),
            fecha:           FechaPromesa::futura(new DateTimeImmutable('2026-04-25'), $hoy),
            creadaEn:        $hoy->setTime(10, 30),
        );
    }
}
