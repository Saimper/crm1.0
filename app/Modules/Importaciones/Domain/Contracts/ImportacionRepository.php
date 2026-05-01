<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\Contracts;

use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use DateTimeImmutable;

/**
 * Contrato de persistencia para Importacion. Expone solo lo que la capa
 * Application necesita; los detalles de Eloquent quedan en Infrastructure.
 *
 * F34D — extraído desde el UseCase EncolarImportacion. Los Livewire de
 * Importaciones siguen usando ImportacionModel directo (capa Infrastructure
 * a Infrastructure es admisible §3); el contrato existe para garantizar que
 * UseCases y futuros consumers de Application no importen Models.
 */
interface ImportacionRepository
{
    /**
     * Devuelve datos crudos de la importación o null si no existe.
     * Saltea el scope de proyecto activo (la importación se lookea por id).
     *
     * @return array{id:int, proyecto_id:int, estado:string, modo:string}|null
     */
    public function buscarPorId(int $id): ?array;

    /**
     * Adquiere el row con FOR UPDATE dentro de la transacción actual.
     * Usado por EncolarImportacion para evitar doble despacho.
     *
     * @return array{id:int, proyecto_id:int, estado:string, modo:string}|null
     */
    public function buscarPorIdConLock(int $id): ?array;

    /**
     * Actualiza modo + estado + iniciado_en. Devuelve el número de filas afectadas.
     */
    public function marcarComoEncolada(int $id, ModoImportacion $modo, EstadoImportacion $nuevoEstado, DateTimeImmutable $iniciadoEn): int;
}
