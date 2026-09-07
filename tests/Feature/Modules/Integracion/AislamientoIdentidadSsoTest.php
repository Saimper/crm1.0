<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El JWT del handshake identifica al usuario por el claim `sub` (email), y ese
 * claim lo elige quien firma. Como cada mandante firma con su propio
 * sso_secret, resolver la identidad solo por email permitia que un tenant
 * reclamara la cuenta de otro tenant — o la del administrador global — con un
 * token perfectamente valido.
 *
 * Estos tests fijan la ligadura: un token solo puede resolver a un usuario que
 * pertenezca al mandante que lo firmo.
 */
final class AislamientoIdentidadSsoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** @param array<string, mixed> $extra */
    private function jwtPara(stdClass $mandante, string $email, array $extra = []): string
    {
        return JWT::encode(array_merge([
            'sub' => $email,
            'name' => 'Quien Sea',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $mandante->id,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], $extra), (string) $mandante->sso_secret, 'HS256');
    }

    public function test_un_mandante_no_puede_autenticar_al_usuario_de_otro_mandante(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandanteA);
        $victima = $this->crearGestor($proyectoA);

        // B firma con SU secret — valido — pero reclamando el email de A.
        $jwt = $this->jwtPara($mandanteB, (string) $victima->email);

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(403);
        $this->assertGuest();
    }

    public function test_el_endpoint_de_sanctum_tambien_rechaza_la_identidad_ajena(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandanteA);
        $victima = $this->crearGestor($proyectoA);

        $jwt = $this->jwtPara($mandanteB, (string) $victima->email);

        $this->postJson('/api/integracion/sanctum-token', ['token' => $jwt])->assertStatus(403);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_un_usuario_con_rol_global_nunca_entra_por_sso(): void
    {
        $mandante = $this->crearMandante();
        $admin = $this->crearAdminGlobal();

        $jwt = $this->jwtPara($mandante, (string) $admin->email);

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(403);
        $this->assertGuest();
    }

    public function test_el_sso_no_reactiva_una_cuenta_desactivada(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $usuario = $this->crearGestor($proyecto);

        DB::table('users')->where('id', $usuario->id)->update(['activo' => false]);

        $jwt = $this->jwtPara($mandante, (string) $usuario->email);

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(403);
        $this->assertGuest();
        $this->assertFalse(
            (bool) DB::table('users')->where('id', $usuario->id)->value('activo'),
            'El handshake reactivo una cuenta que estaba dada de baja.'
        );
    }

    public function test_el_usuario_del_propio_mandante_sigue_entrando(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $usuario = $this->crearGestor($proyecto);

        $jwt = $this->jwtPara($mandante, (string) $usuario->email, [
            'proyecto_id' => (int) $proyecto->id,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();
        $this->assertAuthenticatedAs($usuario->fresh());
    }

    public function test_un_usuario_nuevo_queda_ligado_al_mandante_que_lo_provisiono(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);

        $jwt = $this->jwtPara($mandante, 'nuevo@wrap.io', [
            'proyecto_id' => (int) $proyecto->id,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();

        $creado = User::query()->where('email', 'nuevo@wrap.io')->first();
        $this->assertNotNull($creado);
        $this->assertSame((int) $mandante->id, (int) $creado->mandante_origen_id);
    }

    /**
     * Un GESTOR sin proyecto_id no recibe pivot (solo los roles de mandante o
     * los tokens con proyecto lo reciben). Sin `mandante_origen_id` ese usuario
     * quedaba sin ninguna ligadura y su SEGUNDO handshake habria sido
     * rechazado: es la regresion que la columna evita.
     */
    public function test_un_usuario_provisionado_sin_pivot_puede_volver_a_entrar(): void
    {
        $mandante = $this->crearMandante();
        $this->crearProyectoCobranza($mandante);

        $primero = $this->jwtPara($mandante, 'sinpivot@wrap.io');
        $this->get("/integracion/handshake?token={$primero}")->assertRedirect();

        $creado = User::query()->where('email', 'sinpivot@wrap.io')->firstOrFail();
        $this->assertSame(0, DB::table('usuario_proyecto_rol')->where('usuario_id', $creado->id)->count());

        $this->flushSession();
        Auth::guard('web')->logout();
        $segundo = $this->jwtPara($mandante, 'sinpivot@wrap.io');
        $this->get("/integracion/handshake?token={$segundo}")->assertRedirect();
        $this->assertAuthenticatedAs($creado->fresh());
    }

    public function test_un_usuario_compartido_por_pivot_entra_desde_ambos_mandantes(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandanteA);
        $proyectoB = $this->crearProyectoCobranza($mandanteB);

        $usuario = $this->crearGestor($proyectoA);

        // Vinculo explicito creado por administracion, no por el propio SSO.
        $rolId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $usuario->id,
            'proyecto_id' => $proyectoB->id,
            'rol_id' => $rolId,
            'activo' => true,
        ]);

        $jwt = $this->jwtPara($mandanteB, (string) $usuario->email, [
            'proyecto_id' => (int) $proyectoB->id,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();
        $this->assertAuthenticatedAs($usuario->fresh());
    }

    /**
     * Una cuenta sin origen y sin pivot no pertenece a nadie, y por eso mismo
     * NO se adopta: dejar que el primer mandante que la reclame se la quede
     * convertiria toda alta manual (que nace sin pivot) en reclamable por SSO,
     * saltandose su contrasenia. Se rechaza y un administrador decide.
     */
    public function test_una_cuenta_sin_vinculos_no_es_adoptable_por_sso(): void
    {
        $mandante = $this->crearMandante();
        $this->crearProyectoCobranza($mandante);

        User::query()->create([
            'name' => 'Alta Manual',
            'email' => 'manual@crm.local',
            'password' => bcrypt('secreta'),
            'activo' => true,
        ]);

        $jwt = $this->jwtPara($mandante, 'manual@crm.local');

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(403);
        $this->assertGuest();
        $this->assertNull(
            User::query()->where('email', 'manual@crm.local')->value('mandante_origen_id'),
            'Un handshake rechazado no debe dejar rastro en la cuenta.'
        );
    }

    public function test_un_pivot_desactivado_no_vuelve_a_dar_acceso(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $usuario = $this->crearGestor($proyecto);

        // Se le retira la asignacion: el pivot queda inactivo.
        DB::table('usuario_proyecto_rol')
            ->where('usuario_id', $usuario->id)
            ->update(['activo' => false]);
        DB::table('users')->where('id', $usuario->id)->update(['mandante_origen_id' => null]);

        $jwt = $this->jwtPara($mandante, (string) $usuario->email);

        $this->get("/integracion/handshake?token={$jwt}")->assertStatus(403);
        $this->assertGuest();
    }

    public function test_la_pertenencia_tambien_vale_por_rol_de_mandante(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $usuario = $this->crearGestor($proyecto);

        DB::table('usuario_proyecto_rol')->where('usuario_id', $usuario->id)->delete();
        DB::table('users')->where('id', $usuario->id)->update(['mandante_origen_id' => null]);

        $rolId = (int) DB::table('roles')->where('codigo', 'ADMIN_MANDANTE')->value('id');
        DB::table('usuario_mandante_rol')->insert([
            'usuario_id' => $usuario->id,
            'mandante_id' => $mandante->id,
            'rol_id' => $rolId,
            'activo' => true,
        ]);

        $jwt = $this->jwtPara($mandante, (string) $usuario->email, [
            'proyecto_id' => (int) $proyecto->id,
        ]);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();
        $this->assertAuthenticatedAs($usuario->fresh());
    }

    public function test_el_403_no_revela_por_que_se_rechazo(): void
    {
        $mandanteA = $this->crearMandante();
        $mandanteB = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandanteA);
        $ajeno = $this->crearGestor($proyectoA);
        $global = $this->crearAdminGlobal();

        $esperado = 'Acceso no permitido para esta identidad.';

        foreach ([$ajeno->email, $global->email] as $email) {
            $this->flushSession();
            $this->get('/integracion/handshake?token='.$this->jwtPara($mandanteB, (string) $email))
                ->assertStatus(403)
                ->assertSee($esperado);
        }
    }
}
