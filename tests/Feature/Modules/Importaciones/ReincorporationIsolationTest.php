<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Casos\Application\UseCases\ReincorporarCuenta;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionDinamica;
use App\Modules\Importaciones\Application\UseCases\EjecutarImportacionInput;
use App\Modules\Importaciones\Application\UseCases\ProcesarFilaDinamica;
use App\Modules\Importaciones\Application\UseCases\ProcesarFilaInput;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\EstadoFila;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\Exceptions\FalloDeImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class ReincorporationIsolationTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_worker_rejects_schema_project_different_from_import_owner(): void
    {
        [$p, $source, $destination, $person, $case, $actor, $import, $row, $schema] = $this->scenario();
        $other = $this->crearProyectoCobranza();
        $portfolio = $this->crearCarteraEn($other);
        $forged = new EsquemaImportacion($schema->target, (int) $other->id, (int) $portfolio->id, $schema->modo, $schema->columnas, true, (int) $actor->id);
        DB::table('importaciones')->where('id', $import)->update(['esquema' => $forged->serializar()]);
        try {
            app(EjecutarImportacionDinamica::class)->execute(new EjecutarImportacionInput($import));
            self::fail('The mismatched project must be rejected.');
        } catch (FalloDeImportacion) {
            $this->assertDatabaseHas('casos', ['id' => $case, 'cartera_id' => $source->id]);
            self::assertSame(0, DB::table('caso_cartera_movimientos')->count());
            $this->assertDatabaseHas('importacion_filas', ['id' => $row, 'estado' => 'pendiente']);
        }
    }

    public function test_direct_row_processing_rolls_back_transfer_when_a_later_value_is_invalid(): void
    {
        [$p, $source, $destination, $person, $case, $actor, $import, $row, $schema] = $this->scenario();
        try {
            app(ProcesarFilaDinamica::class)->execute(new ProcesarFilaInput(
                ['id_cpelegido' => 'REF-ONE', 'identificacion' => $person->identificacion, 'saldo_total' => 'not-a-number'],
                $schema, $row, [], DB::table('tipos_identificacion')->pluck('id', 'codigo')->all(),
            ));
            self::fail('The malformed balance must be rejected.');
        } catch (DomainException|InvalidArgumentException) {
            $this->assertDatabaseHas('casos', ['id' => $case, 'cartera_id' => $source->id]);
            self::assertSame(0, DB::table('caso_cartera_movimientos')->count());
            self::assertEquals(100, DB::table('casos_cobranza')->where('caso_id', $case)->value('saldo_total'));
        }
    }

    public function test_identity_conflict_never_moves_the_account(): void
    {
        [$p, $source, $destination, $person, $case, $actor, $import, $row, $schema] = $this->scenario();
        $otherPerson = $this->crearPersonaEn($p, 'OTHER-IDENTITY');
        $result = app(ProcesarFilaDinamica::class)->execute(new ProcesarFilaInput(
            ['id_cpelegido' => 'REF-ONE', 'identificacion' => $otherPerson->identificacion, 'saldo_total' => '200.00'],
            $schema, $row, [], DB::table('tipos_identificacion')->pluck('id', 'codigo')->all(),
        ));
        self::assertSame(EstadoFila::INVALIDA, $result->resultadoFila->estado);
        $this->assertDatabaseHas('casos', ['id' => $case, 'cartera_id' => $source->id, 'persona_id' => $person->id]);
        self::assertSame(0, DB::table('caso_cartera_movimientos')->count());
    }

    public function test_transfer_cannot_use_an_import_authorized_for_another_destination(): void
    {
        [$p, $source, $destination, $person, $case, $actor, $import] = $this->scenario();
        $third = $this->crearCarteraEn($p);
        $this->expectException(DomainException::class);
        app(ReincorporarCuenta::class)->execute((int) $p->id, $case, (int) $third->id, $import, (int) $actor->id);
    }

    public function test_worker_does_not_process_a_foreign_project_row_linked_to_its_import(): void
    {
        [$p, $source, $destination, $person, $case, $actor, $import, $row] = $this->scenario();
        $other = $this->crearProyectoCobranza();
        DB::table('importacion_filas')->where('id', $row)->update(['proyecto_id' => $other->id]);
        $result = app(EjecutarImportacionDinamica::class)->execute(new EjecutarImportacionInput($import));
        self::assertSame(0, $result['procesadas']);
        $this->assertDatabaseHas('importacion_filas', ['id' => $row, 'proyecto_id' => $other->id, 'estado' => 'pendiente']);
        $this->assertDatabaseHas('casos', ['id' => $case, 'cartera_id' => $source->id]);
    }

    private function scenario(): array
    {
        $p = $this->crearProyectoCobranza();
        $source = $this->crearCarteraEn($p);
        $destination = $this->crearCarteraEn($p);
        $person = $this->crearPersonaEn($p, 'ORIGINAL-IDENTITY');
        $case = $this->crearCasoEn($p, ['cartera' => $source, 'persona' => $person]);
        DB::table('casos_cobranza')->insert(['proyecto_id' => $p->id, 'caso_id' => $case, 'numero_prestamo' => 'REF-ONE', 'moneda' => 'USD', 'saldo_total' => 100]);
        DB::table('carteras')->where('id', $source->id)->update(['activo' => false, 'eliminada_en' => now()]);
        $actor = $this->crearAdminGlobal();
        $schema = new EsquemaImportacion(TargetImportacion::CASO_COBRANZA, (int) $p->id, (int) $destination->id, ModoImportacion::UPSERT, [
            new ColumnaExcel('ID', TipoCampo::TEXTO_CORTO, 'identificacion', true, false, AccionColumna::MAPEAR_SISTEMA),
            new ColumnaExcel('BALANCE', TipoCampo::NUMERO_DECIMAL, 'saldo_total', false, false, AccionColumna::MAPEAR_SISTEMA),
        ], true, (int) $actor->id);
        $import = (int) DB::table('importaciones')->insertGetId(['public_id' => (string) Str::ulid(), 'proyecto_id' => $p->id,
            'tipo_entidad' => 'caso_cobranza', 'estado' => 'preparada', 'modo' => 'upsert', 'usuario_id' => $actor->id,
            'nombre_archivo' => 'isolated.csv', 'total_filas' => 1, 'esquema' => $schema->serializar()]);
        $row = (int) DB::table('importacion_filas')->insertGetId(['proyecto_id' => $p->id, 'importacion_id' => $import,
            'numero_fila' => 1, 'estado' => 'pendiente', 'payload' => json_encode(['id_cpelegido' => 'REF-ONE', 'identificacion' => $person->identificacion, 'saldo_total' => '200.00'])]);

        return [$p, $source, $destination, $person, $case, $actor, $import, $row, $schema];
    }
}
