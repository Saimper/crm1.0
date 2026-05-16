<?php

declare(strict_types=1);

namespace App\Modules\Integracion\Domain\ValueObjects;

use App\Modules\Integracion\Domain\Exceptions\SecretMandanteInvalido;

/**
 * sso_secret de mandante. 64 caracteres hex (32 bytes random).
 *
 * Generado por bin2hex(random_bytes(32)). Usado para firmar JWT entre
 * wrapper y CRM. Vive en mandantes.sso_secret y mandantes.sso_secret_old
 * (durante ventana 24h tras rotación).
 */
final readonly class MandanteSsoSecret
{
    private const LONGITUD = 64;

    private const PATRON_HEX = '/^[a-f0-9]{64}$/';

    private function __construct(public string $valor) {}

    public static function desde(string $valor): self
    {
        if (strlen($valor) !== self::LONGITUD || preg_match(self::PATRON_HEX, $valor) !== 1) {
            throw SecretMandanteInvalido::crear();
        }

        return new self($valor);
    }

    public static function generar(): self
    {
        return new self(bin2hex(random_bytes(32)));
    }
}
