<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\Cobranza\Domain\Exceptions\DatosCasoCobranzaInvalidos;
use App\Modules\Contactos\Domain\Exceptions\DatosContactoInvalidos;
use App\Modules\Importaciones\Application\Services\DescriptorDeFalloImportacion;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionNoProcesable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use PDOException;
use RuntimeException;
use Tests\TestCase;

/**
 * Extiende el TestCase de Laravel y no el de PHPUnit porque el descriptor es
 * el único que escribe al log y aquí se comprueba QUÉ escribe.
 */
final class DescriptorDeFalloImportacionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    public function test_una_excepcion_de_dominio_ajena_no_ensena_el_dato_que_interpola(): void
    {
        $fallo = (new DescriptorDeFalloImportacion)->describir(new DatosContactoInvalidos('Correo inválido: a@b.c'));

        self::assertStringNotContainsString('a@b.c', $fallo->motivo);
        self::assertStringContainsString('Fallo interno (DatosContactoInvalidos)', $fallo->motivo);
        self::assertStringContainsString('Referencia '.$fallo->referencia, $fallo->motivo);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $fallo->referencia);
    }

    public function test_un_fallo_de_base_de_datos_no_deja_los_datos_ni_en_el_motivo_ni_en_el_log(): void
    {
        $excepcion = $this->queryExceptionConDatos();

        $fallo = (new DescriptorDeFalloImportacion)->describir($excepcion, ['importacion_id' => 24, 'proyecto_id' => 8, 'lote' => 0]);

        self::assertSame(
            "La base de datos rechazó el lote (SQLSTATE 23000, error 1062). Referencia {$fallo->referencia}.",
            $fallo->motivo,
        );
        self::assertStringNotContainsString('8-990-429', $fallo->motivo);
        self::assertStringNotContainsString('ALEXIS', $fallo->motivo);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $mensaje, array $contexto) use ($fallo): bool {
                $volcado = json_encode($contexto, JSON_THROW_ON_ERROR);

                return str_contains($mensaje, $fallo->referencia)
                    && $contexto['referencia'] === $fallo->referencia
                    && $contexto['excepcion'] === QueryException::class
                    && $contexto['importacion_id'] === 24
                    && $contexto['proyecto_id'] === 8
                    && str_contains((string) $contexto['sql'], '?')
                    && ! str_contains($volcado, '8-990-429')
                    && ! str_contains($volcado, 'ALEXIS');
            });
    }

    public function test_una_excepcion_apta_para_pantalla_pasa_tal_cual_y_no_se_loguea(): void
    {
        $excepcion = ImportacionNoProcesable::enEstado(EstadoImportacion::CANCELADA);

        $fallo = (new DescriptorDeFalloImportacion)->describir($excepcion);

        self::assertSame($excepcion->getMessage(), $fallo->motivo);
        Log::shouldNotHaveReceived('error');
    }

    public function test_el_mensaje_de_una_excepcion_cualquiera_va_al_log_redactado(): void
    {
        $excepcion = new RuntimeException("Duplicate entry 'JUANA PEREZ' for key 12345678 (tel 60001234)");

        $fallo = (new DescriptorDeFalloImportacion)->describir($excepcion);

        self::assertSame("Fallo interno (RuntimeException). Referencia {$fallo->referencia}.", $fallo->motivo);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $mensaje, array $contexto): bool {
                return $contexto['mensaje'] === 'Duplicate entry … for key # (tel #)';
            });
    }

    public function test_por_fila_la_base_de_datos_tampoco_ensena_la_fila(): void
    {
        $motivo = (new DescriptorDeFalloImportacion)->motivoDeFila($this->queryExceptionConDatos());

        self::assertSame('La base de datos rechazó la fila (SQLSTATE 23000, error 1062).', $motivo);
    }

    public function test_por_fila_los_value_objects_conservan_su_mensaje_para_corregir_la_celda(): void
    {
        $descriptor = new DescriptorDeFalloImportacion;

        self::assertSame(
            'Los días de mora (99999) superan los 40 años; casi siempre es una fecha mal leída en el archivo.',
            $descriptor->motivoDeFila(new DatosCasoCobranzaInvalidos('Los días de mora (99999) superan los 40 años; casi siempre es una fecha mal leída en el archivo.')),
        );
        self::assertSame('Fallo interno (RuntimeException).', $descriptor->motivoDeFila(new RuntimeException('lo que sea con datos')));
        self::assertSame(200, mb_strlen($descriptor->motivoDeFila(new DatosCasoCobranzaInvalidos(str_repeat('x', 300)))));
    }

    private function queryExceptionConDatos(): QueryException
    {
        $pdo = new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '8-990-429' for key 'personas_unique'");
        $pdo->errorInfo = ['23000', 1062, "Duplicate entry '8-990-429' for key 'personas_unique'"];

        return new QueryException(
            'mysql',
            'insert into `personas` (`identificacion`, `nombres`) values (?, ?)',
            ['8-990-429', 'ALEXIS SANTOS'],
            $pdo,
        );
    }
}
