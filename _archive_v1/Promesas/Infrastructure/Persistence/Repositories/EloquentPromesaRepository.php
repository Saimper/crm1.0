<?php

declare(strict_types=1);

namespace App\Modules\Promesas\Infrastructure\Persistence\Repositories;

use App\Modules\Promesas\Domain\Contracts\PromesaRepository;
use App\Modules\Promesas\Domain\Entities\Promesa;
use App\Modules\Promesas\Domain\ValueObjects\EstadoPromesa;
use App\Modules\Promesas\Domain\ValueObjects\FechaPromesa;
use App\Modules\Promesas\Domain\ValueObjects\MontoPromesa;
use App\Modules\Promesas\Infrastructure\Persistence\Models\PromesaModel;
use DateTimeImmutable;

final class EloquentPromesaRepository implements PromesaRepository
{
    public function save(Promesa $promesa): Promesa
    {
        $model = $promesa->id !== null
            ? PromesaModel::query()->findOrFail($promesa->id)
            : new PromesaModel;

        $model->public_id = $promesa->publicId;
        $model->producto_id = $promesa->productoId;
        $model->gestion_origen_id = $promesa->gestionOrigenId;
        $model->usuario_id = $promesa->usuarioId;
        $model->tipo_pago_id = $promesa->tipoPagoId;
        $model->monto_promesa = $promesa->monto->asDecimal();
        $model->fecha_promesa = $promesa->fecha->fecha;
        $model->estado = $promesa->estado->value;
        $model->fecha_resolucion = $promesa->fechaResolucion;
        $model->creada_en = $promesa->creadaEn;

        $model->save();

        return $promesa->id !== null ? $promesa : $promesa->conId((int) $model->id);
    }

    public function buscarPorId(int $id): Promesa
    {
        /** @var PromesaModel $model */
        $model = PromesaModel::query()->findOrFail($id);

        return Promesa::reconstituir(
            id: (int) $model->id,
            publicId: (string) $model->public_id,
            productoId: (int) $model->producto_id,
            gestionOrigenId: (int) $model->gestion_origen_id,
            usuarioId: (int) $model->usuario_id,
            tipoPagoId: $model->tipo_pago_id !== null ? (int) $model->tipo_pago_id : null,
            monto: new MontoPromesa((string) $model->monto_promesa),
            fecha: FechaPromesa::hidratar($model->fecha_promesa instanceof DateTimeImmutable
                                ? $model->fecha_promesa
                                : new DateTimeImmutable((string) $model->fecha_promesa)),
            estado: EstadoPromesa::from((string) $model->estado),
            fechaResolucion: $model->fecha_resolucion instanceof DateTimeImmutable
                                ? $model->fecha_resolucion
                                : ($model->fecha_resolucion !== null ? new DateTimeImmutable((string) $model->fecha_resolucion) : null),
            creadaEn: $model->creada_en instanceof DateTimeImmutable
                                ? $model->creada_en
                                : new DateTimeImmutable((string) $model->creada_en),
        );
    }

    public function existenVigentesParaProducto(int $productoId): bool
    {
        return PromesaModel::query()
            ->where('producto_id', $productoId)
            ->where('estado', EstadoPromesa::PENDIENTE->value)
            ->exists();
    }
}
