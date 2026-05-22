<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use PHPUnit\Framework\TestCase;

final class ColumnaExcelTest extends TestCase
{
    public function test_codigo_sugerido_convierte_a_snake_case(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: 'Saldo Actual',
            tipoInferido: TipoCampo::NUMERO_DECIMAL,
        );

        self::assertSame('saldo_actual', $col->codigoSugerido());
    }

    public function test_codigo_sugerido_limpia_caracteres_especiales(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: 'CÉDULA/NIT',
            tipoInferido: TipoCampo::TEXTO_CORTO,
        );

        self::assertSame('cedula_nit', $col->codigoSugerido());
    }

    public function test_codigo_sugerido_trunca_a_60_chars(): void
    {
        $nombreLargo = str_repeat('abcdefghij', 8);

        $col = new ColumnaExcel(
            nombreOriginal: $nombreLargo,
            tipoInferido: TipoCampo::TEXTO_CORTO,
        );

        self::assertLessThanOrEqual(60, strlen($col->codigoSugerido()));
    }

    public function test_codigo_sugerido_sin_caracteres_vacios_extremos(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: '__saldo__actual__',
            tipoInferido: TipoCampo::NUMERO_DECIMAL,
        );

        self::assertSame('saldo_actual', $col->codigoSugerido());
    }

    public function test_codigo_sugerido_con_guiones(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: 'numero-prestamo',
            tipoInferido: TipoCampo::TEXTO_CORTO,
        );

        self::assertSame('numero_prestamo', $col->codigoSugerido());
    }

    public function test_codigo_sugerido_con_tildes_y_enie(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: 'Dirección de envío',
            tipoInferido: TipoCampo::TEXTO_LARGO,
        );

        self::assertSame('direccion_de_envio', $col->codigoSugerido());
    }

    public function test_codigo_sugerido_con_numeros(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: 'cartera_2026_v1',
            tipoInferido: TipoCampo::TEXTO_CORTO,
        );

        self::assertSame('cartera_2026_v1', $col->codigoSugerido());
    }

    public function test_etiqueta_sugerida_aplica_ucwords(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: 'saldo_actual',
            tipoInferido: TipoCampo::NUMERO_DECIMAL,
        );

        self::assertSame('Saldo Actual', $col->etiquetaSugerida());
    }

    public function test_etiqueta_sugerida_con_guiones(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: 'numero-prestamo',
            tipoInferido: TipoCampo::TEXTO_CORTO,
        );

        self::assertSame('Número Préstamo', $col->etiquetaSugerida());
    }

    public function test_es_campo_de_sistema_retorna_true_cuando_mapeado(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: 'identificacion',
            tipoInferido: TipoCampo::TEXTO_CORTO,
            campoSistemaMapeado: 'identificacion',
        );

        self::assertTrue($col->esCampoDeSistema());
    }

    public function test_es_campo_de_sistema_retorna_false_cuando_no_mapeado(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: 'saldo_extra',
            tipoInferido: TipoCampo::NUMERO_DECIMAL,
        );

        self::assertFalse($col->esCampoDeSistema());
    }

    public function test_debe_persistirse_retorna_false_cuando_ignorar(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: 'columna_basura',
            tipoInferido: TipoCampo::TEXTO_CORTO,
            accion: AccionColumna::IGNORAR,
        );

        self::assertFalse($col->debePersistirse());
    }

    public function test_debe_persistirse_retorna_true_cuando_crear_cp(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: 'saldo_deuda',
            tipoInferido: TipoCampo::NUMERO_DECIMAL,
            accion: AccionColumna::CREAR_CP,
        );

        self::assertTrue($col->debePersistirse());
    }

    public function test_debe_persistirse_retorna_true_cuando_mapear_sistema(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: 'identificacion',
            tipoInferido: TipoCampo::TEXTO_CORTO,
            campoSistemaMapeado: 'identificacion',
            accion: AccionColumna::MAPEAR_SISTEMA,
        );

        self::assertTrue($col->debePersistirse());
    }

    public function test_accion_default_es_ignorar(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: 'test',
            tipoInferido: TipoCampo::TEXTO_CORTO,
        );

        self::assertSame(AccionColumna::IGNORAR, $col->accion);
    }

    public function test_es_identificador_persona_default_false(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: 'cedula',
            tipoInferido: TipoCampo::TEXTO_CORTO,
        );

        self::assertFalse($col->esIdentificadorPersona);
    }

    public function test_es_identificador_persona_puede_ser_true(): void
    {
        $col = new ColumnaExcel(
            nombreOriginal: 'cedula',
            tipoInferido: TipoCampo::TEXTO_CORTO,
            esIdentificadorPersona: true,
        );

        self::assertTrue($col->esIdentificadorPersona);
    }
}
