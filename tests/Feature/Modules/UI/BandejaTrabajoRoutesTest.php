<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\UI;

use App\Models\User;
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
use Database\Seeders\Tenancy\CarterasDemoSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use Database\Seeders\Usuarios\PermisosSeeder;
use Database\Seeders\Usuarios\RolesSeeder;
use Database\Seeders\Usuarios\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BandejaTrabajoRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            MandantesDemoSeeder::class,
            ProyectosDemoSeeder::class,
            CarterasDemoSeeder::class,
            TiposIdentificacionSeeder::class,
            RolesSeeder::class,
            PermisosSeeder::class,
            RolPermisoSeeder::class,
        ]);
    }

    public function test_gestor_accede_a_bandeja_del_proyecto(): void
    {
        $gestor = User::factory()->create();
        $proyectoId = $this->idProyecto();
        $this->asignar($gestor->id, $proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get("/proyectos/{$proyectoId}/bandeja")
            ->assertOk()
            ->assertSee('Bandeja');
    }

    public function test_usuario_sin_permiso_bandeja_recibe_403(): void
    {
        $auditor = User::factory()->create();
        $proyectoId = $this->idProyecto();
        $this->asignar($auditor->id, $proyectoId, 'AUDITOR');

        // AUDITOR no tiene asignaciones.ver_propia según matriz.
        $this->actingAs($auditor)
            ->get("/proyectos/{$proyectoId}/bandeja")
            ->assertForbidden();
    }

    public function test_gestor_accede_a_vista_de_trabajo_de_persona_en_proyecto(): void
    {
        $gestor = User::factory()->create();
        $proyectoId = $this->idProyecto();
        $this->asignar($gestor->id, $proyectoId, 'GESTOR');

        $personaPublicId = $this->crearPersona($proyectoId);

        $this->actingAs($gestor)
            ->get("/proyectos/{$proyectoId}/trabajo/{$personaPublicId}")
            ->assertOk()
            ->assertSee('Vista de trabajo');
    }

    public function test_vista_de_trabajo_rechaza_persona_de_otro_proyecto(): void
    {
        $gestor = User::factory()->create();
        $proyectoId = $this->idProyecto();
        $this->asignar($gestor->id, $proyectoId, 'GESTOR');

        // Crear otro proyecto del mismo mandante y una persona allí.
        $mandanteId = (int) DB::table('mandantes')->where('codigo', 'BPO_DEMO')->value('id');
        $otroProyectoId = (int) DB::table('proyectos')->insertGetId([
            'public_id'      => (string) Str::ulid(),
            'mandante_id'    => $mandanteId,
            'codigo'         => 'OTRO_P',
            'nombre'         => 'Otro proyecto',
            'tipo_operacion' => 'cobranza',
            'activo'         => true,
        ]);
        $personaAjenaPublicId = $this->crearPersona($otroProyectoId);

        $this->actingAs($gestor)
            ->get("/proyectos/{$proyectoId}/trabajo/{$personaAjenaPublicId}")
            ->assertNotFound();
    }

    public function test_bandeja_de_otro_proyecto_sin_acceso_recibe_403(): void
    {
        $gestor = User::factory()->create();
        $proyectoAsignado = $this->idProyecto();
        $this->asignar($gestor->id, $proyectoAsignado, 'GESTOR');

        $mandanteId = (int) DB::table('mandantes')->where('codigo', 'BPO_DEMO')->value('id');
        $otroProyectoId = (int) DB::table('proyectos')->insertGetId([
            'public_id'      => (string) Str::ulid(),
            'mandante_id'    => $mandanteId,
            'codigo'         => 'AJENO_P',
            'nombre'         => 'Proyecto ajeno',
            'tipo_operacion' => 'cobranza',
            'activo'         => true,
        ]);

        $this->actingAs($gestor)
            ->get("/proyectos/{$otroProyectoId}/bandeja")
            ->assertForbidden();
    }

    private function idProyecto(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function asignar(int $usuarioId, int $proyectoId, string $rolCodigo): void
    {
        $rolId = (int) DB::table('roles')->where('codigo', $rolCodigo)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id'  => $usuarioId,
            'proyecto_id' => $proyectoId,
            'rol_id'      => $rolId,
            'activo'      => true,
        ]);
    }

    private function crearPersona(int $proyectoId): string
    {
        $tipoIdentId = (int) DB::table('tipos_identificacion')->orderBy('id')->value('id');
        $publicId = (string) Str::ulid();

        DB::table('personas')->insert([
            'public_id'              => $publicId,
            'proyecto_id'            => $proyectoId,
            'tipo_persona'           => 'fisica',
            'tipo_identificacion_id' => $tipoIdentId,
            'identificacion'         => '9999'.$proyectoId,
            'nombres'                => 'Persona',
            'apellidos'              => 'Test',
        ]);

        return $publicId;
    }
}
