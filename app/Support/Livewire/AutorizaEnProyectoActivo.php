<?php

declare(strict_types=1);

namespace App\Support\Livewire;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

/**
 * Las tres guardas que necesita cualquier componente Livewire que escriba.
 *
 * El `can:` de la ruta protege la PÁGINA, no el commit: cada acción de Livewire
 * es un POST aparte a /livewire/update que vuelve a entrar en el componente con
 * las propiedades que mande el cliente, sin volver a pasar por el middleware de
 * la ruta. Un componente montado legítimamente y luego reapuntado a otro id es
 * el camino por el que se cruzan datos entre mandantes.
 *
 * De ahí que haya que comprobar dos cosas distintas en cada método que escribe:
 * que el usuario PUEDA hacer eso (`autorizarEn`), y que la fila sobre la que lo
 * hace SEA de su proyecto (`filaDelProyecto`). Una sin la otra no basta: el
 * permiso es por proyecto, así que un supervisor con permiso en el suyo lo
 * arrastraría al ajeno si nadie comprueba la pertenencia.
 */
trait AutorizaEnProyectoActivo
{
    use AuthorizesRequests;

    /**
     * El proyecto activo, o 403 si no hay ninguno.
     *
     * Sin la guarda, un componente montado fuera de una ruta con
     * `proyecto.activo` revienta con BindingResolutionException: un 500 con
     * traza, que además del ruido dice más de la cuenta a quien sondea
     * /livewire/update. Aquí la ausencia de contexto es exactamente lo que
     * significa —no se puede autorizar— y se responde como tal.
     */
    protected function proyectoActivoId(): int
    {
        abort_unless(app()->bound('tenancy.proyecto_activo'), 403, 'Sin proyecto activo.');

        return (int) app('tenancy.proyecto_activo')->id;
    }

    /**
     * Autoriza el permiso contra el proyecto activo. Lanza 403 si no lo tiene.
     *
     * Se llama al principio del método que escribe, no en `mount()`: mount()
     * corre una vez y el commit puede llegar mucho después, con otra sesión y
     * otro estado.
     */
    protected function autorizarEn(string $permiso): void
    {
        $this->authorize($permiso, $this->proyectoActivoId());
    }

    /**
     * Devuelve la fila sólo si pertenece al proyecto activo; si no, 404.
     *
     * 404 y no 403 a propósito: un 403 sobre un id ajeno confirma que ese id
     * existe, y con ids secuenciales eso deja enumerar la cartera de otro
     * cliente sin llegar a leerla.
     *
     * @param  string  $tabla  tabla con columna `proyecto_id`
     */
    protected function filaDelProyecto(string $tabla, int $id, string $columnaId = 'id'): object
    {
        $fila = DB::table($tabla)
            ->where($columnaId, $id)
            ->where('proyecto_id', $this->proyectoActivoId())
            ->when(
                $this->tieneColumna($tabla, 'eliminada_en'),
                fn (Builder $q): Builder => $q->whereNull('eliminada_en')
            )
            ->first();

        abort_if($fila === null, 404);

        return $fila;
    }

    /** Comprueba la pertenencia sin traerse la fila. */
    protected function exigirDelProyecto(string $tabla, int $id, string $columnaId = 'id'): void
    {
        $this->filaDelProyecto($tabla, $id, $columnaId);
    }

    private function tieneColumna(string $tabla, string $columna): bool
    {
        /** @var array<string, array<int, string>> $cache */
        static $cache = [];

        $cache[$tabla] ??= DB::getSchemaBuilder()->getColumnListing($tabla);

        return in_array($columna, $cache[$tabla], true);
    }
}
