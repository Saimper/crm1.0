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
            'sso_secret' => bin2hex(random_bytes(32)),
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
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        // Los canales del proyecto: en la app los siembra un listener sobre
        // ProyectoCreado, y este helper inserta con DB::table sin disparar el
        // evento. Sin esto un proyecto de test nace sin canales y no se puede
        // registrar una gestión, que es donde empieza la cascada.
        $ahora = Carbon::now();
        $canales = DB::table('canales')->where('activo', true)->orderBy('orden')->get(['id', 'orden']);

        if ($canales->isNotEmpty()) {
            DB::table('canal_proyecto')->insert($canales->map(fn (stdClass $c): array => [
                'proyecto_id' => $id,
                'canal_id' => $c->id,
                'etiqueta' => null,
                'activo' => true,
                'orden' => (int) $c->orden,
                'requiere_duracion' => false,
                'permite_adjunto' => false,
                'creada_en' => $ahora,
                'actualizada_en' => $ahora,
            ])->all());
        }

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

    /**
     * Un caso del proyecto, creando por el camino lo que le falte (cartera,
     * persona, estado). Devuelve el id porque es lo que piden las tablas CTI y
     * los UseCases; el resto del escenario se pide aparte si hace falta.
     *
     * @param  array{cartera?: stdClass, persona?: stdClass, estado?: stdClass, tipo_caso?: string, fecha_ingreso?: string, prioridad?: int}  $opciones
     */
    protected function crearCasoEn(stdClass $proyecto, array $opciones = []): int
    {
        $cartera = $opciones['cartera'] ?? $this->crearCarteraEn($proyecto);
        $persona = $opciones['persona'] ?? $this->crearPersonaEn($proyecto);
        $estado = $opciones['estado'] ?? $this->crearEstadoCasoEn($proyecto, 'ABIERTO_'.strtoupper(Str::random(4)));

        return (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => $opciones['tipo_caso'] ?? $this->tipoCasoDe($proyecto),
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => $opciones['fecha_ingreso'] ?? Carbon::now()->toDateString(),
            'prioridad' => $opciones['prioridad'] ?? 100,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    /**
     * La cascada completa que exige registrar una gestión: tipo, resultado y la
     * fila del pivote que los declara compatibles.
     *
     * Sin la fila de `resultado_tipo_gestion` el UseCase rechaza la combinación,
     * así que montar tipo y resultado por separado no basta: el pivote es parte
     * del escenario mínimo, no un extra.
     *
     * @param  array{requiere_compromiso?: bool, requiere_causa?: bool, es_contacto_efectivo?: bool, codigo_tipo?: string, codigo_resultado?: string}  $opciones
     * @return array{tipo_gestion_id: int, resultado_id: int, canal_id: int, motivo_no_contacto_id: int, causa_id: int}
     */
    protected function crearCascadaGestionEn(stdClass $proyecto, array $opciones = []): array
    {
        $ahora = Carbon::now();
        $sufijo = strtoupper(Str::random(6));

        $tipoGestionId = (int) DB::table('tipos_gestion')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => $opciones['codigo_tipo'] ?? 'TIPO_'.$sufijo,
            'nombre' => 'Tipo '.$sufijo,
            'activo' => true,
            'orden' => 10,
            'creada_en' => $ahora,
            'actualizada_en' => $ahora,
        ]);

        $resultadoId = (int) DB::table('resultados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => $opciones['codigo_resultado'] ?? 'RES_'.$sufijo,
            'nombre' => 'Resultado '.$sufijo,
            'activo' => true,
            'orden' => 10,
            'es_contacto_efectivo' => $opciones['es_contacto_efectivo'] ?? true,
            'requiere_compromiso' => $opciones['requiere_compromiso'] ?? false,
            'requiere_causa' => $opciones['requiere_causa'] ?? false,
            'creada_en' => $ahora,
            'actualizada_en' => $ahora,
        ]);

        DB::table('resultado_tipo_gestion')->insert([
            'proyecto_id' => $proyecto->id,
            'tipo_gestion_id' => $tipoGestionId,
            'resultado_id' => $resultadoId,
            'orden' => 10,
            'creada_en' => $ahora,
            'actualizada_en' => $ahora,
        ]);

        $motivoId = (int) DB::table('motivos_no_contacto')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'MOT_'.$sufijo,
            'nombre' => 'Motivo '.$sufijo,
            'activo' => true,
            'orden' => 10,
            'creada_en' => $ahora,
            'actualizada_en' => $ahora,
        ]);

        $causaId = (int) DB::table('causas_gestion')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'CAUSA_'.$sufijo,
            'nombre' => 'Causa '.$sufijo,
            'activo' => true,
            'orden' => 10,
            'creada_en' => $ahora,
            'actualizada_en' => $ahora,
        ]);

        return [
            'tipo_gestion_id' => $tipoGestionId,
            'resultado_id' => $resultadoId,
            'canal_id' => $this->canalDe($proyecto),
            'motivo_no_contacto_id' => $motivoId,
            'causa_id' => $causaId,
        ];
    }

    /** Un canal habilitado en el proyecto; `crearProyecto` los siembra todos. */
    protected function canalDe(stdClass $proyecto): int
    {
        return (int) DB::table('canal_proyecto')
            ->where('proyecto_id', $proyecto->id)
            ->where('activo', true)
            ->orderBy('orden')
            ->value('canal_id');
    }

    protected function crearContactoEn(stdClass $persona, string $tipo = 'telefono', ?string $valor = null): stdClass
    {
        $id = DB::table('contactos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $persona->proyecto_id,
            'persona_id' => $persona->id,
            'tipo' => $tipo,
            'valor' => $valor ?? ($tipo === 'correo' ? Str::random(8).'@crm.local' : (string) random_int(60000000, 69999999)),
            'es_principal' => true,
            'activo' => true,
            'origen' => 'manual',
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        return DB::table('contactos')->find($id);
    }

    protected function crearTipoPagoEn(stdClass $proyecto): int
    {
        return (int) DB::table('tipos_pago')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'PAGO_'.strtoupper(Str::random(6)),
            'nombre' => 'Tipo de pago',
            'activo' => true,
            'orden' => 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }

    /** El `tipo_caso` que corresponde al `tipo_operacion` del proyecto (§1: un proyecto, un tipo). */
    private function tipoCasoDe(stdClass $proyecto): string
    {
        return match ($proyecto->tipo_operacion) {
            'cx' => 'ticket_cx',
            'venta' => 'lead_venta',
            'servicio' => 'servicio',
            default => 'cobranza',
        };
    }
}
