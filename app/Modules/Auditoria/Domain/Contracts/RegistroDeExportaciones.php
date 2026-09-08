<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Domain\Contracts;

/**
 * Deja constancia en la auditoría de que alguien sacó datos del sistema.
 *
 * Es un contrato y no el modelo porque quien exporta —Personas, Casos,
 * Gestiones, Compromisos, Importaciones— vive en otro módulo y no puede tocar
 * el Eloquent de Auditoría (§3, §13.6). Y es un evento propio (`exportado`) y
 * no un «creado» sobre nada porque descargar el padrón completo de un cliente
 * es la acción con más peso en privacidad de la aplicación y era la única sin
 * huella en el sitio donde ese cliente comprueba quién tocó sus datos.
 */
interface RegistroDeExportaciones
{
    /**
     * @param  string  $entidadTipo  La tabla exportada (`personas`, `casos`, `gestiones`,
     *                               `compromisos`, `auditorias`, `importacion_filas`).
     * @param  array<string, mixed>  $filtros  Con qué recorte se pidió. Sólo claves y valores
     *                                         de filtro, nunca los datos exportados.
     * @param  int|null  $proyectoId  Nulo en las descargas transversales del mandante.
     * @param  int|null  $mandanteId  Si es nulo y hay proyecto, se deduce del proyecto.
     * @param  bool  $completa  Falso si la descarga se cortó a mitad: lo que se emitió
     *                          hasta entonces salió igual, y la huella lo dice.
     */
    public function registrar(
        string $entidadTipo,
        array $filtros,
        int $totalFilas,
        ?int $proyectoId,
        ?int $mandanteId = null,
        ?int $usuarioId = null,
        bool $completa = true,
    ): void;
}
