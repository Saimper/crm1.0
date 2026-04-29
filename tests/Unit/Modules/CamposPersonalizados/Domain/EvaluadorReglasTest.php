<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\CamposPersonalizados\Domain;

use App\Modules\CamposPersonalizados\Domain\Exceptions\ReglaViolada;
use App\Modules\CamposPersonalizados\Domain\Services\EvaluadorReglas;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use PHPUnit\Framework\TestCase;

final class EvaluadorReglasTest extends TestCase
{
    private EvaluadorReglas $evaluador;

    protected function setUp(): void
    {
        $this->evaluador = new EvaluadorReglas();
    }

    public function test_obligatorio_con_valor_vacio_throws(): void
    {
        $this->expectException(ReglaViolada::class);
        $this->evaluador->validar(TipoCampo::TEXTO_CORTO, '', [], true, 'Nombre');
    }

    public function test_opcional_con_valor_vacio_ok(): void
    {
        $this->evaluador->validar(TipoCampo::TEXTO_CORTO, '', [], false, 'Nombre');
        $this->addToAssertionCount(1);
    }

    public function test_texto_respeta_longitudes(): void
    {
        $this->evaluador->validar(TipoCampo::TEXTO_CORTO, 'abcde', ['longitud_min' => 3, 'longitud_max' => 10], false, 'X');
        $this->addToAssertionCount(1);

        $this->expectException(ReglaViolada::class);
        $this->evaluador->validar(TipoCampo::TEXTO_CORTO, 'ab', ['longitud_min' => 3], false, 'X');
    }

    public function test_texto_respeta_regex(): void
    {
        $this->evaluador->validar(TipoCampo::TEXTO_CORTO, 'PL-123456', ['regex' => '^PL-\d{6}$'], false, 'Plan');
        $this->addToAssertionCount(1);

        $this->expectException(ReglaViolada::class);
        $this->evaluador->validar(TipoCampo::TEXTO_CORTO, 'PL-12', ['regex' => '^PL-\d{6}$'], false, 'Plan');
    }

    public function test_numero_entero_rechaza_decimales(): void
    {
        $this->expectException(ReglaViolada::class);
        $this->evaluador->validar(TipoCampo::NUMERO_ENTERO, '12.5', [], false, 'N');
    }

    public function test_numero_entero_respeta_min_max(): void
    {
        $this->evaluador->validar(TipoCampo::NUMERO_ENTERO, 50, ['min' => 0, 'max' => 100], false, 'N');
        $this->addToAssertionCount(1);

        $this->expectException(ReglaViolada::class);
        $this->evaluador->validar(TipoCampo::NUMERO_ENTERO, 200, ['max' => 100], false, 'N');
    }

    public function test_numero_decimal_acepta_float(): void
    {
        $this->evaluador->validar(TipoCampo::NUMERO_DECIMAL, 3.14, ['min' => 0], false, 'pi');
        $this->addToAssertionCount(1);
    }

    public function test_fecha_valida_rango(): void
    {
        $this->evaluador->validar(TipoCampo::FECHA, '2026-06-15', ['min' => '2026-01-01', 'max' => '2026-12-31'], false, 'F');
        $this->addToAssertionCount(1);

        $this->expectException(ReglaViolada::class);
        $this->evaluador->validar(TipoCampo::FECHA, '2027-01-01', ['max' => '2026-12-31'], false, 'F');
    }

    public function test_booleano_acepta_valores_diversos(): void
    {
        $this->evaluador->validar(TipoCampo::BOOLEANO, true, [], false, 'B');
        $this->evaluador->validar(TipoCampo::BOOLEANO, 1, [], false, 'B');
        $this->evaluador->validar(TipoCampo::BOOLEANO, '1', [], false, 'B');
        $this->addToAssertionCount(3);

        $this->expectException(ReglaViolada::class);
        $this->evaluador->validar(TipoCampo::BOOLEANO, 'quizás', [], false, 'B');
    }

    public function test_seleccion_multiple_rechaza_valor_no_lista(): void
    {
        $this->expectException(ReglaViolada::class);
        $this->evaluador->validar(TipoCampo::SELECCION_MULTIPLE, 'no_lista', [], false, 'S');
    }

    public function test_seleccion_multiple_acepta_lista_de_ids(): void
    {
        $this->evaluador->validar(TipoCampo::SELECCION_MULTIPLE, [1, 2, 3], [], false, 'S');
        $this->addToAssertionCount(1);
    }
}
