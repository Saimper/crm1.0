<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use stdClass;

/**
 * Helpers de escenario para tests Feature: sustituye lo que antes hacían los
 * *DemoSeeder borrados. Inserta entidades vía DB::table consistente con los
 * helpers privados que ya existían en cada test multi-tenancy.
 */
trait EscenarioOperativo
{
    protected function crearMandante(?string $codigo = null, ?string $nombre = null): stdClass
    {
        $codigo ??= 'MAND_'.strtoupper(Str::random(6));
        $id = DB::table('mandantes')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'codigo' => $codigo,
            'nombre' => $nombre ?? 'Mandante '.$codigo,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return DB::table('mandantes')->find($id);
    }

    protected function crearProyecto(string $tipoOperacion, ?stdClass $mandante = null, ?string $codigo = null): stdClass
    {
        $mandante ??= $this->crearMandante();
        $codigo ??= strtoupper($tipoOperacion).'_'.Str::random(6);

        $id = DB::table('proyectos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'mandante_id' => $mandante->id,
            'codigo' => $codigo,
            'nombre' => 'Proyecto '.$codigo,
            'tipo_operacion' => $tipoOperacion,
            'activo' => true,
            'sso_secret' => bin2hex(random_bytes(32)),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return DB::table('proyectos')->find($id);
    }

    protected function crearProyectoCobranza(?stdClass $mandante = null): stdClass
    {
        return $this->crearProyecto('cobranza', $mandante);
    }

    protected function crearProyectoCx(?stdClass $mandante = null): stdClass
    {
        return $this->crearProyecto('cx', $mandante);
    }

    protected function crearProyectoVenta(?stdClass $mandante = null): stdClass
    {
        return $this->crearProyecto('venta', $mandante);
    }

    protected function crearProyectoServicio(?stdClass $mandante = null): stdClass
    {
        return $this->crearProyecto('servicio', $mandante);
    }

    protected function crearCarteraEn(stdClass $proyecto, ?string $codigo = null): stdClass
    {
        $codigo ??= 'CART_'.strtoupper(Str::random(6));
        $id = DB::table('carteras')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => 'Cartera '.$codigo,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return DB::table('carteras')->find($id);
    }

    protected function crearEstadoCasoEn(stdClass $proyecto, ?string $codigo = null, bool $esTerminal = false): stdClass
    {
        $codigo ??= 'ESTADO_'.strtoupper(Str::random(6));
        $id = DB::table('estados_caso')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => 'Estado '.$codigo,
            'activo' => true,
            'es_terminal' => $esTerminal,
            'orden' => 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return DB::table('estados_caso')->find($id);
    }

    protected function crearPersonaEn(stdClass $proyecto, ?string $identificacion = null): stdClass
    {
        $tipoIdentId = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $identificacion ??= (string) random_int(1_000_000, 99_999_999);

        $id = DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoIdentId,
            'identificacion' => $identificacion,
            'nombres' => 'Test',
            'apellidos' => 'Persona',
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return DB::table('personas')->find($id);
    }

    protected function crearUsuarioConRol(stdClass $proyecto, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.'.Str::random(8).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);

        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => $rolId,
            'activo' => true,
        ]);

        return $u;
    }

    protected function crearGestor(stdClass $proyecto): User
    {
        return $this->crearUsuarioConRol($proyecto, 'GESTOR');
    }

    protected function crearSupervisor(stdClass $proyecto): User
    {
        return $this->crearUsuarioConRol($proyecto, 'SUPERVISOR');
    }

    protected function crearAuditor(stdClass $proyecto): User
    {
        return $this->crearUsuarioConRol($proyecto, 'AUDITOR');
    }

    protected function crearAdminGlobal(): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => 'Admin Global',
            'email' => 'admin.'.Str::random(8).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);

        $rolId = (int) DB::table('roles')->where('codigo', 'ADMIN_GLOBAL')->value('id');
        DB::table('usuario_global_rol')->insert([
            'usuario_id' => $u->id,
            'rol_id' => $rolId,
        ]);

        return $u;
    }

    protected function activarProyecto(stdClass $proyecto): void
    {
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyecto->id));
    }
}
