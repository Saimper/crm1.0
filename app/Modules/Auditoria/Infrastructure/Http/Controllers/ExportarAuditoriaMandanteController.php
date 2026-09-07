<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Infrastructure\Http\Controllers;

use App\Models\User;
use App\Modules\Auditoria\Application\Services\AlcanceAuditoria;
use App\Modules\Auditoria\Application\Services\ExportadorCsvAuditoria;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta lo que /admin/auditoria ENSEÑA, con el mismo recorte.
 *
 * Antes la única exportación era por `proyecto_id` suelto, así que el admin de
 * un mandante veía en pantalla el historial de todo su cliente —proyectos más
 * acciones administrativas sin proyecto— y no podía descargarlo: tenía que ir
 * proyecto por proyecto, y los eventos huérfanos no salían por ningún lado.
 *
 * El alcance sale de `AlcanceAuditoria`, el mismo servicio que usa la pantalla,
 * pero calculado con `auditoria.exportar` en vez de `auditoria.ver`: ver el
 * historial de un cliente y poder sacarlo del sistema son dos cosas distintas.
 * El permiso se exige DOS veces y por dos vías que no se solapan:
 *
 *  - en el MANDANTE, porque los eventos administrativos sin proyecto no cuelgan
 *    de ninguno y ningún cruce por proyecto los filtraría;
 *  - y PROYECTO A PROYECTO para todo lo demás.
 */
final class ExportarAuditoriaMandanteController
{
    public function __construct(
        private readonly AlcanceAuditoria $alcance,
        private readonly ExportadorCsvAuditoria $exportador,
    ) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        // Mismo default que la pantalla: el cliente activo. Un `mandante_id`
        // por query string no amplía nada — `mandantesLegibles()` lo interseca
        // con lo que el usuario ya alcanzaba.
        $filtro = $this->escalar($request, 'mandante_id');
        $mandanteFiltro = $filtro === '' ? $this->alcance->mandanteActivoId() : (int) $filtro;

        // `auditoria.exportar`, no `auditoria.ver`: el alcance de lo que se
        // puede SACAR del sistema se calcula con el permiso de sacarlo. Sin
        // esto, un rol de mandante con sólo lectura llegaba hasta abajo y se
        // llevaba al menos los eventos administrativos sin proyecto, que son
        // los que ningún cruce por proyecto puede filtrar.
        $mandantes = $this->alcance->mandantesLegibles($usuario, $mandanteFiltro, 'auditoria.exportar');

        abort_if($mandantes === [], 403, 'No tienes auditoría de ningún cliente que exportar.');

        $exportables = $this->proyectosExportables($usuario, $mandantes);

        $q = DB::table('auditorias as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.usuario_id')
            ->select($this->exportador->columnas())
            ->orderByDesc('a.creada_en');

        // 1 · Recorte de tenant, idéntico al de la pantalla.
        $this->alcance->aplicarAMandantes($q, 'a', $mandantes);

        // 2 · Y encima, el permiso por proyecto. Un evento sin proyecto (acción
        //     administrativa del cliente) sale con el resto: no pertenece a
        //     ningún proyecto sobre el que cruzar el permiso, sino al mandante,
        //     que ya se comprobó arriba.
        if ($exportables !== null) {
            $q->where(function (Builder $w) use ($exportables): void {
                $w->whereNull('a.proyecto_id')->orWhereIn('a.proyecto_id', $exportables);
            });
        }

        $this->aplicarFiltros($request, $q);

        return $this->exportador->responder($q, $this->nombreFichero($mandantes));
    }

    /**
     * Proyectos del alcance sobre los que este usuario puede exportar.
     * `null` = sin recorte por proyecto (ADMIN_GLOBAL sin cliente activo).
     *
     * @param  list<int>|null  $mandantes
     * @return list<int>|null
     */
    private function proyectosExportables(User $usuario, ?array $mandantes): ?array
    {
        if ($mandantes === null) {
            return null;
        }

        $delMandante = $this->alcance->proyectosDeMandantes($mandantes);

        if ($delMandante === []) {
            // Cliente sin proyectos: no hay nada que cruzar. Llegar hasta aquí
            // ya exigió `auditoria.exportar` en el propio mandante, así que los
            // eventos administrativos sin proyecto salen con ese permiso y no
            // con ninguno heredado.
            return [];
        }

        $exportables = array_values(array_filter(
            $delMandante,
            static fn (int $proyectoId): bool => $usuario->tienePermiso('auditoria.exportar', $proyectoId),
        ));

        abort_if(
            $exportables === [],
            403,
            'No tienes permiso para exportar la auditoría de este cliente.',
        );

        return $exportables;
    }

    private function aplicarFiltros(Request $request, Builder $q): void
    {
        $entidadTipo = $this->escalar($request, 'entidad_tipo');
        $usuarioId = $this->escalar($request, 'usuario_id');
        $evento = $this->escalar($request, 'evento');
        $desde = $this->escalar($request, 'desde');
        $hasta = $this->escalar($request, 'hasta');

        if ($entidadTipo !== '') {
            $q->where('a.entidad_tipo', $entidadTipo);
        }
        if ($usuarioId !== '') {
            $q->where('a.usuario_id', (int) $usuarioId);
        }
        if ($evento !== '') {
            $q->where('a.evento', $evento);
        }
        if ($desde !== '') {
            $q->where('a.creada_en', '>=', $desde.' 00:00:00');
        }
        if ($hasta !== '') {
            $q->where('a.creada_en', '<=', $hasta.' 23:59:59');
        }
    }

    /**
     * Un parámetro de query string es lo que quiera quien hace la petición:
     * `?usuario_id[]=1` llega como array, y castearlo a int o a string da 1 o
     * "Array" con un warning. Cualquier cosa que no sea un escalar se trata
     * como "no me han pasado filtro", que es el único valor seguro.
     */
    private function escalar(Request $request, string $clave): string
    {
        $valor = $request->query($clave);

        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    /** @param  list<int>|null  $mandantes */
    private function nombreFichero(?array $mandantes): string
    {
        $sufijo = 'global';

        if (is_array($mandantes) && count($mandantes) === 1) {
            $codigo = DB::table('mandantes')->where('id', $mandantes[0])->value('codigo');
            if ($codigo !== null) {
                $sufijo = (string) $codigo;
            }
        }

        return 'auditoria_'.$sufijo.'_'.now()->format('Ymd_His').'.csv';
    }
}
