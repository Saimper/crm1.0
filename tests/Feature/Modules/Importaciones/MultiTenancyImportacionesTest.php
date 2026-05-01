<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Models\User;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\ImportarPersonas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F34C — multi-tenancy: historial de importaciones del proyecto B
 * no aparece en panel del proyecto A.
 */
final class MultiTenancyImportacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_historial_no_muestra_imports_de_otro_proyecto(): void
    {
        $proyectoA = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        $usuarioId = (int) DB::table('users')->first()->id;

        // Importación en proyecto B (ajena).
        DB::table('importaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB,
            'tipo_entidad' => 'persona',
            'modo' => 'merge',
            'estado' => 'completada',
            'usuario_id' => $usuarioId,
            'nombre_archivo' => 'foreign.csv',
            'total_filas' => 1,
        ]);
        // Importación propia en proyecto A.
        $idA = (int) DB::table('importaciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoA,
            'tipo_entidad' => 'persona',
            'modo' => 'merge',
            'estado' => 'completada',
            'usuario_id' => $usuarioId,
            'nombre_archivo' => 'mio.csv',
            'total_filas' => 1,
        ]);

        $this->bindProyectoActivo($proyectoA);
        $this->actingAs($this->crearConRol($proyectoA, 'SUPERVISOR'));

        $c = Livewire::test(ImportarPersonas::class);
        $historial = $c->viewData('historial');

        $codigosVistos = collect($historial)->pluck('nombre_archivo')->all();
        $this->assertContains('mio.csv', $codigosVistos);
        $this->assertNotContains('foreign.csv', $codigosVistos);
    }

    private function bindProyectoActivo(int $proyectoId): void
    {
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
    }

    private function crearConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.mt.'.Str::random(6).'@crm.local',
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
