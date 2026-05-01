<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Persistence\Repositories;

use App\Modules\Importaciones\Domain\Contracts\ImportacionRepository;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use DateTimeImmutable;

/**
 * F34D — implementa ImportacionRepository contra Eloquent. Toda lógica que
 * antes estaba en EncolarImportacion (UseCase) lookeando ImportacionModel
 * vive aquí.
 */
final class EloquentImportacionRepository implements ImportacionRepository
{
    public function buscarPorId(int $id): ?array
    {
        $row = ImportacionModel::query()
            ->sinScopeProyecto()
            ->where('id', $id)
            ->first();

        return $row === null ? null : $this->hidratar($row);
    }

    public function buscarPorIdConLock(int $id): ?array
    {
        $row = ImportacionModel::query()
            ->sinScopeProyecto()
            ->where('id', $id)
            ->lockForUpdate()
            ->first();

        return $row === null ? null : $this->hidratar($row);
    }

    public function marcarComoEncolada(
        int $id,
        ModoImportacion $modo,
        EstadoImportacion $nuevoEstado,
        DateTimeImmutable $iniciadoEn,
    ): int {
        return ImportacionModel::query()
            ->sinScopeProyecto()
            ->where('id', $id)
            ->update([
                'modo' => $modo->value,
                'estado' => $nuevoEstado->value,
                'iniciado_en' => $iniciadoEn,
            ]);
    }

    /** @return array{id:int, proyecto_id:int, estado:string, modo:string} */
    private function hidratar(ImportacionModel $row): array
    {
        return [
            'id' => (int) $row->id,
            'proyecto_id' => (int) $row->proyecto_id,
            'estado' => (string) $row->estado,
            'modo' => (string) $row->modo,
        ];
    }
}
