<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Infrastructure\Http\Livewire;

use App\Models\User;
use App\Modules\Auditoria\Application\Services\AlcanceAuditoria;
use App\Modules\Auditoria\Application\Services\FiltrosAuditoria;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lista paginada de eventos de auditoría.
 *
 * Corre en dos modos:
 *  - dentro de un proyecto (`/proyectos/{id}/auditoria`), acotada a él y
 *    exigiendo `auditoria.ver` EN ESE proyecto;
 *  - sin proyecto (`/admin/auditoria`), acotada al MANDANTE — el cliente —
 *    activo. Antes este segundo modo corría sin contexto de tenant, que es de
 *    donde salían las fugas de /admin.
 *
 * Todo el recorte vive en `AlcanceAuditoria`: registros, tipos de entidad,
 * desplegable de usuarios, modal de detalle y la exportación llaman al MISMO
 * sitio. Cuando cada consulta se recortaba por su cuenta, la fuga entraba por
 * la copia que nadie tocó.
 */
final class ListadoAuditoria extends Component
{
    use WithPagination;

    public string $entidadTipo = '';

    public ?int $usuarioId = null;

    public string $evento = '';

    public string $desde = '';

    public string $hasta = '';

    /**
     * Filtro por cliente. NO lleva #[Locked] a propósito: es un filtro que el
     * usuario cambia desde el desplegable, no un identificador que gobierne una
     * escritura. Lo que lo hace seguro es que `AlcanceAuditoria` lo INTERSECA
     * con lo que el usuario ya alcanzaba: sólo puede estrechar, nunca ampliar.
     */
    public ?int $mandanteId = null;

    /**
     * El evento cuyo detalle está abierto. #[Locked] porque sí es un
     * identificador: sin esto lo fija quien manda el payload, y el modal sirve
     * `datos_antes`/`datos_despues` completos. Además `verDetalle()` revalida.
     */
    #[Locked]
    public ?int $detalleId = null;

