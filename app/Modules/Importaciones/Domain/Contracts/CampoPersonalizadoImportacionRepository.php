<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Contracts;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;

/**
 * Contrato para la gestión de campos personalizados durante importaciones.
 *
 * Permite crear campos y persistir valores en lote sin importar modelos
 * Eloquent del módulo CamposPersonalizados.
 */
interface CampoPersonalizadoImportacionRepository
{
    /**
     * Verifica si ya existe un campo personalizado con el código dado
     * para el ámbito caso de una cartera específica.
     */
    public function existeCampo(int $proyectoId, int $carteraId, string $codigo): bool;

    /**
     * Crea un campo personalizado en ámbito 'caso' con ambito_id = carteraId.
     * Retorna el ID del campo creado.
     */
    public function crearCampo(int $proyectoId, int $carteraId, string $codigo, string $etiqueta, TipoCampo $tipo): int;

    /**
     * Persiste valores de campos personalizados en lote usando
     * INSERT ON DUPLICATE KEY UPDATE.
     *
     * @param  list<array{campo_id: int, entidad_id: int, valor: mixed, tipo: string}>  $lote
     */
    public function guardarValoresEnLote(array $lote): void;

    /**
     * Carga todos los campos personalizados de una cartera en una sola
     * query para evitar N+1 durante el procesamiento del job.
     *
     * @return array<string, array{id: int, tipo: string}> mapa de código de campo a [id, tipo]
     */
    public function obtenerMapaCampos(int $proyectoId, int $carteraId): array;

    /**
     * Registra en la tabla de auditoría qué campos personalizados fueron
     * creados o reutilizados por una importación.
     *
     * @param  list<array{campo_id: int, columna_original: string}>  $campos
     */
    public function registrarAuditoriaCampos(int $importacionId, array $campos): void;
}
