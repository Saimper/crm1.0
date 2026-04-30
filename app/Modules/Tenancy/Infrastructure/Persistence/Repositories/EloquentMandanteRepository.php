<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Persistence\Repositories;

use App\Modules\Tenancy\Domain\Contracts\MandanteRepository;
use App\Modules\Tenancy\Domain\Entities\Mandante;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoMandante;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\MandanteModel;
use DateTimeImmutable;
use RuntimeException;

final class EloquentMandanteRepository implements MandanteRepository
{
    public function save(Mandante $mandante): Mandante
    {
        $model = $mandante->id !== null
            ? MandanteModel::query()->findOrFail($mandante->id)
            : new MandanteModel;

        $model->public_id = $mandante->publicId;
        $model->codigo = $mandante->codigo->asString();
        $model->nombre = $mandante->nombre;
        $model->documento = $mandante->documento;
        $model->activo = $mandante->activo;
        if ($mandante->id === null) {
            $model->creada_en = $mandante->creadaEn;
        }

        $model->save();

        return $mandante->id !== null ? $mandante : $mandante->conId((int) $model->id);
    }

    public function buscarPorId(int $id): Mandante
    {
        /** @var MandanteModel|null $model */
        $model = MandanteModel::query()->find($id);
        if ($model === null) {
            throw new RuntimeException("Mandante {$id} no encontrado.");
        }

        return Mandante::reconstituir(
            id: (int) $model->id,
            publicId: (string) $model->public_id,
            codigo: new CodigoMandante((string) $model->codigo),
            nombre: (string) $model->nombre,
            documento: $model->documento !== null ? (string) $model->documento : null,
            activo: (bool) $model->activo,
            creadaEn: $this->hidratarFecha($model->creada_en),
        );
    }

    public function existePorCodigo(CodigoMandante $codigo): bool
    {
        return MandanteModel::query()
            ->where('codigo', $codigo->asString())
            ->whereNull('eliminada_en')
            ->exists();
    }

    private function hidratarFecha(mixed $valor): DateTimeImmutable
    {
        if ($valor instanceof DateTimeImmutable) {
            return $valor;
        }

        return new DateTimeImmutable((string) $valor);
    }
}