    public function mount(): void
    {
        // La pantalla se abre DENTRO del cliente en el que se está trabajando.
        // Para el admin de mandante es además su techo; para ADMIN_GLOBAL es
        // sólo el valor por defecto — auditar cross-cliente es parte de su
        // trabajo y puede volver a "todos" desde el desplegable.
        $this->mandanteId = $this->alcance()->mandanteActivoId();
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['entidadTipo', 'usuarioId', 'evento', 'desde', 'hasta']);
        // `mandanteId` NO se limpia aquí: no es un filtro más, es el contexto de
        // tenant de la pantalla. Volver a "todos" es una acción explícita del
        // desplegable, y para quien no es ADMIN_GLOBAL ni siquiera cambia nada.
        $this->resetPage();
    }

    public function verDetalle(int $id): void
    {
        // Regla del patrón: la acción revalida el id que RECIBE. Que render()
        // recorte no protege nada — el id llega del cliente, y este modal es la
        // superficie que enseña el snapshot completo de la fila auditada.
        $this->detalleId = $this->esEventoVisible($id) ? $id : null;
    }

    public function cerrarDetalle(): void
    {
        $this->detalleId = null;
    }

    public function render(): View
    {
        $usuario = $this->usuario();
        $modoGlobal = ! app()->bound('tenancy.proyecto_activo');
        $proyectoId = $modoGlobal ? null : (int) app('tenancy.proyecto_activo')->id;

        $mandantes = $modoGlobal && $usuario !== null
            ? $this->alcance()->mandantesLegibles($usuario, $this->mandanteId)
            : [];

        $q = DB::table('auditorias as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.usuario_id')
            ->leftJoin('proyectos as p', 'p.id', '=', 'a.proyecto_id')
            ->select([
                'a.id', 'a.entidad_tipo', 'a.entidad_id', 'a.evento',
                'a.ip', 'a.creada_en', 'a.proyecto_id',
                'u.name as usuario_nombre',
                'p.codigo as proyecto_codigo', 'p.nombre as proyecto_nombre',
            ]);

        $this->recortar($q, 'a', $usuario, $modoGlobal, $proyectoId, $mandantes);

        // Los mismos filtros que aplican las dos exportaciones: lo que se ve y
        // lo que se descarga no pueden divergir.
        $this->filtros()->aplicar($q, 'a');

        $registros = $q->orderByDesc('a.creada_en')->paginate(25);

        $tiposQ = DB::table('auditorias as a');
        $this->recortar($tiposQ, 'a', $usuario, $modoGlobal, $proyectoId, $mandantes);
        $tiposEntidad = $tiposQ->distinct()->orderBy('a.entidad_tipo')
            ->pluck('a.entidad_tipo')->all();

        $usuariosQ = DB::table('auditorias as a')
            ->join('users as u', 'u.id', '=', 'a.usuario_id');
        $this->recortar($usuariosQ, 'a', $usuario, $modoGlobal, $proyectoId, $mandantes);
        $usuarios = $usuariosQ->distinct()
            ->select(['u.id', 'u.name'])
            ->orderBy('u.name')
            ->get();

        $detalle = null;
        if ($this->detalleId !== null) {
            // El recorte se vuelve a aplicar aquí aunque verDetalle() ya validó:
            // entre una petición y otra al usuario le pueden haber quitado el
            // acceso, y `detalleId` sobrevive en el snapshot del componente.
            $detalleQ = DB::table('auditorias as a')->where('a.id', $this->detalleId);
            $this->recortar($detalleQ, 'a', $usuario, $modoGlobal, $proyectoId, $mandantes);
            $detalle = $detalleQ->first();
        }

        return view('auditoria::livewire.listado-auditoria', [
            'registros' => $registros,
            'tiposEntidad' => $tiposEntidad,
            'usuarios' => $usuarios,
            'detalle' => $detalle,
            'modoGlobal' => $modoGlobal,
            'mandantesElegibles' => $modoGlobal && $usuario !== null
                ? $this->mandantesElegibles($usuario)
                : [],
            // El botón de exportar sólo se pinta a quien de verdad puede: ver y
            // sacar del sistema son permisos distintos, y un botón que siempre
            // acaba en 403 hace creer que el permiso existe.
            'puedeExportar' => $modoGlobal && $usuario !== null
                && $this->alcance()->puedeExportar($usuario, $this->mandanteId),
        ]);
    }

    /**
     * El recorte de tenant, idéntico para las cuatro consultas de la pantalla.
     *
     * @param  list<int>|null  $mandantes
     */
    private function recortar(
        Builder $q,
        string $alias,
        ?User $usuario,
        bool $modoGlobal,
        ?int $proyectoId,
        ?array $mandantes,
    ): void {
        if ($usuario === null) {
            $q->whereRaw('1 = 0');

            return;
        }

        if ($modoGlobal) {
            $this->alcance()->aplicarAMandantes($q, $alias, $mandantes);

            return;
        }

        $this->alcance()->aplicarAProyecto($q, $alias, $usuario, (int) $proyectoId);
    }

    /** Filtros de pantalla. Sólo estrechan; el recorte de tenant ya se aplicó. */
    private function filtros(): FiltrosAuditoria
    {
        return new FiltrosAuditoria(
            entidadTipo: $this->entidadTipo,
            usuarioId: $this->usuarioId,
            evento: $this->evento,
            desde: $this->desde,
            hasta: $this->hasta,
        );
    }

    /** ¿Este id cae dentro de lo que el usuario alcanza AHORA MISMO? */
    private function esEventoVisible(int $id): bool
    {
        $usuario = $this->usuario();
        $modoGlobal = ! app()->bound('tenancy.proyecto_activo');
        $proyectoId = $modoGlobal ? null : (int) app('tenancy.proyecto_activo')->id;

        $mandantes = $modoGlobal && $usuario !== null
            ? $this->alcance()->mandantesLegibles($usuario, $this->mandanteId)
            : [];

        $q = DB::table('auditorias as a')->where('a.id', $id);
        $this->recortar($q, 'a', $usuario, $modoGlobal, $proyectoId, $mandantes);

        return $q->exists();
    }

    /**
     * Clientes que se ofrecen en el desplegable. Sólo se pinta cuando hay más
     * de uno: a un admin de un solo cliente no se le pide una decisión que no
     * existe, y no se le enseña un nombre de cliente que no es suyo.
     *
     * @return list<object{id: int, codigo: string, nombre: string}>
     */
    private function mandantesElegibles(User $usuario): array
    {
        $ids = $this->alcance()->mandantesElegibles($usuario);

        if (count($ids) < 2) {
            return [];
        }

        /** @var list<object{id: int, codigo: string, nombre: string}> $filas */
        $filas = DB::table('mandantes')
            ->whereIn('id', $ids)
            ->whereNull('eliminada_en')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre'])
            ->all();

        return $filas;
    }

    private function usuario(): ?User
    {
        $usuario = auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }

    private function alcance(): AlcanceAuditoria
    {
        return app(AlcanceAuditoria::class);
    }
}
