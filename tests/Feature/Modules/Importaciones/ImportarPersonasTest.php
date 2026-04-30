<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Models\User;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\ImportarPersonas;
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
use Database\Seeders\Tenancy\CarterasDemoSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use Database\Seeders\Usuarios\PermisosSeeder;
use Database\Seeders\Usuarios\RolesSeeder;
use Database\Seeders\Usuarios\RolPermisoSeeder;
use Database\Seeders\Usuarios\UsuarioAdminGlobalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class ImportarPersonasTest extends TestCase
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
            UsuarioAdminGlobalSeeder::class,
        ]);
    }

    public function test_supervisor_sube_csv_valido_y_commit(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));

        $supervisor = $this->crearUsuarioConRol($proyectoId, 'SUPERVISOR');
        $this->actingAs($supervisor);

        $csv = "tipo_persona,tipo_identificacion_codigo,identificacion,nombres,apellidos,razon_social,fecha_nacimiento\n"
             ."fisica,CED,2200000001,Rosa,Andrade,,1985-05-10\n"
             ."fisica,CED,2200000002,Luis,Paredes,,\n"
             ."juridica,RUC,1799888800001,,,Empresa Demo S.A.,\n";
        $archivo = UploadedFile::fake()->createWithContent('personas.csv', $csv);

        $componente = Livewire::test(ImportarPersonas::class)
            ->set('archivo', $archivo)
            ->call('guardarArchivo')
            ->assertHasNoErrors();

        $importacionId = $componente->get('importacionId');
        $this->assertNotNull($importacionId);

        $this->assertDatabaseHas('importaciones', [
            'id' => $importacionId,
            'proyecto_id' => $proyectoId,
            'total_filas' => 3,
            'filas_ok' => 3,
            'filas_error' => 0,
            'estado' => 'validada',
        ]);

        $componente->call('confirmar');

        $this->assertDatabaseHas('importaciones', [
            'id' => $importacionId,
            'filas_importadas' => 3,
            'estado' => 'completada',
        ]);
        $this->assertDatabaseHas('personas', ['identificacion' => '2200000001']);
        $this->assertDatabaseHas('personas', ['identificacion' => '1799888800001']);
    }

    public function test_csv_con_filas_invalidas_reporta_errores_sin_importar(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
        $this->actingAs($this->crearUsuarioConRol($proyectoId, 'SUPERVISOR'));

        $csv = "tipo_persona,tipo_identificacion_codigo,identificacion,nombres,apellidos,razon_social,fecha_nacimiento\n"
             ."fisica,CED,,Sin identificacion,,,\n"            // sin identificacion
             ."XYZ,CED,2200000010,,,,\n"                         // tipo_persona inválido
             ."fisica,CED,2200000011,Valido,Ok,,\n";            // ok
        $archivo = UploadedFile::fake()->createWithContent('mix.csv', $csv);

        $c = Livewire::test(ImportarPersonas::class)
            ->set('archivo', $archivo)
            ->call('guardarArchivo')
            ->assertHasNoErrors();

        $id = $c->get('importacionId');
        $this->assertDatabaseHas('importaciones', [
            'id' => $id,
            'total_filas' => 3,
            'filas_ok' => 1,
            'filas_error' => 2,
            'estado' => 'validada',
        ]);
    }

    public function test_gestor_sin_permiso_recibe_403(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $gestor = $this->crearUsuarioConRol($proyectoId, 'GESTOR');

        $this->actingAs($gestor)
            ->get(route('proyectos.importaciones', ['proyecto_id' => $proyectoId]))
            ->assertStatus(403);
    }

    public function test_exportar_personas_descarga_csv(): void
    {
        $proyectoId = $this->proyectoCobranzaId();
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));

        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        DB::table('personas')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion' => '3300000001',
            'nombres' => 'Export',
            'apellidos' => 'Test',
        ]);

        $supervisor = $this->crearUsuarioConRol($proyectoId, 'SUPERVISOR');
        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.importaciones.exportar-personas', ['proyecto_id' => $proyectoId]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $contenido = $response->streamedContent();
        $this->assertStringContainsString('3300000001', $contenido);
        $this->assertStringContainsString('Export', $contenido);
    }

    private function proyectoCobranzaId(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function crearUsuarioConRol(int $proyectoId, string $codigoRol): User
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
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
