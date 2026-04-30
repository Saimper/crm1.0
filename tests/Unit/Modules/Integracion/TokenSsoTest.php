<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Integracion;

use App\Modules\Integracion\Domain\Entities\TokenSso;
use App\Modules\Integracion\Domain\Events\TokenSsoEmitido;
use App\Modules\Integracion\Domain\Exceptions\TokenSsoExpiradoException;
use App\Modules\Integracion\Domain\Exceptions\TokenSsoYaConsumidoException;
use App\Modules\Integracion\Domain\ValueObjects\TokenClaroHash;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TokenSsoTest extends TestCase
{
    public function test_token_nuevo_no_esta_expirado(): void
    {
        $token = $this->tokenFuturo();
        $this->assertFalse($token->expirado());
    }

    public function test_token_expirado_retorna_true(): void
    {
        $token = TokenSso::reconstituir(
            publicId: 'pub-1',
            usuarioId: 1,
            tokenHash: 'hash',
            expiraEn: new DateTimeImmutable('-1 second'),
            consumidoEn: null,
        );
        $this->assertTrue($token->expirado());
    }

    public function test_consumir_token_valido_ok(): void
    {
        $token = $this->tokenFuturo();
        $token->consumir(new DateTimeImmutable('now'));

        $this->assertNotNull($token->consumidoEn());
    }

    public function test_consumir_token_expirado_lanza_excepcion(): void
    {
        $token = TokenSso::reconstituir(
            publicId: 'pub-2',
            usuarioId: 1,
            tokenHash: 'hash',
            expiraEn: new DateTimeImmutable('-1 second'),
            consumidoEn: null,
        );

        $this->expectException(TokenSsoExpiradoException::class);
        $token->consumir(new DateTimeImmutable('now'));
    }

    public function test_consumir_token_ya_consumido_lanza_excepcion(): void
    {
        $token = $this->tokenFuturo();
        $token->consumir(new DateTimeImmutable('now'));

        $this->expectException(TokenSsoYaConsumidoException::class);
        $token->consumir(new DateTimeImmutable('now'));
    }

    public function test_token_crear_emite_evento(): void
    {
        $hash = TokenClaroHash::generar('mi-token-claro');
        $token = TokenSso::crear(
            publicId: 'pub-3',
            usuarioId: 42,
            tokenClaroHash: $hash,
            expiraEn: new DateTimeImmutable('+5 minutes'),
            proyectoId: 10,
        );

        $eventos = $token->pullEventos();
        $this->assertCount(1, $eventos);
        $this->assertInstanceOf(TokenSsoEmitido::class, $eventos[0]);
        $this->assertSame(42, $eventos[0]->usuarioId);
    }

    public function test_token_claro_hash_hashea_con_sha256(): void
    {
        $claro = 'test_token_12345';
        $vo = TokenClaroHash::generar($claro);

        $this->assertSame(hash('sha256', $claro), $vo->hash);
        $this->assertSame($claro, $vo->claro);
    }

    private function tokenFuturo(): TokenSso
    {
        return TokenSso::reconstituir(
            publicId: 'pub-ok',
            usuarioId: 1,
            tokenHash: 'some-hash',
            expiraEn: new DateTimeImmutable('+5 minutes'),
            consumidoEn: null,
            proyectoId: null,
        );
    }
}
