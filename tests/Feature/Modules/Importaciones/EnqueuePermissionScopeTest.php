<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Models\User;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Application\UseCases\EncolarImportacion;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionNoProcesable;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Importaciones\Infrastructure\Http\Livewire\Importar;
use App\Modules\Importaciones\Infrastructure\Jobs\EjecutarImportacionJob;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class EnqueuePermissionScopeTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
    }

    public static function activeImportModes(): iterable
    {
        yield 'new account' => [ModoImportacion::INSERT];
        yield 'existing account' => [ModoImportacion::UPSERT];
    }

    #[DataProvider('activeImportModes')]
    public function test_explicit_actor_cannot_enqueue_new_or_existing_accounts_in_a_denied_active_portfolio(ModoImportacion $mode): void
    {
        [$project, $allowed, $denied, $actor] = $this->scenario();
        $case = $this->crearCasoEn($project, ['cartera' => $denied]);
        DB::table('casos_cobranza')->insert(['proyecto_id' => $project->id, 'caso_id' => $case, 'numero_prestamo' => 'PRESERVE-ACTIVE', 'saldo_total' => 100, 'moneda' => 'USD']);
        $import = $this->prepare($project, $denied, $this->crearAdminGlobal(), $mode);
        $before = DB::table('importaciones')->where('id', $import)->value('esquema');

        try {
            app(EncolarImportacion::class)->execute($import, $mode, usuarioId: (int) $actor->id);
            self::fail('The importing actor must be authorized on the destination.');
        } catch (ImportacionNoProcesable $exception) {
            self::assertStringContainsString('cartera de destino', $exception->getMessage());
        }
        $this->assertDatabaseHas('importaciones', ['id' => $import, 'estado' => 'preparada']);
        self::assertSame($before, DB::table('importaciones')->where('id', $import)->value('esquema'));
        $this->assertDatabaseHas('importacion_filas', ['importacion_id' => $import, 'estado' => 'pendiente']);
        $this->assertDatabaseHas('casos_cobranza', ['caso_id' => $case, 'saldo_total' => 100]);
        Queue::assertNothingPushed();
    }

    public function test_two_argument_compatibility_uses_authorized_uploader_on_its_allowed_destination(): void
    {
        [$project, $allowed, $denied, $actor] = $this->scenario();
        $import = $this->prepare($project, $allowed, $actor, ModoImportacion::INSERT);
        app(EncolarImportacion::class)->execute($import, ModoImportacion::INSERT);

        $row = DB::table('importaciones')->where('id', $import)->first();
        $schema = EsquemaImportacion::deserializar($row->esquema);
        self::assertSame('procesando', $row->estado);
        self::assertTrue($schema->reincorporarArchivadas);
        self::assertSame((int) $actor->id, $schema->autorizadoPorId);
        Queue::assertPushed(EjecutarImportacionJob::class, fn ($job) => $job->importacionId === $import);
    }

    public function test_default_uploader_must_still_be_active_and_belong_to_the_project(): void
    {
        [$project, $allowed, $denied, $actor] = $this->scenario();
        $foreign = $this->crearSupervisor($this->crearProyectoCobranza());
        DB::table('users')->where('id', $actor->id)->update(['activo' => false]);
        foreach ([$actor, $foreign] as $unauthorized) {
            $import = $this->prepare($project, $allowed, $unauthorized, ModoImportacion::INSERT);
            try {
                app(EncolarImportacion::class)->execute($import, ModoImportacion::INSERT);
                self::fail('The uploader fallback is still subject to current authorization.');
            } catch (ImportacionNoProcesable $exception) {
                self::assertStringContainsString('No tienes permiso', $exception->getMessage());
            }
            $this->assertDatabaseHas('importaciones', ['id' => $import, 'estado' => 'preparada']);
        }
        Queue::assertNothingPushed();
    }

    public function test_person_import_requires_project_processing_permission_without_a_portfolio(): void
    {
        [$project, $allowed, $denied, $supervisor] = $this->scenario();
        $accepted = $this->prepare($project, null, $supervisor, ModoImportacion::UPSERT, TargetImportacion::PERSONA);
        app(EncolarImportacion::class)->execute($accepted, ModoImportacion::UPSERT);
        $this->assertDatabaseHas('importaciones', ['id' => $accepted, 'estado' => 'procesando']);
        $rejected = $this->prepare($project, null, $this->crearGestor($project), ModoImportacion::UPSERT, TargetImportacion::PERSONA);
        try {
            app(EncolarImportacion::class)->execute($rejected, ModoImportacion::UPSERT);
            self::fail('A person import still requires import processing permission.');
        } catch (ImportacionNoProcesable $exception) {
            self::assertStringContainsString('No tienes permiso', $exception->getMessage());
        }
        $this->assertDatabaseHas('importaciones', ['id' => $rejected, 'estado' => 'preparada']);
        Queue::assertPushed(EjecutarImportacionJob::class, 1);
    }

    public function test_portfolio_selector_uses_the_import_permission_scope_even_with_an_unrestricted_other_role(): void
    {
        [$project, $allowed, $denied, $actor] = $this->scenario();
        DB::table('usuario_proyecto_rol')->insert(['usuario_id' => $actor->id, 'proyecto_id' => $project->id,
            'rol_id' => DB::table('roles')->where('codigo', 'GESTOR')->value('id'), 'activo' => true]);
        $this->activarProyecto($project);
        $this->actingAs($actor);
        Livewire::test(Importar::class)->set('targetValor', TargetImportacion::CASO_COBRANZA->value)
            ->assertViewHas('carteras', fn ($rows) => $rows->pluck('id')->map(fn ($id) => (int) $id)->all() === [(int) $allowed->id]);
    }

    private function scenario(): array
    {
        $project = $this->crearProyectoCobranza();
        $allowed = $this->crearCarteraEn($project);
        $denied = $this->crearCarteraEn($project);
        $actor = $this->crearSupervisor($project);
        DB::table('usuario_proyecto_rol_cartera')->insert(['usuario_id' => $actor->id, 'proyecto_id' => $project->id,
            'rol_id' => DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'), 'cartera_id' => $allowed->id]);

        return [$project, $allowed, $denied, $actor];
    }

    private function prepare(stdClass $project, ?stdClass $portfolio, User $uploader, ModoImportacion $mode, TargetImportacion $target = TargetImportacion::CASO_COBRANZA): int
    {
        $schema = new EsquemaImportacion($target, (int) $project->id, $portfolio === null ? null : (int) $portfolio->id, $mode, [
            new ColumnaExcel('IDENTITY', TipoCampo::TEXTO_CORTO, 'identificacion', true, false, AccionColumna::MAPEAR_SISTEMA),
        ]);
        $id = (int) DB::table('importaciones')->insertGetId(['public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id,
            'tipo_entidad' => $target->value, 'estado' => 'preparada', 'modo' => $mode->value, 'usuario_id' => $uploader->id,
            'nombre_archivo' => 'scoped.csv', 'total_filas' => 1, 'esquema' => $schema->serializar()]);
        DB::table('importacion_filas')->insert(['importacion_id' => $id, 'proyecto_id' => $project->id, 'numero_fila' => 1,
            'estado' => 'pendiente', 'payload' => json_encode(['identificacion' => 'IMPORT-IDENTITY', 'id_cpelegido' => $mode === ModoImportacion::INSERT ? 'NEW-ACTIVE' : 'PRESERVE-ACTIVE'])]);

        return $id;
    }
}
