<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Domain\ValueObjects;

/**
 * Tokens declarativos de auto-relleno aplicados al render inicial del formulario (§7.4 CLAUDE.md).
 * NO ejecuta lógica de usuario final; cada token resuelve a un valor del contexto actual.
 */
enum AutoFill: string
{
    case NOW = 'now';
    case TODAY = 'today';
    case USUARIO_NOMBRE = 'usuario_nombre';
    case USUARIO_EMAIL = 'usuario_email';
    case PROYECTO_CODIGO = 'proyecto_codigo';

    public function tipoCompatible(TipoCampo $tipo): bool
    {
        return match ($this) {
            self::NOW => $tipo === TipoCampo::FECHA_HORA,
            self::TODAY => $tipo === TipoCampo::FECHA || $tipo === TipoCampo::FECHA_HORA,
            self::USUARIO_NOMBRE,
            self::USUARIO_EMAIL,
            self::PROYECTO_CODIGO => $tipo === TipoCampo::TEXTO_CORTO || $tipo === TipoCampo::TEXTO_LARGO,
        };
    }
}
