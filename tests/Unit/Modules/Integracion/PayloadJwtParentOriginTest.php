<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Integracion;

use App\Modules\Integracion\Domain\ValueObjects\PayloadJwt;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PayloadJwtParentOriginTest extends TestCase
{
    private function claims(array $extra = []): object
    {
        $ahora = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

        return (object) array_merge([
            'jti' => '00000000-0000-4000-8000-000000000000',
            'sub' => 'agente@acme.com',
            'exp' => $ahora->getTimestamp() + 60,
            'mandante_id' => 7,
        ], $extra);
    }

    private function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    }

    public function test_parsea_parent_origin_valido(): void
    {
        $payload = PayloadJwt::desdeClaims(
            $this->claims(['parent_origin' => 'https://app.viciconnect.net']),
            $this->ahora(),
        );

        $this->assertSame('https://app.viciconnect.net', $payload->parentOrigin);
    }

    public function test_rechaza_parent_origin_con_path_o_query(): void
    {
        $payload = PayloadJwt::desdeClaims(
            $this->claims(['parent_origin' => 'https://app.viciconnect.net/phish?x=1']),
            $this->ahora(),
        );

        $this->assertNull($payload->parentOrigin);
    }

    public function test_acepta_origin_con_puerto(): void
    {
        $payload = PayloadJwt::desdeClaims(
            $this->claims(['parent_origin' => 'http://localhost:5173']),
            $this->ahora(),
        );

        $this->assertSame('http://localhost:5173', $payload->parentOrigin);
    }

    public function test_parent_origin_ausente_es_null(): void
    {
        $payload = PayloadJwt::desdeClaims($this->claims(), $this->ahora());

        $this->assertNull($payload->parentOrigin);
    }
}
