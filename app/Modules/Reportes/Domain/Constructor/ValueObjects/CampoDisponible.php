<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Domain\Constructor\ValueObjects;

use App\Modules\Reportes\Domain\Constructor\Enums\TipoCampoReporte;

/**
 * Descripción server-side de un campo permitido en un reporte.
 *
 * - $clave: identificador canónico expuesto al cliente (ej. "casos.persona.nombres").
 * - $etiqueta: nombre legible por defecto.
 * - $tipo: usado para validar operadores y formatear export.
 * - $sql: expresión SQL ya calificada (alias.columna) — NUNCA viene de input usuario.
 * - $joinKey: clave de relación predeclarada que debe agregarse a la query si se usa este campo.
 * - $esCampoPersonalizado: flag para construir join especial sobre valores_campo_personalizado.
 * - $campoPersonalizadoId: si aplica, ID del campo personalizado §7.
 */
final readonly class CampoDisponible
{
    public function __construct(
        public string $clave,
        public string $etiqueta,
        public TipoCampoReporte $tipo,
        public string $sql,
        public ?string $joinKey = null,
        public bool $esCampoPersonalizado = false,
        public ?int $campoPersonalizadoId = null,
    ) {}
}
