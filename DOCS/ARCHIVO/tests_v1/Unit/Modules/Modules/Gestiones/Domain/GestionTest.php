<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Gestiones\Domain;

use App\Modules\Gestiones\Domain\Entities\Gestion;
use App\Modules\Gestiones\Domain\Exceptions\CausaMoraRequerida;
use App\Modules\Gestiones\Domain\Exceptions\PromesaRequerida;
use App\Modules\Gestiones\Domain\ValueObjects\BanderasResultado;
use App\Modules\Gestiones\Domain\ValueObjects\DatosPromesa;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use App\Modules\Gestiones\Domain\ValueObjects\SnapshotGestion;
use App\Modules\Productos\Domain\ValueObjects\DiasMora;
use App\Modules\Promesas\Domain\ValueObjects\FechaPromesa;
use App\Modules\Promesas\Domain\ValueObjects\MontoPromesa;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GestionTest extends TestCase
{
    public function test_registra_gestion_cuando_ningun_resultado_lo_requiere(): void
    {
        $gestion = $this->gestion(
            banderas: new BanderasResultado(false, false, false),
        );

        $this->assertNull($gestion->id);
        $this->assertSame(42, $gestion->productoId);
    }

    public function test_throws_cuando_resultado_requiere_promesa_y_no_se_provee(): void
    {
        $this->expectException(PromesaRequerida::class);

        $this->gestion(
            banderas: new BanderasResultado(true, true, false),
            datosPromesa: null,
        );
    }

    public function test_registra_gestion_cuando_resultado_requiere_promesa_y_se_provee(): void
    {
        $hoy = new DateTimeImmutable('2026-04-17');
        $datos = new DatosPromesa(
            monto: new MontoPromesa('1500.00'),
            fecha: FechaPromesa::futura(new DateTimeImmutable('2026-04-20'), $hoy),
        );

        $gestion = $this->gestion(
            banderas: new BanderasResultado(true, true, true),
            datosPromesa: $datos,
            causaMoraId: 3,
        );

        $this->assertSame('1500.00', $gestion->datosPromesa->monto->asDecimal());
    }

    public function test_throws_cuando_resultado_requiere_causa_mora_y_no_se_provee(): void
    {
        $this->expectException(CausaMoraRequerida::class);

        $this->gestion(
            banderas: new BanderasResultado(true, false, true),
            causaMoraId: null,
        );
    }

    public function test_con_id_produce_gestion_con_id_asignado_y_mismos_datos(): void
    {
        $gestion = $this->gestion();
        $persistida = $gestion->conId(777);

        $this->assertSame(777, $persistida->id);
        $this->assertSame($gestion->publicId, $persistida->publicId);
        $this->assertSame($gestion->productoId, $persistida->productoId);
        $this->assertSame($gestion->creadaEn, $persistida->creadaEn);
    }

    public function test_snapshot_se_acepta_con_valores_validos(): void
    {
        $gestion = $this->gestion(
            snapshot: new SnapshotGestion('8250.50', new DiasMora(45)),
        );

        $this->assertSame('8250.50', $gestion->snapshot->saldo);
        $this->assertSame(45, $gestion->snapshot->diasMora->valor);
    }

    private function gestion(
        ?BanderasResultado $banderas = null,
        ?DatosPromesa $datosPromesa = null,
        ?int $causaMoraId = null,
        ?SnapshotGestion $snapshot = null,
    ): Gestion {
        return Gestion::registrar(
            publicId: '01HXTESTULIDSAMPLE0000000',
            productoId: 42,
            clienteId: 7,
            contactoId: 11,
            canalId: 1,
            tipoGestionId: 1,
            resultadoId: 1,
            causaMoraId: $causaMoraId,
            motivoNoContactoId: null,
            usuarioId: 9,
            notas: null,
            duracion: new DuracionSegundos(120),
            snapshot: $snapshot,
            datosPromesa: $datosPromesa,
            banderas: $banderas ?? new BanderasResultado(false, false, false),
            creadaEn: new DateTimeImmutable('2026-04-17 10:30:00'),
        );
    }
}
