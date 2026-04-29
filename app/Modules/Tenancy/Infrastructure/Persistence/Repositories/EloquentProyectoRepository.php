<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Persistence\Repositories;

use App\Modules\Tenancy\Domain\Contracts\ProyectoRepository;
use App\Modules\Tenancy\Domain\Entities\Proyecto;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoProyecto;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use DateTimeImmutable;
use RuntimeException;

final class EloquentProyectoRepository implements ProyectoRepository
{
    public function save(Proyecto $proyecto): Proyecto
    {
        $model = $proyecto->id !== null
            ? ProyectoModel::query()->findOrFail($proyecto->id)
            : new ProyectoModel();

        $model->public_id      = $proyecto->publicId;
        $model->mandante_id    = $proyecto->mandanteId;
        $model->codigo         = $proyecto->codigo->asString();
        $model->nombre         = $proyecto->nombre;
        $model->descripcion    = $proyecto->descripcion;
        $model->tipo_operacion = $proyecto->tipoOperacion->value;
        $model->activo         = $proyecto->activo;
        $model->fecha_inicio   = $proyecto->fechaInicio;
        $model->fecha_fin      = $proyecto->fechaFin;
        if ($proyecto->id === null) {
            $model->creada_en = $proyecto->creadaEn;
        }

        $model->save();

        return $proyecto->id !== null ? $proyecto : $proyecto->conId((int) $model->id);
    }

    public function buscarPorId(int $id): Proyecto
    {
        /** @var ProyectoModel|null $model */
        $model = ProyectoModel::query()->find($id);
        if ($model === null) {
            throw new RuntimeException("Proyecto {$id} no encontrado.");
        }

        return Proyecto::reconstituir(
            id:            (int) $model->id,
            publicId:      (string) $model->public_id,
            mandanteId:    (int) $model->mandante_id,
            codigo:        new CodigoProyecto((string) $model->codigo),
            nombre:        (string) $model->nombre,
            descripcion:   $model->descripcion !== null ? (string) $model->descripcion : null,
            tipoOperacion: TipoOperacion::from((string) $model->tipo_operacion),
            activo:        (bool) $model->activo,
            fechaInicio:   $model->fecha_inicio instanceof DateTimeImmutable ? $model->fecha_inicio : null,
            fechaFin:      $model->fecha_fin    instanceof DateTimeImmutable ? $model->fecha_fin    : null,
            creadaEn:      $this->hidratarFecha($model->creada_en),
        );
    }

    public function existePorCodigoEnMandante(int $mandanteId, CodigoProyecto $codigo): bool
    {
        return ProyectoModel::query()
            ->where('mandante_id', $mandanteId)
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
