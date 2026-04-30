<?php

declare(strict_types=1);

namespace App\Modules\Casos\Infrastructure\Persistence\Repositories;

use App\Modules\Casos\Domain\Contracts\CasoRepository;
use App\Modules\Casos\Domain\Entities\Caso;
use App\Modules\Casos\Domain\ValueObjects\TipoCaso;
use App\Modules\Casos\Infrastructure\Persistence\Models\CasoModel;
use DateTimeImmutable;
use RuntimeException;

final class EloquentCasoRepository implements CasoRepository
{
    public function save(Caso $caso): Caso
    {
        $model = $caso->id !== null
            ? CasoModel::query()->sinScopeProyecto()->findOrFail($caso->id)
            : new CasoModel;

        $model->public_id = $caso->publicId;
        $model->proyecto_id = $caso->proyectoId;
        $model->cartera_id = $caso->carteraId;
        $model->persona_id = $caso->personaId;
        $model->tipo_caso = $caso->tipoCaso->value;
        $model->estado_caso_id = $caso->estadoCasoId;
        $model->fecha_ingreso = $caso->fechaIngreso;
        $model->prioridad = $caso->prioridad;
        $model->cerrado_en = $caso->cerradoEn;
        if ($caso->id === null) {
            $model->creada_en = $caso->creadaEn;
        }

        $model->save();

        return $caso->id !== null ? $caso : $caso->conId((int) $model->id);
    }

    public function buscarPorId(int $id): Caso
    {
        /** @var CasoModel|null $model */
        $model = CasoModel::query()->sinScopeProyecto()->find($id);
        if ($model === null) {
            throw new RuntimeException("Caso {$id} no encontrado.");
        }

        return Caso::reconstituir(
            id: (int) $model->id,
            publicId: (string) $model->public_id,
            proyectoId: (int) $model->proyecto_id,
            carteraId: (int) $model->cartera_id,
            personaId: (int) $model->persona_id,
            tipoCaso: TipoCaso::from((string) $model->tipo_caso),
            estadoCasoId: (int) $model->estado_caso_id,
            fechaIngreso: $this->hidratarFecha($model->fecha_ingreso),
            prioridad: (int) $model->prioridad,
            cerradoEn: $model->cerrado_en instanceof DateTimeImmutable ? $model->cerrado_en : null,
            creadaEn: $this->hidratarFecha($model->creada_en),
        );
    }

    private function hidratarFecha(mixed $valor): DateTimeImmutable
    {
        if ($valor instanceof DateTimeImmutable) {
            return $valor;
        }

        return new DateTimeImmutable((string) $valor);
    }
}
