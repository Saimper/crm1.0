<?php

declare(strict_types=1);

namespace App\Modules\Campanas\Infrastructure\Persistence\Repositories;

use App\Modules\Campanas\Domain\Contracts\CampanaRepository;
use App\Modules\Campanas\Domain\Entities\Campana;
use App\Modules\Campanas\Domain\ValueObjects\CodigoCampana;
use App\Modules\Campanas\Infrastructure\Persistence\Models\CampanaModel;

final class EloquentCampanaRepository implements CampanaRepository
{
    public function save(Campana $campana): Campana
    {
        $model = new CampanaModel();
        $model->public_id     = $campana->publicId;
        $model->proyecto_id   = $campana->proyectoId;
        $model->codigo        = $campana->codigo->asString();
        $model->nombre        = $campana->nombre;
        $model->descripcion   = $campana->descripcion;
        $model->estado        = $campana->estado->value;
        $model->fecha_inicio  = $campana->fechaInicio;
        $model->fecha_fin     = $campana->fechaFin;
        $model->creada_por_id = $campana->creadaPorId;
        $model->creada_en     = $campana->creadaEn;

        $model->save();

        return $campana->conId((int) $model->id);
    }

    public function existePorCodigoEnProyecto(int $proyectoId, CodigoCampana $codigo): bool
    {
        return CampanaModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('codigo', $codigo->asString())
            ->whereNull('eliminada_en')
            ->exists();
    }
}
