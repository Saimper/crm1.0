<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Persistence\Repositories;

use App\Modules\Tenancy\Domain\Contracts\CarteraRepository;
use App\Modules\Tenancy\Domain\Entities\Cartera;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoCartera;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\CarteraModel;
use DateTimeImmutable;
use RuntimeException;

final class EloquentCarteraRepository implements CarteraRepository
{
    public function save(Cartera $cartera): Cartera
    {
        $model = $cartera->id !== null
            ? CarteraModel::query()->findOrFail($cartera->id)
            : new CarteraModel();

        $model->public_id    = $cartera->publicId;
        $model->proyecto_id  = $cartera->proyectoId;
        $model->codigo       = $cartera->codigo->asString();
        $model->nombre       = $cartera->nombre;
        $model->descripcion  = $cartera->descripcion;
        $model->activo       = $cartera->activo;
        if ($cartera->id === null) {
            $model->creada_en = $cartera->creadaEn;
        }

        $model->save();

        return $cartera->id !== null ? $cartera : $cartera->conId((int) $model->id);
    }

    public function buscarPorId(int $id): Cartera
    {
        /** @var CarteraModel|null $model */
        $model = CarteraModel::query()->find($id);
        if ($model === null) {
            throw new RuntimeException("Cartera {$id} no encontrada.");
        }

        return Cartera::reconstituir(
            id:          (int) $model->id,
            publicId:    (string) $model->public_id,
            proyectoId:  (int) $model->proyecto_id,
            codigo:      new CodigoCartera((string) $model->codigo),
            nombre:      (string) $model->nombre,
            descripcion: $model->descripcion !== null ? (string) $model->descripcion : null,
            activo:      (bool) $model->activo,
            creadaEn:    $this->hidratarFecha($model->creada_en),
        );
    }

    public function existePorCodigoEnProyecto(int $proyectoId, CodigoCartera $codigo): bool
    {
        return CarteraModel::query()
            ->where('proyecto_id', $proyectoId)
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
