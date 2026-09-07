<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use stdClass;

/**
 * Monta DOS mandantes completos y ajenos entre sí, para poder afirmar lo único
 * que de verdad importa del aislamiento: que estando dentro de uno no se ve
 * absolutamente nada del otro.
 *
 * El aislamiento del CRM se construyó a nivel de PROYECTO — hay un binding
 * `tenancy.proyecto_activo` y un global scope que cuelga de él — y el MANDANTE
 * (la empresa cliente) llegó después, sólo para el SSO. No existe ningún
 * `mandante_activo`, así que todas las pantallas de administración corren sin
 * contexto de tenant y muestran datos de todos los clientes a la vez.
 *
 * Estos tests son la red que tiene que existir ANTES de tocar nada. Los que
 * comprueban pantallas de administración están pensados para FALLAR hoy: cada
 * fallo es una fuga concreta con nombre y apellido. Si alguno pasa en verde
 * desde el principio, sospecha del test antes que del código.
 */
trait EscenarioMultiMandante
{
    use EscenarioOperativo;

    /**
     * Un mandante con todo lo que lo hace reconocible: proyecto, cartera,
     * persona, caso, campo personalizado y sus tres perfiles de usuario.
     *
     * @return array<string, mixed>
     */
    protected function montarMandanteCompleto(string $etiqueta): array
    {
        $mandante = $this->crearMandante('MND_'.strtoupper($etiqueta));
        $proyecto = $this->crearProyectoCobranza($mandante);
        $cartera = $this->crearCarteraEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'ABIERTO_'.strtoupper($etiqueta));

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => '2026-09-01',
        ]);

        $campoId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $cartera->id,
            'codigo' => 'CAMPO_'.strtoupper($etiqueta),
            'nombre' => 'Campo de '.$etiqueta,
            'tipo' => 'texto_corto',
            'obligatorio' => false,
            'activo' => true,
        ]);

        return [
            'mandante' => $mandante,
            'proyecto' => $proyecto,
            'cartera' => $cartera,
            'persona' => $persona,
            'casoId' => $casoId,
            'campoId' => $campoId,
            'adminMandante' => $this->crearAdminDeMandante($mandante, $etiqueta),
            'supervisor' => $this->crearSupervisor($proyecto),
            'gestor' => $this->crearGestor($proyecto),
        ];
    }

    /**
     * Los dos mandantes de la prueba. `a` es desde donde se mira; `b` es lo que
     * no debería verse nunca.
     *
     * @return array{a: array<string, mixed>, b: array<string, mixed>}
     */
    protected function montarDosMandantes(): array
    {
        return [
            'a' => $this->montarMandanteCompleto('alfa'),
            'b' => $this->montarMandanteCompleto('beta'),
        ];
    }

    /**
     * ADMIN_MANDANTE: administra todos los proyectos de su mandante sin pivot
     * por proyecto (F38). Vivía duplicado como método privado en varios tests.
     */
    protected function crearAdminDeMandante(stdClass $mandante, string $etiqueta = ''): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => 'Admin '.($etiqueta !== '' ? $etiqueta : 'mandante'),
            'email' => 'admin.'.strtolower($etiqueta !== '' ? $etiqueta : 'm').'.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);

        DB::table('usuario_mandante_rol')->insert([
            'usuario_id' => $u->id,
            'mandante_id' => $mandante->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'ADMIN_MANDANTE')->value('id'),
            'activo' => true,
        ]);

        return $u;
    }

    /**
     * Afirma que ningún rastro del mandante ajeno aparece en lo que se rindió.
     *
     * Compara contra los identificadores Y los códigos: una pantalla puede
     * filtrar bien los ids y seguir filtrando mal por nombre en el buscador.
     *
     * @param  array<string, mixed>  $ajeno
     */
    protected function assertNoSeFiltra(string $render, array $ajeno, string $donde): void
    {
        $rastros = [
            'código del mandante' => $ajeno['mandante']->codigo,
            'código del proyecto' => $ajeno['proyecto']->codigo,
            'código de la cartera' => $ajeno['cartera']->codigo ?? null,
            'correo del gestor' => $ajeno['gestor']->email,
            'correo del supervisor' => $ajeno['supervisor']->email,
            'correo del admin' => $ajeno['adminMandante']->email,
        ];

        foreach ($rastros as $que => $rastro) {
            if ($rastro === null || $rastro === '') {
                continue;
            }

            $this->assertStringNotContainsString(
                (string) $rastro,
                $render,
                "{$donde}: se filtró el {$que} del otro mandante ({$rastro})."
            );
        }
    }

    /**
     * Los ids que una consulta devolvió, para contrastarlos con los del ajeno.
     *
     * @param  iterable<mixed>  $filas
     * @return list<int>
     */
    protected function idsDe(iterable $filas): array
    {
        return (new Collection($filas))
            ->map(fn ($f): int => (int) (is_object($f) ? $f->id : $f['id']))
            ->values()
            ->all();
    }
}
