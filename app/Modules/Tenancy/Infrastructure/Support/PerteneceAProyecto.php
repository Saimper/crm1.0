<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Trait para modelos Eloquent que viven bajo el scope de un proyecto activo.
 *
 * Comportamiento:
 *  - Global Scope automático: `where {tabla}.proyecto_id = proyecto_activo`.
 *  - Al crear un modelo nuevo sin `proyecto_id`, lo setea desde el proyecto activo.
 *  - Se puede saltar el scope explícitamente con el scope local `sinScopeProyecto`.
 */
trait PerteneceAProyecto
{
    protected static function bootPerteneceAProyecto(): void
    {
        static::addGlobalScope(new ScopeProyectoActivo);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('proyecto_id') === null && app()->bound('tenancy.proyecto_activo')) {
                $proyecto = app('tenancy.proyecto_activo');
                $model->setAttribute('proyecto_id', is_object($proyecto) ? $proyecto->id : $proyecto);
            }
        });
    }

    public function scopeSinScopeProyecto(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ScopeProyectoActivo::class);
    }
}

final class ScopeProyectoActivo implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('tenancy.proyecto_activo')) {
            return;
        }

        $proyecto = app('tenancy.proyecto_activo');
        $proyectoId = is_object($proyecto) ? (int) $proyecto->id : (int) $proyecto;

        $builder->where($model->getTable().'.proyecto_id', $proyectoId);
    }
}
