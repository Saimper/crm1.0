<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Persistence\Repositories;

use App\Modules\Importaciones\Domain\Contracts\ImportacionRepository;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Importaciones\Infrastructure\Persistence\Models\ImportacionModel;
use DateTimeImmutable;
use InvalidArgumentException;

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
        $cambios = [
            'modo' => $modo->value,
            'estado' => $nuevoEstado->value,
            'iniciado_en' => $iniciadoEn,
        ];

        // La columna y el JSON del esquema tienen que decir el mismo modo: el
        // JSON se guardó al final del paso 2 con el modo de por defecto, y el
        // supervisor elige el suyo en el paso 3. Quien lea cualquiera de los
        // dos después de esto ve lo que se eligió.
        $esquema = $this->esquemaConModo($id, $modo);
        if ($esquema !== null) {
            $cambios['esquema'] = $esquema;
        }

        return ImportacionModel::query()
            ->sinScopeProyecto()
            ->where('id', $id)
            ->update($cambios);
    }

    /**
     * El JSON del esquema reescrito con el modo, o null si no hay esquema o no
     * se puede leer. Si está malformado se deja como está a propósito: es el
     * motor quien lo detecta y marca la importación fallida con un motivo
     * legible; abortar aquí dejaría al supervisor con un error genérico en el
     * botón de ejecutar.
     */
    private function esquemaConModo(int $id, ModoImportacion $modo): ?string
    {
        $json = ImportacionModel::query()
            ->sinScopeProyecto()
            ->where('id', $id)
            ->toBase()
            ->value('esquema');

        if ($json === null) {
            return null;
        }

        try {
            return EsquemaImportacion::deserializar((string) $json)->conModo($modo)->serializar();
        } catch (InvalidArgumentException) {
            return null;
        }
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
