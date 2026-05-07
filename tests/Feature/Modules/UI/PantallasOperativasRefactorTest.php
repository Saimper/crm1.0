<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\UI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fase 29: verifica que las pantallas operativas refactorizadas renderizan
 * con el design system F29 (.page + .page-header + .card).
 */
final class PantallasOperativasRefactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_bandeja_usa_tokens_design_system(): void
    {
        $proyectoId = $this->proyectoId();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $response = $this->actingAs($gestor)
            ->get(route('proyectos.bandeja', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);

        // Page header con tokens
        $response->assertSee('page-header', false);
        // Card / shadow del sistema
        $response->assertSee('class="card"', false);
    }

    public function test_bandeja_equipo_refactorizada(): void
    {
        $proyectoId = $this->proyectoId();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');

        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.bandeja.equipo', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);

        $response->assertSee('page-header', false);
        $response->assertSee('class="card"', false);
    }

    public function test_notificaciones_refactorizada(): void
    {
        $proyectoId = $this->proyectoId();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $response = $this->actingAs($gestor)
            ->get(route('proyectos.notificaciones', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);

        $response->assertSee('page-header', false);
    }

    public function test_vista_trabajo_shell_refactorizada(): void
    {
        $proyectoId = $this->proyectoId();
        $gestor = $this->crearConRol($proyectoId, 'GESTOR');

        $personaPublicId = (string) DB::table('personas')
            ->where('proyecto_id', $proyectoId)
            ->value('public_id');
        $this->assertNotEmpty($personaPublicId);

        $response = $this->actingAs($gestor)
            ->get(route('proyectos.trabajo', [
                'proyecto_id' => $proyectoId,
                'persona' => $personaPublicId,
            ]))
            ->assertStatus(200);

        $response->assertSee('Vista de trabajo', false);
        $response->assertSee('page-header', false);
    }

    private function proyectoId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function crearConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.ui.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
