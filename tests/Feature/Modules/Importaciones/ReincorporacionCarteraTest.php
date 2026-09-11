<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Casos\Application\UseCases\ReincorporarCuenta;
use App\Modules\Importaciones\Application\Services\ConsultaCoincidenciasImportacion;
use App\Modules\Importaciones\Application\Services\DescriptorDeFalloImportacion;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionDinamica;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionInput;
use App\Modules\Importaciones\Application\UseCases\EncolarImportacion;
use App\Modules\Importaciones\Domain\Contracts\CampoPersonalizadoImportacionRepository;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Importaciones\Infrastructure\Jobs\EjecutarImportacionJob;
use App\Modules\Tenancy\Domain\Contracts\RegionalConfiguration;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ReincorporacionCarteraTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_direct_processing_without_server_authorization_preserves_the_archived_account(): void
    {
        [$project, $source, $destination, $person, $case, $admin] = $this->scenario();
        $id = $this->prepare($project->id, $destination->id, $admin->id, $person->identificacion, false);
        $summary = app(ConsultaCoincidenciasImportacion::class)->execute($project->id, $id);
        $this->assertSame(1, $summary['archivadas']);
        $this->assertSame(1, $summary['nuevas']);
        app(EjecutarImportacionDinamica::class)->execute(new EjecutarImportacionInput($id));
        $this->assertDatabaseHas('importaciones', ['id' => $id, 'estado' => 'completada', 'invalidas' => 1, 'omitidas' => 0, 'insertadas' => 1]);
        $this->assertDatabaseHas('casos', ['id' => $case, 'cartera_id' => $source->id]);
        $this->assertSame(0, DB::table('caso_cartera_movimientos')->count());
        $this->assertStringContainsString('autorización de procesamiento válida', (string) DB::table('importacion_filas')->where('importacion_id', $id)->where('numero_fila', 1)->value('mensaje_error'));
    }

    public function test_normal_enqueue_automatically_preserves_identity_owner_and_history_when_reinstating(): void
    {
        [$project, $source, $destination, $person, $case, $admin] = $this->scenario();
        $advisor = $this->crearGestor($project);
        DB::table('asignaciones')->insert(['public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id,
            'caso_id' => $case, 'usuario_id' => $advisor->id, 'fecha_asignacion' => '2026-09-01', 'prioridad' => 1, 'estado' => 'pendiente']);
        $casePublicId = DB::table('casos')->where('id', $case)->value('public_id');
        $cascade = $this->crearCascadaGestionEn($project);
        $interaction = DB::table('gestiones')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id, 'caso_id' => $case, 'persona_id' => $person->id,
            'canal_id' => $cascade['canal_id'], 'tipo_gestion_id' => $cascade['tipo_gestion_id'],
            'resultado_id' => $cascade['resultado_id'], 'usuario_id' => $advisor->id, 'notas' => 'Original activity',
        ]);
        $commitment = DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $project->id, 'caso_id' => $case,
            'tipo_compromiso' => 'promesa_pago', 'estado' => 'pendiente', 'fecha_vencimiento' => '2026-09-15', 'usuario_id' => $advisor->id,
        ]);
        $id = $this->prepare($project->id, $destination->id, $admin->id, $person->identificacion, false);
        $this->activarProyecto($project);
        $this->actingAs($admin);
        $this->enqueueAndRun($id, ModoImportacion::UPSERT, $admin->id);
        $this->assertDatabaseHas('importaciones', ['id' => $id, 'estado' => 'completada', 'actualizadas' => 1, 'insertadas' => 1]);
        $this->assertDatabaseHas('casos', ['id' => $case, 'public_id' => $casePublicId, 'cartera_id' => $destination->id, 'persona_id' => $person->id]);
        $this->assertDatabaseHas('asignaciones', ['caso_id' => $case, 'usuario_id' => $advisor->id]);
        $this->assertDatabaseHas('gestiones', ['id' => $interaction, 'caso_id' => $case, 'usuario_id' => $advisor->id, 'notas' => 'Original activity']);
        $this->assertDatabaseHas('compromisos', ['id' => $commitment, 'caso_id' => $case, 'usuario_id' => $advisor->id]);
        $movement = DB::table('caso_cartera_movimientos')->sole();
        $this->assertSame($source->id, $movement->cartera_origen_id);
        $snapshot = json_decode($movement->instantanea, true, flags: JSON_THROW_ON_ERROR);
        $this->assertEquals(100, $snapshot['saldo_total']);
        $this->assertSame($advisor->name, $snapshot['asesor_nombre']);
        $this->assertSame($interaction, $snapshot['last_gestion_id']);
        $this->assertSame($commitment, $snapshot['last_compromiso_id']);
        $this->assertSame(1, DB::table('casos_cobranza')->where('proyecto_id', $project->id)->where('numero_prestamo', 'EXISTING')->count());
    }

    #[DataProvider('automaticModes')]
    public function test_every_import_mode_reinstates_archived_or_inactive_matches_automatically(ModoImportacion $mode, bool $deleted): void
    {
        [$project, $source, $destination, $person, $case, $admin] = $this->scenario();
        DB::table('carteras')->where('id', $source->id)->update([
            'activo' => $deleted, 'eliminada_en' => $deleted ? now() : null,
        ]);
        $publicId = DB::table('casos')->where('id', $case)->value('public_id');
        $id = $this->prepare($project->id, $destination->id, $admin->id, $person->identificacion, false);
        DB::table('importacion_filas')->where('importacion_id', $id)->where('numero_fila', 2)->delete();
        DB::table('importaciones')->where('id', $id)->update(['total_filas' => 1]);
        $this->activarProyecto($project);
        $this->actingAs($admin);
        $this->enqueueAndRun($id, $mode, $admin->id);

        $this->assertDatabaseHas('importaciones', ['id' => $id, 'estado' => 'completada', 'procesadas' => 1,
            'actualizadas' => 1, 'insertadas' => 0, 'invalidas' => 0, 'omitidas' => 0, 'duplicadas' => 0]);
        $this->assertDatabaseHas('importacion_filas', ['importacion_id' => $id, 'estado' => 'procesada', 'entidad_id' => $case]);
        $this->assertDatabaseHas('casos', ['id' => $case, 'public_id' => $publicId, 'persona_id' => $person->id, 'cartera_id' => $destination->id]);
        $this->assertEquals($mode === ModoImportacion::MERGE ? 100 : 250.12, DB::table('casos_cobranza')->where('caso_id', $case)->value('saldo_total'));
        $this->assertDatabaseCount('casos', 1);
        $this->assertDatabaseCount('personas', 1);
        $this->assertDatabaseCount('caso_cartera_movimientos', 1);

        // A delivered job retry must not repeat the movement or insert a second debt.
        (new EjecutarImportacionJob($id))->handle(app(EjecutarImportacionDinamica::class), app(DescriptorDeFalloImportacion::class));
        $this->assertDatabaseCount('casos', 1);
        $this->assertDatabaseCount('caso_cartera_movimientos', 1);
    }

    /** @return iterable<string, array{ModoImportacion, bool}> */
    public static function automaticModes(): iterable
    {
        foreach (ModoImportacion::cases() as $mode) {
            yield $mode->value.' / archived portfolio' => [$mode, true];
            yield $mode->value.' / inactive portfolio' => [$mode, false];
        }
    }

    #[DataProvider('mergeContexts')]
    public function test_merge_preserves_filled_destination_custom_fields_and_fills_empty_values_once(bool $reinstating): void
    {
        [$project, $source, $destination, $person, $case, $admin] = $this->scenario();
        if (! $reinstating) {
            DB::table('carteras')->where('id', $source->id)->update(['activo' => true, 'eliminada_en' => null]);
            $destination = $source;
        }
        $repository = app(CampoPersonalizadoImportacionRepository::class);
        $fields = [];
        $columns = [];
        foreach ([
            'filled' => TipoCampo::TEXTO_CORTO,
            'empty' => TipoCampo::TEXTO_CORTO,
            'zero' => TipoCampo::NUMERO_ENTERO,
            'flag' => TipoCampo::BOOLEANO,
            'cash' => TipoCampo::MONEDA,
        ] as $code => $type) {
            $fields[$code] = $repository->crearCampo($project->id, $destination->id, $code, $code, $type);
            $columns[] = new ColumnaExcel(strtoupper($code), $type, accion: AccionColumna::CREAR_CP);
        }
        foreach ([
            'filled' => ['valor_texto_corto' => 'Keep original'],
            'empty' => ['valor_texto_corto' => ''],
            'zero' => ['valor_numero_entero' => 0],
            'flag' => ['valor_booleano' => false],
            'cash' => ['valor_moneda_monto' => null, 'valor_moneda_codigo' => 'USD'],
        ] as $code => $stored) {
            DB::table('valores_campo_personalizado')->insert([
                'campo_personalizado_id' => $fields[$code], 'entidad_id' => $case, ...$stored,
            ]);
        }
        if ($reinstating) {
            $sourceField = $repository->crearCampo($project->id, $source->id, 'filled', 'Old source field', TipoCampo::TEXTO_CORTO);
            DB::table('valores_campo_personalizado')->insert([
                'campo_personalizado_id' => $sourceField, 'entidad_id' => $case, 'valor_texto_corto' => 'Historical source value',
            ]);
        }
        $importId = $this->prepare($project->id, $destination->id, $admin->id, $person->identificacion, false);
        $original = EsquemaImportacion::deserializar(DB::table('importaciones')->where('id', $importId)->value('esquema'));
        $schema = new EsquemaImportacion($original->target, $original->proyectoId, $original->carteraId,
            ModoImportacion::MERGE, [...$original->columnas, ...$columns]);
        DB::table('importaciones')->where('id', $importId)->update(['esquema' => $schema->serializar()]);
        foreach ([1 => ['First fill', '12.34'], 2 => ['Second fill', '99.99']] as $number => [$emptyText, $amount]) {
            DB::table('importacion_filas')->where('importacion_id', $importId)->where('numero_fila', $number)->update([
                'payload' => json_encode([
                    'id_cpelegido' => 'EXISTING', 'identificacion' => $person->identificacion, 'saldo_total' => '250.12',
                    'filled' => 'Would overwrite', 'empty' => $emptyText, 'zero' => '5', 'flag' => 'true', 'cash' => $amount,
                ], JSON_THROW_ON_ERROR),
            ]);
        }
        $this->activarProyecto($project);
        $this->actingAs($admin);
        $this->enqueueAndRun($importId, ModoImportacion::MERGE, $admin->id);
        $this->assertDatabaseHas('importaciones', ['id' => $importId, 'estado' => 'completada', 'actualizadas' => 2, 'invalidas' => 0]);
        foreach ([
            'filled' => ['valor_texto_corto' => 'Keep original'],
            'empty' => ['valor_texto_corto' => 'First fill'],
            'zero' => ['valor_numero_entero' => 0],
            'flag' => ['valor_booleano' => false],
            'cash' => ['valor_moneda_monto' => '12.34', 'valor_moneda_codigo' => 'USD'],
        ] as $code => $expected) {
            $this->assertDatabaseHas('valores_campo_personalizado', [
                'campo_personalizado_id' => $fields[$code], 'entidad_id' => $case, ...$expected,
            ]);
        }
        if ($reinstating) {
            $this->assertDatabaseHas('valores_campo_personalizado', [
                'campo_personalizado_id' => $sourceField, 'entidad_id' => $case, 'valor_texto_corto' => 'Historical source value',
            ]);
        }
        $this->assertDatabaseHas('casos', ['id' => $case, 'cartera_id' => $destination->id]);
        $this->assertDatabaseCount('caso_cartera_movimientos', $reinstating ? 1 : 0);
    }

    /** @return iterable<string, array{bool}> */
    public static function mergeContexts(): iterable
    {
        yield 'active account' => [false];
        yield 'returning account' => [true];
    }

    public function test_invalid_money_rolls_back_transfer_and_only_rejects_that_row(): void
    {
        [$project, $source, $destination, $person, $case, $admin] = $this->scenario();
        $id = $this->prepare($project->id, $destination->id, $admin->id, $person->identificacion, true, '12.345');
        app(EjecutarImportacionDinamica::class)->execute(new EjecutarImportacionInput($id));
        $this->assertDatabaseHas('importaciones', ['id' => $id, 'estado' => 'completada', 'invalidas' => 1, 'insertadas' => 1]);
        $this->assertDatabaseHas('casos', ['id' => $case, 'cartera_id' => $source->id]);
        $this->assertSame(0, DB::table('caso_cartera_movimientos')->count());
    }

    public function test_other_active_portfolio_is_not_duplicated_moved_or_overwritten(): void
    {
        [$project, $source, $destination, $person, $case, $admin] = $this->scenario();
        DB::table('carteras')->where('id', $source->id)->update(['activo' => true, 'eliminada_en' => null]);
        $id = $this->prepare($project->id, $destination->id, $admin->id, $person->identificacion, true);
        app(EjecutarImportacionDinamica::class)->execute(new EjecutarImportacionInput($id));
        $this->assertDatabaseHas('importaciones', ['id' => $id, 'omitidas' => 1, 'insertadas' => 1]);
        $this->assertDatabaseHas('casos', ['id' => $case, 'cartera_id' => $source->id]);
        $this->assertEquals(100, DB::table('casos_cobranza')->where('caso_id', $case)->value('saldo_total'));
    }

    public function test_transfer_cannot_cross_projects(): void
    {
        [$project, $source, $destination, $person, $case, $admin] = $this->scenario();
        $other = $this->crearProyectoCobranza();
        $foreign = $this->crearCarteraEn($other);
        $id = $this->prepare($project->id, $destination->id, $admin->id, $person->identificacion, true);
        $this->expectException(DomainException::class);
        app(ReincorporarCuenta::class)->execute($project->id, $case, $foreign->id, $id, $admin->id);
    }

    public function test_project_input_preserves_three_decimals_and_uses_day_month_dates(): void
    {
        $this->assertRegionalImport('proyecto', '1.234,567', '75,12', '10/09/2026');
    }

    public function test_standard_input_keeps_spreadsheet_numbers_unambiguous_in_a_comma_project(): void
    {
        $this->assertRegionalImport('estandar', '1234.567', '75.12', '2026-09-10');
    }

    private function assertRegionalImport(string $format, string $amount, string $secondAmount, string $date): void
    {
        [$project, $source, $destination, $person, $case, $admin] = $this->scenario();
        DB::table('carteras')->where('id', $source->id)->update(['activo' => true, 'eliminada_en' => null]);
        DB::table('proyectos')->where('id', $project->id)->update([
            'decimales' => 3, 'separador_decimal' => ',', 'separador_miles' => '.', 'formato_fecha' => 'd/m/Y',
        ]);
        app(RegionalConfiguration::class)->clear();
        $id = $this->prepare($project->id, $source->id, $admin->id, $person->identificacion, false, $amount);
        $original = EsquemaImportacion::deserializar(DB::table('importaciones')->where('id', $id)->value('esquema'));
        $schema = new EsquemaImportacion($original->target, $original->proyectoId, $original->carteraId, $original->modo,
            [...$original->columnas, new ColumnaExcel('VENCIMIENTO', TipoCampo::FECHA, 'fecha_vencimiento', false, false, AccionColumna::MAPEAR_SISTEMA)],
            formatoEntrada: $format);
        DB::table('importaciones')->where('id', $id)->update(['esquema' => $schema->serializar()]);
        foreach (DB::table('importacion_filas')->where('importacion_id', $id)->get() as $row) {
            $payload = json_decode($row->payload, true);
            $payload['fecha_vencimiento'] = $date;
            if ($payload['id_cpelegido'] === 'NEW') {
                $payload['saldo_total'] = $secondAmount;
            }
            DB::table('importacion_filas')->where('id', $row->id)->update(['payload' => json_encode($payload)]);
        }
        app(EjecutarImportacionDinamica::class)->execute(new EjecutarImportacionInput($id));
        $this->assertSame(0, (int) DB::table('importaciones')->where('id', $id)->value('invalidas'), DB::table('importacion_filas')->where('importacion_id', $id)->pluck('mensaje_error')->toJson());
        $this->assertDatabaseHas('importaciones', ['id' => $id, 'estado' => 'completada', 'invalidas' => 0, 'actualizadas' => 1, 'insertadas' => 1]);
        $cti = DB::table('casos_cobranza')->where('caso_id', $case)->first();
        $this->assertSame('1234.567', $cti->saldo_total);
        $this->assertSame('2026-09-10', $cti->fecha_vencimiento);
    }

    private function scenario(): array
    {
        $project = $this->crearProyectoCobranza();
        $source = $this->crearCarteraEn($project);
        $destination = $this->crearCarteraEn($project);
        $person = $this->crearPersonaEn($project, 'TEST-IDENTITY');
        $case = $this->crearCasoEn($project, ['cartera' => $source, 'persona' => $person]);
        DB::table('casos_cobranza')->insert(['caso_id' => $case, 'proyecto_id' => $project->id,
            'numero_prestamo' => 'EXISTING', 'saldo_total' => 100, 'moneda' => 'USD']);
        DB::table('carteras')->where('id', $source->id)->update(['activo' => false, 'eliminada_en' => now()]);

        return [$project, $source, $destination, $person, $case, $this->crearAdminGlobal()];
    }

    private function enqueueAndRun(int $importId, ModoImportacion $mode, int $actorId): void
    {
        Queue::fake();
        app(EncolarImportacion::class)->execute($importId, $mode, usuarioId: $actorId);
        Queue::assertPushed(EjecutarImportacionJob::class, fn ($job) => $job->importacionId === $importId);
        $schema = EsquemaImportacion::deserializar(DB::table('importaciones')->where('id', $importId)->value('esquema'));
        $this->assertTrue($schema->reincorporarArchivadas);
        $this->assertSame($actorId, $schema->autorizadoPorId);
        (new EjecutarImportacionJob($importId))->handle(app(EjecutarImportacionDinamica::class), app(DescriptorDeFalloImportacion::class));
    }

    private function prepare(int $projectId, int $portfolioId, int $actorId, string $identity, bool $transfer, string $amount = '250.12'): int
    {
        $schema = new EsquemaImportacion(TargetImportacion::CASO_COBRANZA, $projectId, $portfolioId, ModoImportacion::UPSERT, [
            new ColumnaExcel('IDENTIFICACION', TipoCampo::TEXTO_CORTO, 'identificacion', true, false, AccionColumna::MAPEAR_SISTEMA),
            new ColumnaExcel('SALDO', TipoCampo::NUMERO_DECIMAL, 'saldo_total', false, false, AccionColumna::MAPEAR_SISTEMA),
        ], $transfer, $transfer ? $actorId : null);
        $id = DB::table('importaciones')->insertGetId(['public_id' => (string) Str::ulid(), 'proyecto_id' => $projectId,
            'tipo_entidad' => 'caso_cobranza', 'estado' => 'preparada', 'modo' => 'upsert', 'usuario_id' => $actorId,
            'nombre_archivo' => 'synthetic.csv', 'total_filas' => 2, 'esquema' => $schema->serializar()]);
        foreach (['EXISTING' => $amount, 'NEW' => '75.12'] as $reference => $balance) {
            DB::table('importacion_filas')->insert(['proyecto_id' => $projectId, 'importacion_id' => $id,
                'numero_fila' => $reference === 'EXISTING' ? 1 : 2, 'estado' => 'pendiente',
                'payload' => json_encode(['id_cpelegido' => $reference, 'identificacion' => $identity, 'saldo_total' => $balance], JSON_THROW_ON_ERROR)]);
        }

        return $id;
    }
}
