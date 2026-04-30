<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Compromisos\Domain;

use App\Modules\Casos\Domain\ValueObjects\TipoCaso;
use App\Modules\Compromisos\Domain\Entities\Compromiso;
use App\Modules\Compromisos\Domain\Exceptions\TransicionCompromisoInvalida;
use App\Modules\Compromisos\Domain\ValueObjects\EstadoCompromiso;
use App\Modules\Compromisos\Domain\ValueObjects\TipoCompromiso;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CompromisoTest extends TestCase
{
    public function test_tipo_compromiso_derivable_de_tipo_caso(): void
    {
        $this->assertSame(TipoCompromiso::PROMESA_PAGO, TipoCompromiso::desdeTipoCaso(TipoCaso::COBRANZA));
        $this->assertSame(TipoCompromiso::RESOLUCION_TICKET, TipoCompromiso::desdeTipoCaso(TipoCaso::TICKET_CX));
        $this->assertSame(TipoCompromiso::CIERRE_VENTA, TipoCompromiso::desdeTipoCaso(TipoCaso::LEAD_VENTA));
        $this->assertSame(TipoCompromiso::ACCION_SERVICIO, TipoCompromiso::desdeTipoCaso(TipoCaso::SERVICIO));
    }

    public function test_compromiso_nace_pendiente(): void
    {
        $c = $this->crear();

        $this->assertSame(EstadoCompromiso::PENDIENTE, $c->estado);
        $this->assertNull($c->fechaResolucion);
        $this->assertFalse($c->estaResuelto());
    }

    public function test_marcar_cumplido_transiciona_y_fija_fecha_resolucion(): void
    {
        $fechaRes = new DateTimeImmutable('2026-04-25');
        $cumplido = $this->crear()->marcarCumplido($fechaRes);

        $this->assertSame(EstadoCompromiso::CUMPLIDO, $cumplido->estado);
        $this->assertSame($fechaRes, $cumplido->fechaResolucion);
    }

    public function test_no_se_puede_marcar_cumplido_un_compromiso_ya_roto(): void
    {
        $roto = $this->crear()->marcarRoto(new DateTimeImmutable('2026-04-25'));

        $this->expectException(TransicionCompromisoInvalida::class);
        $roto->marcarCumplido(new DateTimeImmutable('2026-04-26'));
    }

    public function test_no_se_puede_cancelar_un_compromiso_cumplido(): void
    {
        $cumplido = $this->crear()->marcarCumplido(new DateTimeImmutable('2026-04-25'));

        $this->expectException(TransicionCompromisoInvalida::class);
        $cumplido->cancelar(new DateTimeImmutable('2026-04-26'));
    }

    public function test_reconstituir_no_revalida_transiciones(): void
    {
        $c = Compromiso::reconstituir(
            id: 100,
            publicId: '01HXCOMPRECON00000000000001',
            proyectoId: 1,
            casoId: 2,
            gestionOrigenId: 3,
            usuarioId: 4,
            tipo: TipoCompromiso::PROMESA_PAGO,
            estado: EstadoCompromiso::CUMPLIDO,
            fechaVencimiento: new DateTimeImmutable('2026-04-20'),
            fechaResolucion: new DateTimeImmutable('2026-04-21'),
            creadaEn: new DateTimeImmutable('2026-04-17'),
        );

        $this->assertSame(100, $c->id);
        $this->assertTrue($c->estaResuelto());
    }

    private function crear(): Compromiso
    {
        return Compromiso::crear(
            publicId: '01HXCOMPCREADO000000000001',
            proyectoId: 1,
            casoId: 2,
            gestionOrigenId: 3,
            usuarioId: 4,
            tipo: TipoCompromiso::PROMESA_PAGO,
            fechaVencimiento: new DateTimeImmutable('2026-04-25'),
            creadaEn: new DateTimeImmutable('2026-04-17 10:00:00'),
        );
    }
}
