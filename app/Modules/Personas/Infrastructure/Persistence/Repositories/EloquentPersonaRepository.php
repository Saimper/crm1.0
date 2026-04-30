<?php

declare(strict_types=1);

namespace App\Modules\Personas\Infrastructure\Persistence\Repositories;

use App\Modules\Personas\Domain\Contracts\PersonaRepository;
use App\Modules\Personas\Domain\Entities\Persona;
use App\Modules\Personas\Domain\ValueObjects\Identificacion;
use App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel;

final class EloquentPersonaRepository implements PersonaRepository
{
    public function save(Persona $persona): Persona
    {
        $model = new PersonaModel;
        $model->public_id = $persona->publicId;
        $model->proyecto_id = $persona->proyectoId;
        $model->tipo_persona = $persona->tipoPersona->value;
        $model->tipo_identificacion_id = $persona->tipoIdentificacionId;
        $model->identificacion = $persona->identificacion->asString();
        $model->nombres = $persona->nombres;
        $model->apellidos = $persona->apellidos;
        $model->razon_social = $persona->razonSocial;
        $model->fecha_nacimiento = $persona->fechaNacimiento;
        $model->creada_en = $persona->creadaEn;

        $model->save();

        return $persona->conId((int) $model->id);
    }

    public function existePorIdentificacionEnProyecto(
        int $proyectoId,
        int $tipoIdentificacionId,
        Identificacion $identificacion,
    ): bool {
        // Saltamos el scope automático del trait porque esta query valida unicidad
        // explícitamente por proyecto (puede correrse fuera del contexto HTTP del mismo proyecto).
        return PersonaModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('tipo_identificacion_id', $tipoIdentificacionId)
            ->where('identificacion', $identificacion->asString())
            ->whereNull('eliminada_en')
            ->exists();
    }
}
