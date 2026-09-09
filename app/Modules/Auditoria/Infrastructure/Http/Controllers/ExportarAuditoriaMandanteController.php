<?php

declare(strict_types=1);

namespace App\Modules\Auditoria\Infrastructure\Http\Controllers;

use App\Models\User;
use App\Modules\Auditoria\Application\Services\AlcanceAuditoria;
use App\Modules\Auditoria\Application\Services\ExportadorCsvAuditoria;
use App\Modules\Auditoria\Application\Services\FiltrosAuditoria;
use App\Support\Http\ParametroDeConsulta;
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
        $mandanteFiltro = ParametroDeConsulta::entero($request, 'mandante_id') ?? $this->alcance->mandanteActivoId();

        // `auditoria.exportar`, no `auditoria.ver`: el alcance de lo que se
        // puede SACAR del sistema se calcula con el permiso de sacarlo. Sin
        // esto, un rol de mandante con sólo lectura llegaba hasta abajo y se
        // llevaba al menos los eventos administrativos sin proyecto, que son
        // los que ningún cruce por proyecto puede filtrar.
        $mandantes = $this->alcance->mandantesLegibles($usuario, $mandanteFiltro, 'auditoria.exportar');

        abort_if($mandantes === [], 403, 'No tienes auditoría de ningún cliente que exportar.');

        $exportables = $this->proyectosExportables($usuario, $mandantes);

        $filtros = FiltrosAuditoria::desdeQueryString($request);

        $q = DB::table('auditorias as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.usuario_id')
            ->select($this->exportador->columnas());

        // 1 · Recorte de tenant, idéntico al de la pantalla. Va PRIMERO y no
        //     depende de ningún parámetro: los filtros sólo estrechan.
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

        $filtros->aplicar($q, 'a');

        return $this->exportador->responder(
            $q,
            $this->nombreFichero($mandantes),
            $filtros,
            proyectoId: null,
            // La huella se atribuye a un cliente sólo cuando la descarga es de
            // uno: un CSV de varios mandantes no es de ninguno en concreto.
            mandanteId: is_array($mandantes) && count($mandantes) === 1 ? $mandantes[0] : null,
        );
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
