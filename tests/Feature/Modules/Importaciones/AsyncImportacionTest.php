<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Models\User;
use App\Modules\Importaciones\Application\UseCases\CancelarImportacion;
use App\Modules\Importaciones\Application\UseCases\ConsultarProgresoImportacion;
use App\Modules\Importaciones\Application\UseCases\EncolarImportacion;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionEnCursoNoEditable;
use App\Modules\Importaciones\Infrastructure\Jobs\EjecutarImportacionJob;
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AsyncImportacionTest extends TestCase
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

    public function test_encolar_dispatches_job_y_marca_procesando(): void
    {
        Queue::fake();

        [$proyectoId, $usuarioId] = $this->contextoProyectoCobranza();
        $importacionId = $this->crearImportacionPreparada($proyectoId, $usuarioId);

        app(EncolarImportacion::class)->execute($importacionId, ModoImportacion::MERGE);

        Queue::assertPushed(EjecutarImportacionJob::class, fn ($job) => $job->importacionId === $importacionId);

        $i = DB::table('importaciones')->where('id', $importacionId)->first();
        $this->assertSame('procesando', $i->estado);
        $this->assertNotNull($i->iniciado_en);
        $this->assertSame('merge', $i->modo);
    }

    public function test_encolar_dos_veces_falla_con_estado_no_editable(): void
    {
        Queue::fake();

        [$proyectoId, $usuarioId] = $this->contextoProyectoCobranza();
        $importacionId = $this->crearImportacionPreparada($proyectoId, $usuarioId);

        app(EncolarImportacion::class)->execute($importacionId, ModoImportacion::MERGE);

        $this->expectException(ImportacionEnCursoNoEditable::class);
        app(EncolarImportacion::class)->execute($importacionId, ModoImportacion::MERGE);
    }

    public function test_consultar_progreso_devuelve_dto_con_porcentaje(): void
    {
        [$proyectoId, $usuarioId] = $this->contextoProyectoCobranza();
        $importacionId = (int) DB::table('importaciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_entidad' => 'persona',
            'modo' => 'merge',
            'estado' => EstadoImportacion::PROCESANDO->value,
            'usuario_id' => $usuarioId,
            'nombre_archivo' => 'p.csv',
            'total_filas' => 100,
            'procesadas' => 30,
            'invalidas' => 10,
            'duplicadas' => 5,
        ]);

        $progreso = app(ConsultarProgresoImportacion::class)->execute($importacionId);

        $this->assertSame(EstadoImportacion::PROCESANDO, $progreso->estado);
        $this->assertSame(45, $progreso->porcentaje());
        $this->assertTrue($progreso->enCurso());
    }

    public function test_idempotencia_job_re_encolado_no_duplica_filas(): void
    {
        [$proyectoId, $usuarioId] = $this->contextoProyectoCobranza();
        $this->crearPersona($proyectoId, '7700000099');

        $importacionId = (int) DB::table('importaciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_entidad' => 'persona',
            'modo' => 'merge',
            'estado' => EstadoImportacion::PREPARADA->value,
            'usuario_id' => $usuarioId,
            'nombre_archivo' => 'idem.csv',
            'total_filas' => 1,
        ]);
        DB::table('importacion_filas')->insert([
            'importacion_id' => $importacionId,
            'proyecto_id' => $proyectoId,
            'numero_fila' => 1,
            'estado' => 'pendiente',
            'payload' => json_encode([
                'tipo_persona' => 'fisica',
                'tipo_identificacion_codigo' => 'CED',
                'identificacion' => '7700000099',
                'nombres' => 'Idem',
            ]),
        ]);

        $job1 = new EjecutarImportacionJob($importacionId, 'merge');
        $job1->handle();

        $personasAntes = (int) DB::table('personas')->where('identificacion', '7700000099')->count();
        $this->assertSame(1, $personasAntes);

        $job2 = new EjecutarImportacionJob($importacionId, 'merge');
        $job2->handle();

        $personasDespues = (int) DB::table('personas')->where('identificacion', '7700000099')->count();
        $this->assertSame(1, $personasDespues, 'Job re-ejecutado no debe duplicar filas');

        $i = DB::table('importaciones')->where('id', $importacionId)->first();
        $this->assertSame('completada', $i->estado);
    }

    public function test_dos_importaciones_distintas_avanzan_independientes(): void
    {
        [$proyectoCobId, $usuarioId] = $this->contextoProyectoCobranza();
        $proyectoCxId = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        $impA = $this->crearImportacionPersonasConFila($proyectoCobId, $usuarioId, '8800000001', 'Proyecto A');
        $impB = $this->crearImportacionPersonasConFila($proyectoCxId, $usuarioId, '8800000002', 'Proyecto B');

        (new EjecutarImportacionJob($impA, 'merge'))->handle();
        (new EjecutarImportacionJob($impB, 'merge'))->handle();

        $a = DB::table('importaciones')->where('id', $impA)->first();
        $b = DB::table('importaciones')->where('id', $impB)->first();

        $this->assertSame('completada', $a->estado);
        $this->assertSame('completada', $b->estado);
        $this->assertSame(1, (int) $a->procesadas);
        $this->assertSame(1, (int) $b->procesadas);

        $this->assertDatabaseHas('personas', ['identificacion' => '8800000001', 'proyecto_id' => $proyectoCobId]);
        $this->assertDatabaseHas('personas', ['identificacion' => '8800000002', 'proyecto_id' => $proyectoCxId]);
    }

    public function test_cancelacion_marca_estado_y_evita_procesamiento(): void
    {
        [$proyectoId, $usuarioId] = $this->contextoProyectoCobranza();
        $importacionId = $this->crearImportacionPersonasConFila($proyectoId, $usuarioId, '9999000001', 'Cancelable');

        DB::table('importaciones')->where('id', $importacionId)->update([
            'estado' => EstadoImportacion::PROCESANDO->value,
        ]);

        app(CancelarImportacion::class)->execute($importacionId);

        $i = DB::table('importaciones')->where('id', $importacionId)->first();
        $this->assertSame('cancelada', $i->estado);

        (new EjecutarImportacionJob($importacionId, 'merge'))->handle();

        $personas = (int) DB::table('personas')->where('identificacion', '9999000001')->count();
        $this->assertSame(0, $personas, 'Job cancelado no debe insertar');
    }

    private function contextoProyectoCobranza(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));

        $rolId = (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id');
        $u = User::query()->create([
            'name' => 'Sup',
            'email' => 'sup.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);
        $this->actingAs($u);

        return [$proyectoId, (int) $u->id];
    }

    private function crearPersona(int $proyectoId, string $identificacion): void
    {
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        DB::table('personas')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion' => $identificacion,
            'nombres' => 'Pre',
        ]);
    }

    private function crearImportacionPreparada(int $proyectoId, int $usuarioId): int
    {
        return (int) DB::table('importaciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_entidad' => 'persona',
            'modo' => 'merge',
            'estado' => EstadoImportacion::PREPARADA->value,
            'usuario_id' => $usuarioId,
            'nombre_archivo' => 'p.csv',
            'total_filas' => 0,
        ]);
    }

    private function crearImportacionPersonasConFila(int $proyectoId, int $usuarioId, string $identificacion, string $nombre): int
    {
        $importacionId = (int) DB::table('importaciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_entidad' => 'persona',
            'modo' => 'merge',
            'estado' => EstadoImportacion::PREPARADA->value,
            'usuario_id' => $usuarioId,
            'nombre_archivo' => 'p.csv',
            'total_filas' => 1,
        ]);
        DB::table('importacion_filas')->insert([
            'importacion_id' => $importacionId,
            'proyecto_id' => $proyectoId,
            'numero_fila' => 1,
            'estado' => 'pendiente',
            'payload' => json_encode([
                'tipo_persona' => 'fisica',
                'tipo_identificacion_codigo' => 'CED',
                'identificacion' => $identificacion,
                'nombres' => $nombre,
            ]),
        ]);

        return $importacionId;
    }
}
