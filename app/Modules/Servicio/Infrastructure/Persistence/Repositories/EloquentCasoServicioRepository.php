<?php

declare(strict_types=1);

namespace App\Modules\Servicio\Infrastructure\Persistence\Repositories;

use App\Modules\Servicio\Domain\Contracts\CasoServicioRepository;
use App\Modules\Servicio\Domain\Entities\CasoServicio;
use App\Modules\Servicio\Domain\ValueObjects\CodigoServicio;
use App\Modules\Servicio\Infrastructure\Persistence\Models\CasoServicioModel;
use DateTimeImmutable;

final class EloquentCasoServicioRepository implements CasoServicioRepository
{
    public function save(CasoServicio $servicio): CasoServicio
    {
        $model = CasoServicioModel::query()->sinScopeProyecto()->find($servicio->casoId)
            ?? new CasoServicioModel;

        $model->caso_id = $servicio->casoId;
        $model->proyecto_id = $servicio->proyectoId;
        $model->codigo_servicio = $servicio->codigoServicio->valor;
        $model->tipo_accion_servicio_id = $servicio->tipoAccionServicioId;
        $model->estado_tecnico_id = $servicio->estadoTecnicoId;
        $model->direccion_servicio = $servicio->direccionServicio;
        $model->tecnico_asignado = $servicio->tecnicoAsignado;
        $model->fecha_solicitud = $servicio->fechaSolicitud;
        $model->fecha_programada = $servicio->fechaProgramada;

        $model->save();

        return $servicio;
    }

    public function buscarPorCasoId(int $casoId): ?CasoServicio
    {
        /** @var CasoServicioModel|null $model */
        $model = CasoServicioModel::query()->sinScopeProyecto()->find($casoId);
        if ($model === null) {
            return null;
        }

        return CasoServicio::reconstituir(
            casoId: (int) $model->caso_id,
            proyectoId: (int) $model->proyecto_id,
            codigoServicio: new CodigoServicio((string) $model->codigo_servicio),
            tipoAccionServicioId: $model->tipo_accion_servicio_id === null ? null : (int) $model->tipo_accion_servicio_id,
            estadoTecnicoId: $model->estado_tecnico_id === null ? null : (int) $model->estado_tecnico_id,
            direccionServicio: $model->direccion_servicio,
            tecnicoAsignado: $model->tecnico_asignado,
            fechaSolicitud: $model->fecha_solicitud === null ? null : $this->hidratarFecha($model->fecha_solicitud),
            fechaProgramada: $model->fecha_programada === null ? null : $this->hidratarFecha($model->fecha_programada),
        );
    }

    public function existeCodigoEnProyecto(int $proyectoId, string $codigoServicio): bool
    {
        return CasoServicioModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('codigo_servicio', $codigoServicio)
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
