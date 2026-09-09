<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Integracion;

use App\Modules\Integracion\Domain\ValueObjects\PayloadJwt;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PayloadJwtTest extends TestCase
{
    public function test_recorta_espacios_de_las_claves_de_ficha(): void
    {
        $payload = PayloadJwt::desdeClaims($this->claims([
            'identificacion' => '  8-123-456 ',
            'tipo_identificacion_codigo' => ' CED ',
            'numero_prestamo' => ' L-001 ',
        ]), new DateTimeImmutable);

        $this->assertSame('8-123-456', $payload->identificacion);
        $this->assertSame('CED', $payload->tipoIdentificacionCodigo);
        $this->assertSame('L-001', $payload->numeroPrestamo);
    }

    public function test_clave_en_blanco_equivale_a_clave_ausente(): void
    {
        $payload = PayloadJwt::desdeClaims($this->claims([
            'identificacion' => '   ',
            'tipo_identificacion_codigo' => '',
            'numero_prestamo' => "\t",
        ]), new DateTimeImmutable);

        $this->assertNull($payload->identificacion);
        $this->assertNull($payload->tipoIdentificacionCodigo);
        $this->assertNull($payload->numeroPrestamo);
    }

    public function test_sin_claves_de_ficha_quedan_nulas(): void
    {
        $payload = PayloadJwt::desdeClaims($this->claims(), new DateTimeImmutable);

        $this->assertNull($payload->identificacion);
        $this->assertNull($payload->tipoIdentificacionCodigo);
        $this->assertNull($payload->numeroPrestamo);
        $this->assertSame(2, $payload->proyectoId);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function claims(array $extra = []): object
    {
        return (object) array_merge([
            'jti' => 'jti-1',
            'sub' => 'Agente@Wrapper.io',
            'exp' => time() + 30,
            'mandante_id' => 1,
            'proyecto_id' => 2,
        ], $extra);
    }
}
