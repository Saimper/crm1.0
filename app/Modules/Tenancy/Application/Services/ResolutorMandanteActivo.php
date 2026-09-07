<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Decide a qué mandante — la empresa cliente — pertenece la petición en curso.
 *
 * El CRM se construyó con aislamiento a nivel de PROYECTO: hay un binding
 * `tenancy.proyecto_activo` y un global scope que cuelga de él. El mandante
 * llegó después, solo para el SSO, y nunca existió como contexto: por eso las
 * pantallas de administración corren sin tenant y enseñan datos de todos los
 * clientes a la vez.
 *
 * Este servicio es la pieza que faltaba, y es deliberadamente puro: no toca la
 * petición ni la sesión, solo responde preguntas sobre un usuario. Así se puede
 * probar sin HTTP y reutilizar desde comandos y jobs, que es donde el contexto
 * se pierde hoy.
 */
final readonly class ResolutorMandanteActivo
{
    /**
     * Mandantes que este usuario puede llegar a ver, por cualquier vía.
     *
     * ADMIN_GLOBAL los alcanza todos — pero alcanzarlos no es verlos a la vez:
     * quien decide en cuál está trabajando es el mandante activo, uno solo.
     *
     * @return list<int>
     */
    public function permitidos(User $usuario): array
    {
        if ($usuario->esAdminGlobal()) {
            return DB::table('mandantes')
                ->whereNull('eliminada_en')
                ->where('activo', true)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn (mixed $v): int => (int) $v)
                ->all();
        }

        $porRolDeMandante = $usuario->mandantesAdministrados();

        $proyectos = $usuario->proyectosAsignados();

        $porProyecto = $proyectos === []
            ? []
            : DB::table('proyectos')
                ->whereIn('id', $proyectos)
                ->whereNull('eliminada_en')
                ->distinct()
                ->pluck('mandante_id')
                ->map(fn (mixed $v): int => (int) $v)
                ->all();

        $candidatos = array_values(array_unique([...$porRolDeMandante, ...$porProyecto]));

        if ($candidatos === []) {
            return [];
        }

        // El mismo filtro de vida que la rama del admin global. Un mandante dado
        // de baja no se alcanza por tener rol en él: si no, quien lo administraba
        // acaba en un selector que le ofrece un cliente que ya no existe.
        $vivos = DB::table('mandantes')
            ->whereIn('id', $candidatos)
            ->whereNull('eliminada_en')
            ->where('activo', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $v): int => (int) $v)
            ->all();

        sort($vivos);

        return $vivos;
    }

    public function puedeVer(User $usuario, int $mandanteId): bool
    {
        return in_array($mandanteId, $this->permitidos($usuario), true);
    }

    /**
     * El mandante, si el usuario puede verlo y sigue vivo. `null` en cualquier
     * otro caso — quien llama decide si eso es un 403 o un redirect al selector.
     */
    public function resolver(User $usuario, ?int $mandanteId): ?stdClass
    {
        if ($mandanteId === null || $mandanteId <= 0) {
            return null;
        }

        if (! $this->puedeVer($usuario, $mandanteId)) {
            return null;
        }

        $mandante = DB::table('mandantes')
            ->where('id', $mandanteId)
            ->whereNull('eliminada_en')
            ->where('activo', true)
            ->first(['id', 'public_id', 'codigo', 'nombre', 'activo']);

        return $mandante === null ? null : (object) (array) $mandante;
    }

    /**
     * El mandante que corresponde a un proyecto.
     *
     * En las rutas operativas el mandante NO se acepta por separado: se deriva
     * del proyecto de la URL. Aceptar los dos y confiar en que casen es
     * exactamente como se cruzan los tenants.
     */
    public function delProyecto(int $proyectoId): ?int
    {
        $id = DB::table('proyectos')->where('id', $proyectoId)->value('mandante_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Cuando el usuario solo alcanza un mandante, no hay nada que elegir.
     * Evita pedirle una decisión que no existe a un admin de un solo cliente.
     */
    public function unicoPermitido(User $usuario): ?int
    {
        $permitidos = $this->permitidos($usuario);

        return count($permitidos) === 1 ? $permitidos[0] : null;
    }
}
