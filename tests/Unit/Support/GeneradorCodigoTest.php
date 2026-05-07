<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Codigo\GeneradorCodigo;
use RuntimeException;
use Tests\TestCase;

final class GeneradorCodigoTest extends TestCase
{
    public function test_derivar_upper_snake_default(): void
    {
        $this->assertSame('COBRANZA_VEHICULAR_2024', GeneradorCodigo::derivar('Cobranza Vehicular 2024'));
    }

    public function test_derivar_normaliza_acentos_y_amp(): void
    {
        $this->assertSame('NINOS_NINAS', GeneradorCodigo::derivar('Niños & niñas'));
    }

    public function test_derivar_string_vacio_genera_fallback(): void
    {
        $codigo = GeneradorCodigo::derivar('');
        $this->assertStringStartsWith('COD_', $codigo);
        $this->assertSame(10, strlen($codigo));
    }

    public function test_derivar_string_solo_simbolos_genera_fallback(): void
    {
        $codigo = GeneradorCodigo::derivar('***');
        $this->assertStringStartsWith('COD_', $codigo);
    }

    public function test_derivar_lowercase_cuando_minusculas_true(): void
    {
        $this->assertSame('cobranza_vehicular_2024', GeneradorCodigo::derivar('Cobranza Vehicular 2024', 50, true));
    }

    public function test_derivar_truncate_respeta_maxlen(): void
    {
        $codigo = GeneradorCodigo::derivar(str_repeat('abc ', 30), 10);
        $this->assertLessThanOrEqual(10, strlen($codigo));
    }

    public function test_normalizar_convierte_guion_a_underscore(): void
    {
        $this->assertSame('CRM_2024', GeneradorCodigo::normalizar('CRM-2024'));
    }

    public function test_normalizar_trim_y_uppercase(): void
    {
        $this->assertSame('TEST', GeneradorCodigo::normalizar('  test  '));
    }

    public function test_normalizar_lowercase_cuando_minusculas_true(): void
    {
        $this->assertSame('cobranza_test', GeneradorCodigo::normalizar('Cobranza_Test', 50, true));
    }

    public function test_regla_validacion_acepta_vacio(): void
    {
        $reglas = GeneradorCodigo::reglaValidacion();
        $this->assertContains('nullable', $reglas);
    }

    public function test_regla_validacion_regex_acepta_input_tolerante(): void
    {
        $regex = collect(GeneradorCodigo::reglaValidacion())->first(fn ($r) => str_starts_with($r, 'regex:'));
        $this->assertNotNull($regex);
        $pattern = substr($regex, strlen('regex:'));

        $this->assertSame(1, preg_match($pattern, ''));
        $this->assertSame(1, preg_match($pattern, 'CRM_2024'));
        $this->assertSame(1, preg_match($pattern, 'abc-DEF'));
        $this->assertSame(1, preg_match($pattern, 'CRM 2024'));
        $this->assertSame(0, preg_match($pattern, 'CRM/2024'));
        $this->assertSame(0, preg_match($pattern, 'CRM@2024'));
    }

    public function test_resolver_conflicto_devuelve_original_si_libre(): void
    {
        $existe = fn (string $c): bool => false;
        $this->assertSame('TEST', GeneradorCodigo::resolverConflicto('TEST', $existe));
    }

    public function test_resolver_conflicto_sufija_hasta_libre(): void
    {
        $tomados = ['TEST' => true, 'TEST_2' => true, 'TEST_3' => true];
        $existe = fn (string $c): bool => isset($tomados[$c]);

        $this->assertSame('TEST_4', GeneradorCodigo::resolverConflicto('TEST', $existe));
    }

    public function test_resolver_conflicto_lanza_si_supera_99(): void
    {
        $existe = fn (string $c): bool => true;
        $this->expectException(RuntimeException::class);
        GeneradorCodigo::resolverConflicto('FULL', $existe);
    }
}
