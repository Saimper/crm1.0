<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Trait para modelos que viven bajo el mandante activo — la empresa cliente.
 *
 * Hermano de PerteneceAProyecto, un piso más arriba en la jerarquía
 * Mandante → Proyecto → Cartera → Persona → Caso.
 *
 * Nota deliberada sobre el fallo abierto: igual que su hermano, este scope no
 * filtra si no hay mandante activo. Es coherente con lo que ya existe y evita
 * romper de golpe los comandos, jobs y pantallas que hoy corren sin contexto.
 * Cerrar los dos a la vez —que sin tenant no haya consulta— es trabajo de la
 * Fase 2, y hacerlo antes de que el contexto esté enchufado en todas partes
 * dejaría la aplicación inservible.
 *
 * Este trait todavía NO se aplica a ningún modelo: colgarlo de ProyectoModel
 * ahora rompería el propio ResolverProyectoActivo, que consulta proyectos antes
 * de que exista mandante activo. Lo aplica la Fase 3, pantalla por pantalla.
 */
trait PerteneceAMandante
{
    protected static function bootPerteneceAMandante(): void
    {
        static::addGlobalScope(new ScopeMandanteActivo);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('mandante_id') === null && app()->bound('tenancy.mandante_activo')) {
                $mandante = app('tenancy.mandante_activo');
                $model->setAttribute('mandante_id', is_object($mandante) ? $mandante->id : $mandante);
            }
        });
    }

    public function scopeSinScopeMandante(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ScopeMandanteActivo::class);
    }
}

final class ScopeMandanteActivo implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('tenancy.mandante_activo')) {
            // Antes esto era un `return` mudo: sin contexto, sin filtro, y la
            // consulta devolvía los datos de todos los clientes. Ahora el
            // guardia decide — y en modo estricto no devuelve, lanza.
            GuardiaDeContexto::sinContexto($model::class, 'mandante');

            return;
        }

        $mandante = app('tenancy.mandante_activo');
        $mandanteId = is_object($mandante) ? (int) $mandante->id : (int) $mandante;

        $builder->where($model->getTable().'.mandante_id', $mandanteId);
    }
}
