<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Application\Services;

use App\Models\User;
use App\Modules\Tenancy\Application\Services\ResolutorMandanteActivo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Decide QUÉ eventos de auditoría alcanza un usuario, y lo aplica igual a todas
 * las consultas de la superficie.
 *
 * Existe por una razón concreta: hasta ahora el recorte del listado vivía
 * copiado cuatro veces dentro de `ListadoAuditoria::render()` (registros, tipos
 * de entidad, desplegable de usuarios y modal de detalle) y la exportación
 * tenía otro distinto. Un recorte duplicado es un recorte que se desincroniza:
 * la fuga entra por la copia que nadie tocó. Aquí hay UNA definición y todos
 * los sitios la llaman.
 *
 * La auditoría es el único sitio donde un cliente puede comprobar quién tocó
 * sus datos, así que el default cuando no hay contexto es "nada", no "todo".
 */
final readonly class AlcanceAuditoria
{
    public function __construct(private ResolutorMandanteActivo $resolutor) {}

    /**
     * Mandantes cuyos eventos puede leer este usuario en las pantallas SIN
     * proyecto activo (/admin/auditoria y su exportación).
     *
     * Devuelve:
     *   - `null`  → sin recorte por mandante (solo ADMIN_GLOBAL, y solo cuando
     *               no hay mandante activo ni filtro).
     *   - `[]`    → no alcanza nada. Es el default deliberado de quien llega
     *               sin contexto de cliente: un GESTOR alcanza proyectos, no
     *               mandantes, y no tiene nada que hacer en la auditoría
     *               transversal de su cliente.
     *   - lista   → los ids concretos.
     *
     * @param  int|null  $mandanteFiltro  El filtro elegido en pantalla. Sólo puede
     *                                    ESTRECHAR el alcance: nunca ampliarlo. Por eso
     *                                    esa propiedad puede venir del cliente sin
     *                                    #[Locked] — se interseca, no se obedece.
     * @param  string  $permiso  Qué se va a hacer con esos eventos: mirarlos en
     *                           pantalla (`auditoria.ver`) o SACARLOS del sistema
     *                           (`auditoria.exportar`). No es el mismo alcance, y
     *                           tratarlo como si lo fuera es justo la fuga que
     *                           dejaba a un SUPERVISOR descargarse el CSV.
     * @return list<int>|null
     */
    public function mandantesLegibles(User $usuario, ?int $mandanteFiltro = null, string $permiso = 'auditoria.ver'): ?array
    {
        $base = $this->baseDeMandantes($usuario, $permiso);

        if ($mandanteFiltro === null || $mandanteFiltro <= 0) {
            return $base;
        }

        // ADMIN_GLOBAL sin recorte previo: el filtro vale si de verdad alcanza
        // ese mandante (el resolutor descarta los eliminados/inactivos).
        if ($base === null) {
            return $this->resolutor->puedeVer($usuario, $mandanteFiltro) ? [$mandanteFiltro] : [];
        }

        return in_array($mandanteFiltro, $base, true) ? [$mandanteFiltro] : [];
    }

    /**
     * El mandante activo de la petición, si el middleware `mandante.activo` lo
     * publicó. En los tests de componente y en los comandos no hay binding: ahí
     * el alcance se calcula solo con los roles del usuario.
     */
    public function mandanteActivoId(): ?int
    {
        if (! app()->bound('tenancy.mandante_activo')) {
            return null;
        }

        $mandante = app('tenancy.mandante_activo');

        return is_object($mandante) ? (int) $mandante->id : (int) $mandante;
    }

    /**
     * Recorta una consulta sobre `auditorias` al alcance dado.
     *
     * @param  string  $alias  Alias de la tabla `auditorias` en esa consulta
     *                         (`a` en el listado, `auditorias` cuando va suelta).
     * @param  list<int>|null  $mandantes  Lo que devolvió mandantesLegibles().
     */
    public function aplicarAMandantes(Builder $q, string $alias, ?array $mandantes): void
    {
        if ($mandantes === null) {
            return; // Sin recorte: ADMIN_GLOBAL mirando todos los clientes a la vez.
        }

        if ($mandantes === []) {
            $q->whereRaw('1 = 0');

            return;
        }

        $q->where(function (Builder $w) use ($alias, $mandantes): void {
            // 1 · Atribución directa. La columna `mandante_id` es lo que permite
            //     que un evento tenga dueño aunque no tenga proyecto.
            $w->whereIn($alias.'.mandante_id', $mandantes);

            // 2 · Filas anteriores a esa columna (o escritas a pelo por DB::table):
            //     el mandante se deduce del proyecto, como se hacía antes.
            $w->orWhere(function (Builder $x) use ($alias, $mandantes): void {
                $x->whereNull($alias.'.mandante_id')
                    ->whereIn($alias.'.proyecto_id', function (Builder $sub) use ($mandantes): void {
                        $sub->from('proyectos')->select('id')->whereIn('mandante_id', $mandantes);
                    });
            });

            // 3 · Acciones administrativas SIN proyecto (alta de usuario, cambio
            //     de rol...). El filtro viejo era `whereIn(proyecto_id, ...)`, que
            //     descarta los NULL: el propio admin del mandante perdía su rastro
            //     y sólo ADMIN_GLOBAL lo veía. Un evento huérfano pertenece al
            //     cliente de quien lo hizo, así que se atribuye por el actor.
            $w->orWhere(function (Builder $x) use ($alias, $mandantes): void {
                $x->whereNull($alias.'.mandante_id')
                    ->whereNull($alias.'.proyecto_id')
                    ->whereIn($alias.'.usuario_id', function (Builder $sub) use ($mandantes): void {
                        $sub->from('usuario_mandante_rol')
                            ->select('usuario_id')
                            ->whereIn('mandante_id', $mandantes)
                            ->where('activo', true);
                    });
            });
        });
    }

    /**
     * Recorta al proyecto activo, comprobando el permiso EN ESE PROYECTO.
     *
     * Defensa en profundidad: hoy el binding `tenancy.proyecto_activo` sólo lo
     * crea ResolverProyectoActivo, que valida el acceso antes de bindear. Pero
     * el componente servía `where proyecto_id = <activo>` sin preguntar nunca
     * por `auditoria.ver`, así que el aislamiento del historial completo de un
     * cliente descansaba entero en un middleware de otro módulo.
     */
    public function aplicarAProyecto(Builder $q, string $alias, ?User $usuario, int $proyectoId): void
    {
        if ($usuario === null || ! $usuario->tienePermiso('auditoria.ver', $proyectoId)) {
            $q->whereRaw('1 = 0');

            return;
        }

        $q->where($alias.'.proyecto_id', $proyectoId);
    }

    /**
     * Proyectos de los mandantes dados. Se usa para el recorte del export
     * global, que además cruza el permiso proyecto a proyecto.
     *
     * NO se filtran los proyectos dados de baja (`eliminada_en`) a propósito:
     * la pantalla SÍ enseña los eventos de un proyecto cerrado —el rastro de lo
     * que se hizo con los datos de un cliente no desaparece cuando se archiva
     * el proyecto—, y si el export los descartara volvería a pasar lo mismo que
     * arreglamos: que lo que el admin VE no coincide con lo que puede DESCARGAR.
     *
     * @param  list<int>  $mandantes
     * @return list<int>
     */
    public function proyectosDeMandantes(array $mandantes): array
    {
        if ($mandantes === []) {
            return [];
        }

        return DB::table('proyectos')
            ->whereIn('mandante_id', $mandantes)
            ->pluck('id')
            ->map(fn (mixed $v): int => (int) $v)
            ->all();
    }

    /**
     * Mandantes que el usuario puede ELEGIR en el desplegable de la pantalla.
     * Nunca más que los que ya alcanza: el select no es una fuente de permisos.
     *
     * @return list<int>
     */
    public function mandantesElegibles(User $usuario): array
    {
        $base = $this->baseDeMandantes($usuario);

        return $base ?? $this->resolutor->permitidos($usuario);
    }

    /**
     * ¿Este usuario puede sacar del sistema algo del alcance en el que está?
     *
     * Se pregunta para decidir si se pinta el botón de exportar. Un botón que
     * siempre lleva a un 403 no es una protección, es una trampa: quien lo ve
     * asume que puede, y quien audita el sistema no distingue el intento
     * legítimo del ataque.
     */
    public function puedeExportar(User $usuario, ?int $mandanteFiltro = null): bool
    {
        $mandantes = $this->mandantesLegibles($usuario, $mandanteFiltro, 'auditoria.exportar');

        return $mandantes === null || $mandantes !== [];
    }

    /**
     * El alcance antes de aplicar el filtro de pantalla.
     *
     * ADMIN_GLOBAL no queda CAPADO por el mandante activo — audita de verdad
     * cross-cliente y necesita poder hacerlo — pero la pantalla se abre con el
     * cliente activo preseleccionado (ver ListadoAuditoria::mount()).
     *
     * Para todos los demás el mandante activo sí es un techo: se interseca con
     * los mandantes en los que su rol trae el permiso, nunca se suma.
     *
     * @return list<int>|null
     */
    private function baseDeMandantes(User $usuario, string $permiso = 'auditoria.ver'): ?array
    {
        if ($usuario->esAdminGlobal()) {
            return null;
        }

        $conPermiso = $this->mandantesConPermiso($usuario, $permiso);

        $activo = $this->mandanteActivoId();

        if ($activo === null) {
            return $conPermiso;
        }

        return array_values(array_intersect($conPermiso, [$activo]));
    }

    /**
     * Mandantes en los que el ROL DE MANDANTE del usuario trae ese permiso.
     *
     * Es la comprobación que la pantalla no hacía nunca en modo global: se
     * apoyaba en `mandantesAdministrados()`, que sólo dice "tiene algún rol de
     * mandante aquí" y no "ese rol le deja leer la auditoría". Con un rol de
     * mandante que no fuera ADMIN_MANDANTE, o el día que a ADMIN_MANDANTE se le
     * quite `auditoria.ver`, la pantalla habría seguido abriéndose: el
     * aislamiento del historial completo de un cliente descansaba entero en un
     * middleware de otro módulo.
     *
     * Es el equivalente a nivel de mandante de la rama F38 de
     * `User::tienePermiso()`, sin el salto a `proyectos`: un cliente sin
     * proyectos sigue teniendo eventos administrativos que auditar, y exigir
     * que exista un proyecto para leerlos volvería a dejarlos huérfanos.
     *
     * Y a propósito NO se usa `ResolutorMandanteActivo::permitidos()`, que
     * incluye los mandantes alcanzados por tener un rol en algún PROYECTO:
     * eso convertiría a cualquier gestor en lector de la auditoría transversal
     * de su cliente.
     *
     * @return list<int>
     */
    private function mandantesConPermiso(User $usuario, string $permiso): array
    {
        return DB::table('usuario_mandante_rol as umr')
            ->join('roles as r', 'r.id', '=', 'umr.rol_id')
            ->join('rol_permiso as rp', 'rp.rol_id', '=', 'umr.rol_id')
            ->join('permisos as p', 'p.id', '=', 'rp.permiso_id')
            ->where('umr.usuario_id', $usuario->id)
            ->where('umr.activo', true)
            ->where('r.activo', true)
            ->where('p.codigo', $permiso)
            ->where('p.activo', true)
            ->distinct()
            ->pluck('umr.mandante_id')
            ->map(fn (mixed $v): int => (int) $v)
            ->values()
            ->all();
    }
}
