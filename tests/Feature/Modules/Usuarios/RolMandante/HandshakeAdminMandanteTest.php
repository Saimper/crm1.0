<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios\RolMandante;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class HandshakeAdminMandanteTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_tenant_sin_proyecto_id_crea_pivot_mandante_no_proyecto(): void
    {
        $mandante = $this->crearMandante();

        $jwt = $this->firmar([
            'sub' => 'admin.tenant@wrap.io',
            'name' => 'Admin Tenant',
            'wrapper_role' => 'admin_tenant',
            'mandante_id' => (int) $mandante->id,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], (string) $mandante->sso_secret);

        $this->get("/integracion/handshake?token={$jwt}")
            ->assertRedirect("/dashboard?mandante={$mandante->id}");

        $usuario = User::where('email', 'admin.tenant@wrap.io')->firstOrFail();
        $rolMandanteId = (int) DB::table('roles')->where('codigo', 'ADMIN_MANDANTE')->value('id');

        $this->assertSame(
            1,
            DB::table('usuario_mandante_rol')
                ->where('usuario_id', $usuario->id)
                ->where('mandante_id', $mandante->id)
                ->where('rol_id', $rolMandanteId)
                ->where('activo', true)
                ->count(),
        );

        // No debe crear pivot proyecto.
        $this->assertSame(0, DB::table('usuario_proyecto_rol')->where('usuario_id', $usuario->id)->count());
    }

    public function test_admin_tenant_con_proyecto_id_igual_va_a_pivot_mandante(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);

        $jwt = $this->firmar([
            'sub' => 'admin.with.proj@wrap.io',
            'name' => 'Admin Con Proyecto',
            'wrapper_role' => 'admin_tenant',
            'mandante_id' => (int) $mandante->id,
            'proyecto_id' => (int) $proyecto->id,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], (string) $mandante->sso_secret);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();

        $usuario = User::where('email', 'admin.with.proj@wrap.io')->firstOrFail();

        $this->assertSame(1, DB::table('usuario_mandante_rol')->where('usuario_id', $usuario->id)->count());
        $this->assertSame(0, DB::table('usuario_proyecto_rol')->where('usuario_id', $usuario->id)->count());
    }

    public function test_agent_sigue_creando_pivot_proyecto_no_mandante(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);

        $jwt = $this->firmar([
            'sub' => 'agent.normal@wrap.io',
            'name' => 'Agente',
            'wrapper_role' => 'agent',
            'mandante_id' => (int) $mandante->id,
            'proyecto_id' => (int) $proyecto->id,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], (string) $mandante->sso_secret);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();

        $usuario = User::where('email', 'agent.normal@wrap.io')->firstOrFail();
        $rolGestorId = (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id');

        $this->assertTrue(
            DB::table('usuario_proyecto_rol')
                ->where('usuario_id', $usuario->id)
                ->where('proyecto_id', $proyecto->id)
                ->where('rol_id', $rolGestorId)
                ->exists(),
        );
        $this->assertSame(0, DB::table('usuario_mandante_rol')->where('usuario_id', $usuario->id)->count());
    }

    public function test_admin_tenant_re_handshake_no_duplica_pivot(): void
    {
        $mandante = $this->crearMandante();

        $ahora = time();

        $jwt1 = $this->firmar([
            'sub' => 'dup@wrap.io',
            'wrapper_role' => 'admin_tenant',
            'mandante_id' => (int) $mandante->id,
            'jti' => Str::uuid()->toString(),
            'iat' => $ahora,
            'exp' => $ahora + 60,
        ], (string) $mandante->sso_secret);

        $jwt2 = $this->firmar([
            'sub' => 'dup@wrap.io',
            'wrapper_role' => 'admin_tenant',
            'mandante_id' => (int) $mandante->id,
            'jti' => Str::uuid()->toString(),
            'iat' => $ahora,
            'exp' => $ahora + 60,
        ], (string) $mandante->sso_secret);

        $this->get("/integracion/handshake?token={$jwt1}")->assertRedirect();
        $this->get("/integracion/handshake?token={$jwt2}")->assertRedirect();

        $usuario = User::where('email', 'dup@wrap.io')->firstOrFail();
        $this->assertSame(
            1,
            DB::table('usuario_mandante_rol')->where('usuario_id', $usuario->id)->count(),
        );
    }

    public function test_supervisor_wrapper_no_es_admin_mandante(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);

        $jwt = $this->firmar([
            'sub' => 'super.wrap@wrap.io',
            'wrapper_role' => 'supervisor',
            'mandante_id' => (int) $mandante->id,
            'proyecto_id' => (int) $proyecto->id,
            'jti' => Str::uuid()->toString(),
            'iat' => time(),
            'exp' => time() + 60,
        ], (string) $mandante->sso_secret);

        $this->get("/integracion/handshake?token={$jwt}")->assertRedirect();

        $usuario = User::where('email', 'super.wrap@wrap.io')->firstOrFail();
        $rolSupId = (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id');

        $this->assertTrue(
            DB::table('usuario_proyecto_rol')
                ->where('usuario_id', $usuario->id)
                ->where('proyecto_id', $proyecto->id)
                ->where('rol_id', $rolSupId)
                ->exists(),
        );
        $this->assertSame(0, DB::table('usuario_mandante_rol')->where('usuario_id', $usuario->id)->count());
    }

    /** @param array<string, mixed> $claims */
    private function firmar(array $claims, string $secret): string
    {
        return JWT::encode($claims, $secret, 'HS256');
    }
}
