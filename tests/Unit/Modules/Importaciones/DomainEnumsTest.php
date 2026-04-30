<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\Importaciones\Domain\Enums\EstadoFila;
use App\Modules\Importaciones\Domain\Enums\EstadoImportacion;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionEnCursoNoEditable;
use App\Modules\Importaciones\Domain\Exceptions\ImportacionNoEncontrada;
use App\Modules\Importaciones\Domain\ValueObjects\ResultadoFila;
use PHPUnit\Framework\TestCase;

final class DomainEnumsTest extends TestCase
{
    public function test_modo_aplica_a_persona_y_casos(): void
    {
        self::assertTrue(ModoImportacion::MERGE->aplicaA('persona'));
        self::assertTrue(ModoImportacion::OVERWRITE->aplicaA('caso_cobranza'));
        self::assertTrue(ModoImportacion::SKIP_DUPLICADOS->aplicaA('caso_servicio'));
        self::assertFalse(ModoImportacion::MERGE->aplicaA('contacto'));
    }

    public function test_modo_actualiza_existente(): void
    {
        self::assertTrue(ModoImportacion::MERGE->actualizaExistente());
        self::assertTrue(ModoImportacion::OVERWRITE->actualizaExistente());
        self::assertFalse(ModoImportacion::SKIP_DUPLICADOS->actualizaExistente());
    }

    public function test_modo_pisa_campos_llenos_solo_overwrite(): void
    {
        self::assertTrue(ModoImportacion::OVERWRITE->pisaCamposLlenos());
        self::assertFalse(ModoImportacion::MERGE->pisaCamposLlenos());
        self::assertFalse(ModoImportacion::SKIP_DUPLICADOS->pisaCamposLlenos());
    }

    public function test_estado_terminal(): void
    {
        self::assertTrue(EstadoImportacion::COMPLETADA->esTerminal());
        self::assertTrue(EstadoImportacion::FALLIDA->esTerminal());
        self::assertTrue(EstadoImportacion::CANCELADA->esTerminal());
        self::assertFalse(EstadoImportacion::PENDIENTE->esTerminal());
        self::assertFalse(EstadoImportacion::PROCESANDO->esTerminal());
    }

    public function test_solo_preparada_puede_encolarse(): void
    {
        self::assertTrue(EstadoImportacion::PREPARADA->puedeEncolarse());
        self::assertFalse(EstadoImportacion::PENDIENTE->puedeEncolarse());
        self::assertFalse(EstadoImportacion::PROCESANDO->puedeEncolarse());
    }

    public function test_resultado_fila_factories(): void
    {
        $proc = ResultadoFila::procesada(42);
        self::assertSame(EstadoFila::PROCESADA, $proc->estado);
        self::assertSame(42, $proc->entidadId);
        self::assertNull($proc->razon);

        $dup = ResultadoFila::duplicada('ya existe');
        self::assertSame(EstadoFila::DUPLICADA, $dup->estado);
        self::assertSame('ya existe', $dup->razon);

        $inv = ResultadoFila::invalida('campo X obligatorio');
        self::assertSame(EstadoFila::INVALIDA, $inv->estado);

        $om = ResultadoFila::omitida('cancelada por usuario');
        self::assertSame(EstadoFila::OMITIDA, $om->estado);
    }

    public function test_excepciones_construyen_mensaje(): void
    {
        $a = ImportacionNoEncontrada::conId(7);
        self::assertStringContainsString('7', $a->getMessage());

        $b = ImportacionEnCursoNoEditable::estado(EstadoImportacion::PROCESANDO);
        self::assertStringContainsString('procesando', $b->getMessage());
    }
}
