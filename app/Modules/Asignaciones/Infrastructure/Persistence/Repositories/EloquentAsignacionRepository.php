<?php

declare(strict_types=1);

namespace App\Modules\Asignaciones\Infrastructure\Persistence\Repositories;

use App\Modules\Asignaciones\Domain\Contracts\AsignacionRepository;
use App\Modules\Asignaciones\Domain\Entities\Asignacion;
use App\Modules\Asignaciones\Domain\ValueObjects\EstadoAsignacion;
use App\Modules\Asignaciones\Infrastructure\Persistence\Models\AsignacionModel;
use DateTimeImmutable;
use RuntimeException;

final class EloquentAsignacionRepository implements AsignacionRepository
{
    public function save(Asignacion $asignacion): Asignacion
    {
        $model = $asignacion->id !== null
            ? AsignacionModel::query()->sinScopeProyecto()->findOrFail($asignacion->id)
            : new AsignacionModel;

        $model->public_id = $asignacion->publicId;
        $model->proyecto_id = $asignacion->proyectoId;
        $model->campana_id = $asignacion->campanaId;
        $model->caso_id = $asignacion->casoId;
        $model->usuario_id = $asignacion->usuarioId;
        $model->fecha_asignacion = $asignacion->fechaAsignacion;
        $model->prioridad = $asignacion->prioridad;
        $model->estado = $asignacion->estado->value;
        $model->cerrada_en = $asignacion->cerradaEn;
        if ($asignacion->id === null) {
            $model->creada_en = $asignacion->creadaEn;
        }

        $model->save();

        return $asignacion->id !== null ? $asignacion : $asignacion->conId((int) $model->id);
    }

    public function buscarPorId(int $id): Asignacion
    {
        /** @var AsignacionModel|null $model */
        $model = AsignacionModel::query()->sinScopeProyecto()->find($id);
        if ($model === null) {
            throw new RuntimeException("Asignación {$id} no encontrada.");
        }

        return Asignacion::reconstituir(
            id: (int) $model->id,
            publicId: (string) $model->public_id,
            proyectoId: (int) $model->proyecto_id,
            campanaId: (int) $model->campana_id,
            casoId: (int) $model->caso_id,
            usuarioId: (int) $model->usuario_id,
            fechaAsignacion: $model->fecha_asignacion instanceof DateTimeImmutable
                ? $model->fecha_asignacion
                : new DateTimeImmutable((string) $model->fecha_asignacion),
            prioridad: (int) $model->prioridad,
            estado: EstadoAsignacion::from((string) $model->estado),
            cerradaEn: $model->cerrada_en instanceof DateTimeImmutable ? $model->cerrada_en : null,
            creadaEn: $model->creada_en instanceof DateTimeImmutable
                ? $model->creada_en
                : new DateTimeImmutable((string) $model->creada_en),
        );
    }

    public function existeParaCampanaCaso(int $campanaId, int $casoId): bool
    {
        return AsignacionModel::query()
            ->sinScopeProyecto()
            ->where('campana_id', $campanaId)
            ->where('caso_id', $casoId)
            ->exists();
    }
}
