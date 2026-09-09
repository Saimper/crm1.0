<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\SelectorProyecto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class CustomRoleProjectAccessTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_custom_role_alone_opens_its_project_and_selects_it_after_login(): void
    {
        $project = $this->crearProyectoCobranza();
        $user = User::factory()->create();
        $this->assignCustomRole($user, (int) $project->id);

        $this->assertSame([(int) $project->id], $user->proyectosAsignados());
        $this->assertTrue($user->tieneAccesoAProyecto((int) $project->id));
        $this->actingAs($user)->get(route('proyectos.casos.lista', $project->id))->assertOk();
        Livewire::test(SelectorProyecto::class)->assertRedirect(route('proyectos.dashboard', $project->id));
    }

    public function test_custom_role_does_not_open_another_project(): void
    {
        $project = $this->crearProyectoCobranza();
        $other = $this->crearProyectoCobranza();
        $user = User::factory()->create();
        $this->assignCustomRole($user, (int) $project->id);

        $this->assertFalse($user->tieneAccesoAProyecto((int) $other->id));
        $this->actingAs($user)->get(route('proyectos.casos.lista', $other->id))->assertForbidden();
    }

    public function test_revoking_assignment_removes_access_and_selector_entry(): void
    {
        $project = $this->crearProyectoCobranza();
        $user = User::factory()->create();
        $this->assignCustomRole($user, (int) $project->id);
        DB::table('usuario_proyecto_rol_custom')->where('usuario_id', $user->id)->update(['activo' => false]);

        $this->assertSame([], $user->proyectosAsignados());
        $this->actingAs($user)->get(route('proyectos.casos.lista', $project->id))->assertForbidden();
        Livewire::test(SelectorProyecto::class)->assertNoRedirect()->assertViewHas('proyectos', fn ($projects) => $projects->isEmpty());
    }

    public function test_disabled_or_archived_custom_role_cannot_grant_access(): void
    {
        $project = $this->crearProyectoCobranza();
        $user = User::factory()->create();
        $role = $this->assignCustomRole($user, (int) $project->id);
        DB::table('roles_custom')->where('id', $role)->update(['activo' => false]);
        $this->assertFalse($user->tieneAccesoAProyecto((int) $project->id));
        $this->assertSame([], $user->proyectosAsignados());

        DB::table('roles_custom')->where('id', $role)->update(['activo' => true, 'eliminada_en' => now()]);
        $this->assertFalse($user->tieneAccesoAProyecto((int) $project->id));
        $this->assertSame([], $user->proyectosAsignados());
    }

    public function test_selector_does_not_redirect_into_an_inactive_client(): void
    {
        $project = $this->crearProyectoCobranza();
        $user = $this->crearGestor($project);
        DB::table('mandantes')->where('id', $project->mandante_id)->update(['activo' => false]);

        $this->actingAs($user);
        Livewire::test(SelectorProyecto::class)->assertNoRedirect()->assertViewHas('proyectos', fn ($projects) => $projects->isEmpty());
    }

    private function assignCustomRole(User $user, int $projectId): int
    {
        $role = DB::table('roles_custom')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $projectId,
            'codigo' => 'CASE_READER',
            'nombre' => 'Case reader',
            'activo' => true,
            'creado_por_usuario_id' => $user->id,
        ]);
        DB::table('rol_custom_permiso')->insert([
            'rol_custom_id' => $role,
            'permiso_id' => DB::table('permisos')->where('codigo', 'casos.ver')->value('id'),
        ]);
        DB::table('usuario_proyecto_rol_custom')->insert([
            'usuario_id' => $user->id,
            'proyecto_id' => $projectId,
            'rol_custom_id' => $role,
            'activo' => true,
        ]);

        return (int) $role;
    }
}
