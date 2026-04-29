<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\Entities;

use App\Modules\Integracion\Domain\Events\TokenSsoConsumido;
use App\Modules\Integracion\Domain\Events\TokenSsoEmitido;
use App\Modules\Integracion\Domain\Exceptions\TokenSsoExpiradoException;
use App\Modules\Integracion\Domain\Exceptions\TokenSsoYaConsumidoException;
use App\Modules\Integracion\Domain\ValueObjects\TokenClaroHash;
use DateTimeImmutable;

final class TokenSso
{
    private ?DateTimeImmutable $consumidoEn = null;

    /** @var list<object> */
    private array $eventos = [];

    private function __construct(
        public readonly string $publicId,
        public readonly int $usuarioId,
        public readonly string $tokenHash,
        public readonly ?int $proyectoId,
        public readonly ?string $identificacion,
        public readonly ?string $tipoIdentificacionCodigo,
        public readonly ?string $redirectPath,
        public readonly DateTimeImmutable $expiraEn,
        public readonly ?string $ipOrigen,
        public readonly ?string $userAgent,
        ?DateTimeImmutable $consumidoEn = null,
    ) {
        $this->consumidoEn = $consumidoEn;
    }

    public static function crear(
        string $publicId,
        int $usuarioId,
        TokenClaroHash $tokenClaroHash,
        DateTimeImmutable $expiraEn,
        ?int $proyectoId = null,
        ?string $identificacion = null,
        ?string $tipoIdentificacionCodigo = null,
        ?string $redirectPath = null,
        ?string $ipOrigen = null,
        ?string $userAgent = null,
    ): self {
        $token = new self(
            publicId: $publicId,
            usuarioId: $usuarioId,
            tokenHash: $tokenClaroHash->hash,
            proyectoId: $proyectoId,
            identificacion: $identificacion,
            tipoIdentificacionCodigo: $tipoIdentificacionCodigo,
            redirectPath: $redirectPath,
            expiraEn: $expiraEn,
            ipOrigen: $ipOrigen,
            userAgent: $userAgent,
        );

        $token->eventos[] = new TokenSsoEmitido($publicId, $usuarioId, $proyectoId);

        return $token;
    }

    public static function reconstituir(
        string $publicId,
        int $usuarioId,
        string $tokenHash,
        DateTimeImmutable $expiraEn,
        ?DateTimeImmutable $consumidoEn,
        ?int $proyectoId = null,
        ?string $identificacion = null,
        ?string $tipoIdentificacionCodigo = null,
        ?string $redirectPath = null,
        ?string $ipOrigen = null,
        ?string $userAgent = null,
    ): self {
        return new self(
            publicId: $publicId,
            usuarioId: $usuarioId,
            tokenHash: $tokenHash,
            proyectoId: $proyectoId,
            identificacion: $identificacion,
            tipoIdentificacionCodigo: $tipoIdentificacionCodigo,
            redirectPath: $redirectPath,
            expiraEn: $expiraEn,
            ipOrigen: $ipOrigen,
            userAgent: $userAgent,
            consumidoEn: $consumidoEn,
        );
    }

    public function expirado(): bool
    {
        return $this->expiraEn < new DateTimeImmutable('now');
    }

    public function consumir(DateTimeImmutable $ahora): void
    {
        if ($this->consumidoEn !== null) {
            throw TokenSsoYaConsumidoException::crear();
        }

        if ($this->expirado()) {
            throw TokenSsoExpiradoException::crear();
        }

        $this->consumidoEn = $ahora;
        $this->eventos[] = new TokenSsoConsumido($this->publicId, $this->usuarioId);
    }

    public function consumidoEn(): ?DateTimeImmutable
    {
        return $this->consumidoEn;
    }

    /** @return list<object> */
    public function pullEventos(): array
    {
        $eventos = $this->eventos;
        $this->eventos = [];

        return $eventos;
    }
}
