<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Infrastructure\Persistence\Repositories;

use App\Modules\Servicio\Domain\Contracts\CompromisoAccionServicioRepository;
use App\Modules\Servicio\Domain\Entities\CompromisoAccionServicio;
use App\Modules\Servicio\Domain\ValueObjects\DescripcionAccion;
use App\Modules\Servicio\Domain\ValueObjects\FechaProgramada;
use App\Modules\Servicio\Infrastructure\Persistence\Models\CompromisoAccionServicioModel;
use DateTimeImmutable;

final class EloquentCompromisoAccionServicioRepository implements CompromisoAccionServicioRepository
{
    public function save(CompromisoAccionServicio $accion): CompromisoAccionServicio
    {
        $model = CompromisoAccionServicioModel::query()->sinScopeProyecto()->find($accion->compromisoId)
            ?? new CompromisoAccionServicioModel();

        $model->compromiso_id           = $accion->compromisoId;
        $model->proyecto_id             = $accion->proyectoId;
        $model->descripcion_accion      = $accion->descripcion->valor;
        $model->fecha_programada        = $accion->fechaProgramada->fecha;
        $model->tipo_accion_servicio_id = $accion->tipoAccionServicioId;
        $model->tecnico_asignado        = $accion->tecnicoAsignado;

        $model->save();

        return $accion;
    }

    public function buscarPorCompromisoId(int $compromisoId): ?CompromisoAccionServicio
    {
        /** @var CompromisoAccionServicioModel|null $model */
        $model = CompromisoAccionServicioModel::query()->sinScopeProyecto()->find($compromisoId);
        if ($model === null) {
            return null;
        }

        return CompromisoAccionServicio::reconstituir(
            compromisoId:         (int) $model->compromiso_id,
            proyectoId:           (int) $model->proyecto_id,
            descripcion:          new DescripcionAccion((string) $model->descripcion_accion),
            fechaProgramada:      new FechaProgramada($this->hidratarFechaHora($model->fecha_programada)),
            tipoAccionServicioId: $model->tipo_accion_servicio_id === null ? null : (int) $model->tipo_accion_servicio_id,
            tecnicoAsignado:      $model->tecnico_asignado,
        );
    }

    private function hidratarFechaHora(mixed $valor): DateTimeImmutable
    {
        if ($valor instanceof DateTimeImmutable) {
            return $valor;
        }

        return new DateTimeImmutable((string) $valor);
    }
}
