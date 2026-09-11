<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Casos\Application\UseCases\ReincorporarCuenta;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class AutomaticReincorporationAuthorizationTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public static function importModes(): iterable
    {
        foreach (ModoImportacion::cases() as $mode) {
            yield $mode->value => [$mode];
        }
    }

    #[DataProvider('importModes')]
    public function test_import_supervisor_can_restore_without_portfolio_edit_permission_and_preserves_owner(ModoImportacion $mode): void
    {
        [$project, $source, $destination, $case, $owner, $actor, $import] = $this->scenario($mode);
        self::assertFalse($actor->tienePermiso('carteras.editar', (int) $project->id));
        app(ReincorporarCuenta::class)->execute((int) $project->id, $case, (int) $destination->id, $import, (int) $actor->id);

        $this->assertDatabaseHas('casos', ['id' => $case, 'proyecto_id' => $project->id, 'cartera_id' => $destination->id]);
        $this->assertDatabaseHas('asignaciones', ['caso_id' => $case, 'proyecto_id' => $project->id, 'usuario_id' => $owner->id]);
        $this->assertDatabaseHas('carteras', ['id' => $source->id, 'activo' => false]);
        self::assertSame(1, DB::table('asignaciones')->where('caso_id', $case)->count());
        self::assertSame(1, DB::table('casos_cobranza')->where('proyecto_id', $project->id)->where('numero_prestamo', 'AUTOMATIC-DEBT')->count());
        $movement = DB::table('caso_cartera_movimientos')->where('caso_id', $case)->sole();
        $snapshot = json_decode($movement->instantanea, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame((int) $owner->id, (int) $movement->asesor_anterior_id);
        self::assertSame((int) $actor->id, (int) $movement->usuario_id);
        self::assertSame($owner->name, $snapshot['asesor_nombre']);
        self::assertEquals(100, $snapshot['saldo_total']);
    }

    public function test_destination_only_import_scope_cannot_extract_archived_source_history(): void
    {
        [$project, $source, $destination, $case, $owner, $actor, $import] = $this->scenario(ModoImportacion::INSERT);
        DB::table('usuario_proyecto_rol_cartera')->insert(['usuario_id' => $actor->id, 'proyecto_id' => $project->id,
            'rol_id' => DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'), 'cartera_id' => $destination->id]);

        try {
            app(ReincorporarCuenta::class)->execute((int) $project->id, $case, (int) $destination->id, $import, (int) $actor->id);
            self::fail('Source import permission must also be required.');
        } catch (DomainException $exception) {
            self::assertStringContainsString('No tienes permiso', $exception->getMessage());
            $this->assertDatabaseHas('casos', ['id' => $case, 'cartera_id' => $source->id]);
            $this->assertDatabaseHas('asignaciones', ['caso_id' => $case, 'usuario_id' => $owner->id]);
            $this->assertDatabaseMissing('caso_cartera_movimientos', ['caso_id' => $case]);
        }
    }

    public function test_automatic_import_still_requires_server_persisted_authorization_and_actor(): void
    {
        [$project, $source, $destination, $case, $owner, $actor, $import] = $this->scenario(ModoImportacion::SKIP_DUPLICADOS);
        $authorized = json_decode(DB::table('importaciones')->where('id', $import)->value('esquema'), true, flags: JSON_THROW_ON_ERROR);
        foreach ([['reincorporar_archivadas' => false], ['autorizado_por_id' => $owner->id]] as $tampering) {
            DB::table('importaciones')->where('id', $import)->update(['esquema' => json_encode(array_replace($authorized, $tampering), JSON_THROW_ON_ERROR)]);
            try {
                app(ReincorporarCuenta::class)->execute((int) $project->id, $case, (int) $destination->id, $import, (int) $actor->id);
                self::fail('Persisted import authorization cannot be bypassed.');
            } catch (DomainException $exception) {
                self::assertStringContainsString('La importación no autoriza', $exception->getMessage());
            }
        }
        $this->assertDatabaseHas('casos', ['id' => $case, 'cartera_id' => $source->id]);
        $this->assertDatabaseMissing('caso_cartera_movimientos', ['caso_id' => $case]);
    }

    private function scenario(ModoImportacion $mode): array
    {
        $project = $this->crearProyectoCobranza();
        $source = $this->crearCarteraEn($project);
        $destination = $this->crearCarteraEn($project);
        $person = $this->crearPersonaEn($project, 'AUTOMATIC-IDENTITY');
        $case = $this->crearCasoEn($project, ['persona' => $person, 'cartera' => $source]);
        DB::table('casos_cobranza')->insert(['caso_id' => $case, 'proyecto_id' => $project->id,
            'numero_prestamo' => 'AUTOMATIC-DEBT', 'moneda' => 'USD', 'saldo_total' => 100]);
        DB::table('carteras')->where('id', $source->id)->update(['activo' => false, 'eliminada_en' => now()]);
        $owner = $this->crearGestor($project);
        $actor = $this->crearSupervisor($project);
        DB::table('asignaciones')->insert(['public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id,
            'caso_id' => $case, 'usuario_id' => $owner->id, 'fecha_asignacion' => '2026-09-01']);
        $schema = new EsquemaImportacion(TargetImportacion::CASO_COBRANZA, (int) $project->id, (int) $destination->id, $mode, [
            new ColumnaExcel('IDENTITY', TipoCampo::TEXTO_CORTO, 'identificacion', true, false, AccionColumna::MAPEAR_SISTEMA),
        ], true, (int) $actor->id);
        $import = (int) DB::table('importaciones')->insertGetId(['public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id,
            'tipo_entidad' => 'caso_cobranza', 'estado' => 'procesando', 'modo' => $mode->value, 'usuario_id' => $actor->id,
            'nombre_archivo' => 'automatic-authorization.csv', 'esquema' => $schema->serializar()]);

        return [$project, $source, $destination, $case, $owner, $actor, $import];
    }
}
