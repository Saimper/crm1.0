<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Gestiones\Domain;

use App\Modules\Gestiones\Domain\Entities\Gestion;
use App\Modules\Gestiones\Domain\Exceptions\CausaRequerida;
use App\Modules\Gestiones\Domain\ValueObjects\BanderasResultado;
use App\Modules\Gestiones\Domain\ValueObjects\DuracionSegundos;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GestionTest extends TestCase
{
    public function test_registra_gestion_cuando_no_hay_banderas_activas(): void
    {
        $g = $this->gestion(new BanderasResultado(false, false, false), null);

        $this->assertNull($g->id);
        $this->assertSame(10, $g->proyectoId);
        $this->assertNull($g->causaId);
    }

    public function test_throws_cuando_resultado_requiere_causa_y_no_se_provee(): void
    {
        $this->expectException(CausaRequerida::class);
        $this->gestion(new BanderasResultado(true, false, true), null);
    }

    public function test_acepta_cuando_requiere_causa_y_se_provee(): void
    {
        $g = $this->gestion(new BanderasResultado(true, false, true), causaId: 5);
        $this->assertSame(5, $g->causaId);
    }

    public function test_con_id_persiste_identificadores(): void
    {
        $g = $this->gestion(new BanderasResultado(false, false, false), null);
        $persistida = $g->conId(777);

        $this->assertSame(777, $persistida->id);
        $this->assertSame($g->publicId, $persistida->publicId);
        $this->assertSame($g->creadaEn, $persistida->creadaEn);
    }

    public function test_banderas_con_requiere_compromiso_no_obliga_datos_en_el_nucleo(): void
    {
        // El núcleo solo valida requiere_causa. requiere_compromiso es responsabilidad
        // de la especialización (cobranza/cx/etc.) en su listener.
        $g = $this->gestion(new BanderasResultado(true, true, false), null);
        $this->assertTrue($g->banderas->requiereCompromiso);
        $this->assertNull($g->causaId);
    }

    private function gestion(BanderasResultado $banderas, ?int $causaId): Gestion
    {
        return Gestion::registrar(
            publicId: '01HXGESTION0000000000000001',
            proyectoId: 10,
            casoId: 42,
            personaId: 7,
            contactoId: 11,
            canalId: 1,
            tipoGestionId: 1,
            resultadoId: 1,
            motivoNoContactoId: null,
            causaId: $causaId,
            usuarioId: 9,
            notas: null,
            duracion: new DuracionSegundos(120),
            banderas: $banderas,
            creadaEn: new DateTimeImmutable('2026-04-17 10:30:00'),
        );
    }
}
