<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use App\Modules\Usuarios\Application\RolesBase\GuardarRolBase;
use App\Modules\Usuarios\Domain\Contracts\AccesoACuenta;
use App\Modules\Usuarios\Domain\RolesBase\ConfiguracionRolBase;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class AccountAccessTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_other_owner_requires_explicit_cooperation_and_never_transfers_ownership(): void
    {
        $p = $this->crearProyectoCobranza();
        $caso = $this->crearCasoEn($p);
        $gestor = $this->crearGestor($p);
        $otro = $this->crearGestor($p);
        $guardia = app(AccesoACuenta::class);
        self::assertTrue($guardia->puedeGestionar((int) $gestor->id, (int) $p->id, $caso));
        DB::table('asignaciones')->insert([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $p->id, 'caso_id' => $caso,
            'usuario_id' => $otro->id, 'fecha_asignacion' => '2026-09-11', 'estado' => 'pendiente',
        ]);
        self::assertFalse($guardia->puedeGestionar((int) $gestor->id, (int) $p->id, $caso));
        self::assertTrue($guardia->puedeGestionar((int) $otro->id, (int) $p->id, $caso));
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);
        app(GuardarRolBase::class)->execute(ConfiguracionRolBase::proyecto('GESTOR', (int) $p->id, ['casos.colaborar' => 'permitir']), (int) $admin->id);
        self::assertTrue($guardia->puedeGestionar((int) $gestor->id, (int) $p->id, $caso));
        self::assertSame((int) $otro->id, (int) DB::table('asignaciones')->where('caso_id', $caso)->value('usuario_id'));
    }

    public function test_cooperation_still_requires_management_permission_and_active_account_project_user(): void
    {
        $p = $this->crearProyectoCobranza();
        $caso = $this->crearCasoEn($p);
        $supervisor = $this->crearSupervisor($p);
        $guardia = app(AccesoACuenta::class);
        self::assertTrue($guardia->puedeGestionar((int) $supervisor->id, (int) $p->id, $caso));
        $admin = $this->crearAdminGlobal();
        $this->actingAs($admin);
        app(GuardarRolBase::class)->execute(ConfiguracionRolBase::proyecto('SUPERVISOR', (int) $p->id, ['gestiones.crear' => 'denegar']), (int) $admin->id);
        self::assertFalse($guardia->puedeGestionar((int) $supervisor->id, (int) $p->id, $caso));
        app(GuardarRolBase::class)->execute(ConfiguracionRolBase::proyecto('SUPERVISOR', (int) $p->id, []), (int) $admin->id);
        DB::table('carteras')->where('proyecto_id', $p->id)->update(['activo' => false]);
        self::assertFalse($guardia->puedeGestionar((int) $supervisor->id, (int) $p->id, $caso));
        self::assertFalse($guardia->puedeGestionar((int) $admin->id, (int) $p->id, $caso));
    }

    public function test_reincorporation_requires_source_and_destination_permission_and_preserves_tenant_boundaries(): void
    {
        $p = $this->crearProyectoCobranza();
        $ajeno = $this->crearProyectoCobranza();
        $origen = $this->crearCarteraEn($p);
        $destino = $this->crearCarteraEn($p);
        $carteraAjena = $this->crearCarteraEn($ajeno);
        DB::table('carteras')->where('id', $origen->id)->update(['activo' => false, 'eliminada_en' => now()]);
        $supervisor = $this->crearSupervisor($p);
        $guardia = app(AccesoACuenta::class);
        self::assertFalse($supervisor->tienePermiso('carteras.editar', (int) $p->id));
        self::assertTrue($guardia->puedeReincorporar((int) $supervisor->id, (int) $p->id, (int) $origen->id, (int) $destino->id));
        $rolId = (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id');
        DB::table('usuario_proyecto_rol_cartera')->insert(['usuario_id' => $supervisor->id, 'proyecto_id' => $p->id, 'rol_id' => $rolId, 'cartera_id' => $destino->id]);
        self::assertFalse($guardia->puedeReincorporar((int) $supervisor->id, (int) $p->id, (int) $origen->id, (int) $destino->id));
        DB::table('usuario_proyecto_rol_cartera')->insert(['usuario_id' => $supervisor->id, 'proyecto_id' => $p->id, 'rol_id' => $rolId, 'cartera_id' => $origen->id]);
        self::assertTrue($guardia->puedeReincorporar((int) $supervisor->id, (int) $p->id, (int) $origen->id, (int) $destino->id));
        self::assertFalse($guardia->puedeReincorporar((int) $supervisor->id, (int) $p->id, (int) $origen->id, (int) $carteraAjena->id));
        self::assertFalse($guardia->puedeReincorporar((int) $supervisor->id, (int) $ajeno->id, (int) $origen->id, (int) $destino->id));
        DB::table('users')->where('id', $supervisor->id)->update(['activo' => false]);
        self::assertFalse($guardia->puedeReincorporar((int) $supervisor->id, (int) $p->id, (int) $origen->id, (int) $destino->id));
    }
}
