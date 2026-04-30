<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Models\User;
use App\Modules\Reportes\Application\DTOs\EntradaDefinicionReporte;
use App\Modules\Reportes\Application\Servicios\ServicioCamposPersonalizadosReporte;
use App\Modules\Reportes\Application\UseCases\CrearDefinicionReporte;
use App\Modules\Reportes\Infrastructure\Persistence\Repositories\RepositorioDefinicionReporteEloquent;
use Database\Seeders\Tenancy\CarterasDemoSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use Database\Seeders\Usuarios\PermisosSeeder;
use Database\Seeders\Usuarios\RolesSeeder;
use Database\Seeders\Usuarios\RolPermisoSeeder;
use Database\Seeders\Usuarios\UsuarioAdminGlobalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ConstructorRutasTest extends TestCase
{
    use RefreshDatabase;

    private int $proyectoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            MandantesDemoSeeder::class,
            ProyectosDemoSeeder::class,
            CarterasDemoSeeder::class,
            RolesSeeder::class,
            PermisosSeeder::class,
            RolPermisoSeeder::class,
            UsuarioAdminGlobalSeeder::class,
        ]);
        $this->proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    public function test_supervisor_accede_listado_custom(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom', ['proyecto_id' => $this->proyectoId]))
            ->assertStatus(200);
    }

    public function test_gestor_recibe_403_en_listado_custom(): void
    {
        $u = $this->usuarioConRol('GESTOR');
        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom', ['proyecto_id' => $this->proyectoId]))
            ->assertStatus(403);
    }

    public function test_supervisor_accede_constructor_nuevo(): void
    {
        $u = $this->usuarioConRol('SUPERVISOR');
        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom.nuevo', ['proyecto_id' => $this->proyectoId]))
            ->assertStatus(200);
    }

    public function test_auditor_no_accede_constructor_nuevo(): void
    {
        $u = $this->usuarioConRol('AUDITOR');
        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom.nuevo', ['proyecto_id' => $this->proyectoId]))
            ->assertStatus(403);
    }

    public function test_auditor_si_accede_listado_pero_no_export(): void
    {
        $u = $this->usuarioConRol('AUDITOR');
        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom', ['proyecto_id' => $this->proyectoId]))
            ->assertStatus(200);

        $defId = $this->crearDefinicionDePrueba();

        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom.exportar', [
                'proyecto_id' => $this->proyectoId, 'definicion_id' => $defId, 'formato' => 'csv',
            ]))
            ->assertStatus(403);
    }

    public function test_supervisor_exporta_csv(): void
    {
        $defId = $this->crearDefinicionDePrueba();
        $u = $this->usuarioConRol('SUPERVISOR');

        $resp = $this->actingAs($u)->get(route('proyectos.reportes.custom.exportar', [
            'proyecto_id' => $this->proyectoId, 'definicion_id' => $defId, 'formato' => 'csv',
        ]));

        $resp->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $resp->headers->get('Content-Type'));
        $contenido = $resp->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido); // BOM
        $this->assertStringContainsString('ID', $contenido); // header de la columna
    }

    public function test_supervisor_exporta_xlsx(): void
    {
        $defId = $this->crearDefinicionDePrueba();
        $u = $this->usuarioConRol('SUPERVISOR');

        $resp = $this->actingAs($u)->get(route('proyectos.reportes.custom.exportar', [
            'proyecto_id' => $this->proyectoId, 'definicion_id' => $defId, 'formato' => 'xlsx',
        ]));

        $resp->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml', (string) $resp->headers->get('Content-Type'));
        $contenido = $resp->streamedContent();
        // XLSX = ZIP archive: magic bytes "PK"
        $this->assertStringStartsWith('PK', $contenido);
    }

    public function test_export_registra_ejecucion(): void
    {
        $defId = $this->crearDefinicionDePrueba();
        $u = $this->usuarioConRol('SUPERVISOR');

        $resp = $this->actingAs($u)->get(route('proyectos.reportes.custom.exportar', [
            'proyecto_id' => $this->proyectoId, 'definicion_id' => $defId, 'formato' => 'csv',
        ]));
        $resp->streamedContent(); // fuerza ejecución del closure

        $this->assertDatabaseHas('reportes_ejecuciones', [
            'definicion_id' => $defId,
            'proyecto_id' => $this->proyectoId,
            'usuario_id' => $u->id,
            'formato' => 'csv',
        ]);
    }

    public function test_export_definicion_otro_proyecto_da_404(): void
    {
        $defId = $this->crearDefinicionDePrueba();
        $otroProy = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        $u = User::query()->create([
            'name' => 'Cross', 'email' => 'cross.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $otroProy,
            'rol_id' => $rolId, 'activo' => true,
        ]);

        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom.exportar', [
                'proyecto_id' => $otroProy, 'definicion_id' => $defId, 'formato' => 'csv',
            ]))
            ->assertStatus(404);
    }

    public function test_export_formato_invalido_da_422(): void
    {
        $defId = $this->crearDefinicionDePrueba();
        $u = $this->usuarioConRol('SUPERVISOR');

        $this->actingAs($u)
            ->get(route('proyectos.reportes.custom.exportar', [
                'proyecto_id' => $this->proyectoId, 'definicion_id' => $defId, 'formato' => 'pdf',
            ]))
            ->assertStatus(422);
    }

    private function crearDefinicionDePrueba(): int
    {
        $repo = new RepositorioDefinicionReporteEloquent;
        $cp = new ServicioCamposPersonalizadosReporte;
        $crear = new CrearDefinicionReporte($repo, $cp);
        $usuarioId = (int) DB::table('users')->where('email', 'admin@crm.local')->value('id');

        return $crear->execute(
            new EntradaDefinicionReporte(
                proyectoId: $this->proyectoId,
                codigo: 'test_export',
                nombre: 'Test Export',
                entidadRaiz: 'casos',
                columnas: [
                    ['campo' => 'casos.public_id', 'etiqueta' => 'ID'],
                    ['campo' => 'casos.tipo_caso', 'etiqueta' => 'Tipo'],
                ],
            ),
            $usuarioId,
        );
    }

    private function usuarioConRol(string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);

        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $this->proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
