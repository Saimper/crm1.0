<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure\Support;

use App\Modules\Tenancy\Domain\Exceptions\EscrituraFueraDelProyectoActivo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Trait para modelos Eloquent que viven bajo el scope de un proyecto activo.
 *
 * Comportamiento:
 *  - Global Scope automático: `where {tabla}.proyecto_id = proyecto_activo`.
 *  - Al crear un modelo nuevo sin `proyecto_id`, lo setea desde el proyecto activo.
 *  - Con un proyecto activo, NINGUNA escritura puede caer en otro proyecto.
 *  - Se puede saltar el scope explícitamente con el scope local `sinScopeProyecto`.
 *
 * Lo segundo y lo tercero no son lo mismo, y confundirlos era el agujero: el
 * global scope sólo toca los SELECT. `$modelo->save()` y `$modelo->delete()`
 * sobre una instancia no pasan por él, así que con el proyecto de A activo se
 * podía insertar una fila dentro del proyecto de B sin más que decir su id.
 * Por eso el guardia de escritura vive en los hooks del modelo y no en el scope.
 */
trait PerteneceAProyecto
{
    protected static function bootPerteneceAProyecto(): void
    {
        static::addGlobalScope(new ScopeProyectoActivo);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('proyecto_id') === null && app()->bound('tenancy.proyecto_activo')) {
                $model->setAttribute('proyecto_id', ContextoDeProyecto::activoId());
            }

            ContextoDeProyecto::exigirQueLaEscrituraCaigaDentro($model, 'crear');
        });

        static::updating(function (Model $model): void {
            ContextoDeProyecto::exigirQueLaEscrituraCaigaDentro($model, 'actualizar');
        });

        static::deleting(function (Model $model): void {
            ContextoDeProyecto::exigirQueLaEscrituraCaigaDentro($model, 'borrar');
        });
    }

    public function scopeSinScopeProyecto(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ScopeProyectoActivo::class);
    }
}

/**
 * El proyecto activo, y qué se puede escribir estando dentro de él.
 *
 * Vive aparte del trait porque lo usan los tres hooks y el scope, y porque la
 * regla que aplica es una sola frase: con un proyecto activo, una escritura que
 * caiga en otro proyecto es un error de programación, no un caso de uso.
 *
 * Sin proyecto activo NO se prohíbe nada. Las tareas de plataforma —los catorce
 * comandos, los jobs, el planificador— escriben cross-proyecto por diseño, casi
 * siempre con `DB::table`, y cerrarles la puerta aquí sólo serviría para que
 * dejaran de funcionar de noche sin que nadie lo viera. Lo que se cierra sin
 * contexto es la LECTURA, que es por donde se fugan los datos de otro cliente.
 */
final class ContextoDeProyecto
{
    public static function hay(): bool
    {
        return app()->bound('tenancy.proyecto_activo');
    }

    public static function activoId(): ?int
    {
        if (! self::hay()) {
            return null;
        }

        $proyecto = app('tenancy.proyecto_activo');

        return is_object($proyecto) ? (int) $proyecto->id : (int) $proyecto;
    }

    /**
     * @throws EscrituraFueraDelProyectoActivo
     */
    public static function exigirQueLaEscrituraCaigaDentro(Model $model, string $accion): void
    {
        $activo = self::activoId();

        if ($activo === null) {
            return;
        }

        $destino = $model->getAttribute('proyecto_id');

        // Al actualizar, importa tanto a dónde va como de dónde venía: cambiar
        // `proyecto_id` de una fila es moverla de cliente.
        $origen = $model->getOriginal('proyecto_id');

        foreach ([$destino, $origen] as $valor) {
            if ($valor !== null && (int) $valor !== $activo) {
                throw EscrituraFueraDelProyectoActivo::para($model::class, $accion, (int) $valor, $activo);
            }
        }
    }
}

final class ScopeProyectoActivo implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('tenancy.proyecto_activo')) {
            // Antes esto era un `return` mudo: sin contexto, sin filtro, y la
            // consulta devolvía los datos de todos los clientes. Ahora el
            // guardia decide — y en modo estricto no devuelve, lanza.
            GuardiaDeContexto::sinContexto($model::class, 'proyecto');

            return;
        }

        $proyecto = app('tenancy.proyecto_activo');
        $proyectoId = is_object($proyecto) ? (int) $proyecto->id : (int) $proyecto;

        $builder->where($model->getTable().'.proyecto_id', $proyectoId);
    }
}
