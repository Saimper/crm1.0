<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Reportes\Domain;

use App\Modules\Reportes\Domain\Constructor\Catalogo\CatalogoCamposReporte;
use App\Modules\Reportes\Domain\Constructor\Entities\DefinicionReporte;
use App\Modules\Reportes\Domain\Constructor\Enums\Agregacion;
use App\Modules\Reportes\Domain\Constructor\Enums\EntidadRaiz;
use App\Modules\Reportes\Domain\Constructor\Enums\OperadorFiltro;
use App\Modules\Reportes\Domain\Constructor\Enums\TipoCampoReporte;
use App\Modules\Reportes\Domain\Constructor\Exceptions\AgregacionRequiereAgrupacion;
use App\Modules\Reportes\Domain\Constructor\Exceptions\CampoNoPermitidoEnReporte;
use App\Modules\Reportes\Domain\Constructor\Exceptions\DefinicionReporteInvalida;
use App\Modules\Reportes\Domain\Constructor\Exceptions\OperadorIncompatibleConTipo;
use App\Modules\Reportes\Domain\Constructor\ValueObjects\ColumnaReporte;
use App\Modules\Reportes\Domain\Constructor\ValueObjects\FiltroReporte;
use App\Modules\Reportes\Domain\Constructor\ValueObjects\OrdenReporte;
use PHPUnit\Framework\TestCase;

final class ConstructorDomainTest extends TestCase
{
    public function test_tipo_campo_acepta_operadores_compatibles(): void
    {
        self::assertTrue(TipoCampoReporte::TEXTO->aceptaOperador(OperadorFiltro::CONTIENE));
        self::assertFalse(TipoCampoReporte::TEXTO->aceptaOperador(OperadorFiltro::MAYOR));
        self::assertTrue(TipoCampoReporte::DECIMAL->aceptaOperador(OperadorFiltro::ENTRE));
        self::assertFalse(TipoCampoReporte::BOOLEANO->aceptaOperador(OperadorFiltro::CONTIENE));
        self::assertTrue(TipoCampoReporte::FECHA->aceptaOperador(OperadorFiltro::MENOR));
    }

    public function test_operador_metadatos(): void
    {
        self::assertTrue(OperadorFiltro::ENTRE->requiereDosValores());
        self::assertTrue(OperadorFiltro::EN_LISTA->requiereLista());
        self::assertFalse(OperadorFiltro::VACIO->requiereValor());
        self::assertSame('like', OperadorFiltro::CONTIENE->aSqlOperador());
    }

    public function test_agregacion_requiere_numerico_solo_sum_avg(): void
    {
        self::assertTrue(Agregacion::SUM->requiereTipoNumerico());
        self::assertTrue(Agregacion::AVG->requiereTipoNumerico());
        self::assertFalse(Agregacion::COUNT->requiereTipoNumerico());
        self::assertFalse(Agregacion::MIN->requiereTipoNumerico());
        self::assertFalse(Agregacion::MAX->requiereTipoNumerico());
    }

    public function test_catalogo_casos_contiene_campos_clave(): void
    {
        $cat = new CatalogoCamposReporte(EntidadRaiz::CASOS);
        self::assertTrue($cat->tiene('casos.public_id'));
        self::assertTrue($cat->tiene('casos.persona.nombres'));
        self::assertTrue($cat->tiene('casos.cobranza.saldo_total'));
        self::assertFalse($cat->tiene('casos.persona.password'));
    }

    public function test_catalogo_lanza_si_campo_no_permitido(): void
    {
        $cat = new CatalogoCamposReporte(EntidadRaiz::CASOS);
        $this->expectException(CampoNoPermitidoEnReporte::class);
        $cat->obtener("'; DROP TABLE casos; --");
    }

    public function test_catalogo_joins_solo_para_keys_predeclaradas(): void
    {
        $cat = new CatalogoCamposReporte(EntidadRaiz::CASOS);
        $joins = $cat->joinsPara(['estado', 'cobranza', 'inventado']);
        self::assertCount(2, $joins);
        self::assertSame('estados_caso', $joins[0]['tabla']);
        self::assertSame('casos_cobranza', $joins[1]['tabla']);
    }

    public function test_definicion_valida_un_reporte_simple(): void
    {
        $cat = new CatalogoCamposReporte(EntidadRaiz::CASOS);
        $def = new DefinicionReporte(
            proyectoId: 1,
            codigo: 'casos_basicos',
            nombre: 'Casos básicos',
            entidad: EntidadRaiz::CASOS,
            columnas: [
                new ColumnaReporte('casos.public_id', 'ID'),
                new ColumnaReporte('casos.persona.nombres', 'Nombre'),
            ],
            filtros: [
                new FiltroReporte('casos.tipo_caso', OperadorFiltro::IGUAL, 'cobranza'),
            ],
            orden: [new OrdenReporte('casos.fecha_ingreso', 'desc')],
        );
        $def->validar($cat);
        self::assertSame(EntidadRaiz::CASOS, $def->entidad);
    }

    public function test_definicion_falla_sin_columnas(): void
    {
        $cat = new CatalogoCamposReporte(EntidadRaiz::CASOS);
        $this->expectException(DefinicionReporteInvalida::class);
        (new DefinicionReporte(1, 'x', 'X', EntidadRaiz::CASOS, []))->validar($cat);
    }

    public function test_definicion_falla_codigo_vacio(): void
    {
        $cat = new CatalogoCamposReporte(EntidadRaiz::CASOS);
        $this->expectException(DefinicionReporteInvalida::class);
        (new DefinicionReporte(
            1, '', 'X', EntidadRaiz::CASOS,
            [new ColumnaReporte('casos.public_id', 'ID')],
        ))->validar($cat);
    }

    public function test_definicion_falla_filtro_operador_incompatible(): void
    {
        $cat = new CatalogoCamposReporte(EntidadRaiz::CASOS);
        $this->expectException(OperadorIncompatibleConTipo::class);
        (new DefinicionReporte(
            1, 'x', 'X', EntidadRaiz::CASOS,
            [new ColumnaReporte('casos.public_id', 'ID')],
            [new FiltroReporte('casos.tiene_compromiso_vigente', OperadorFiltro::CONTIENE, 'x')],
        ))->validar($cat);
    }

    public function test_definicion_falla_agregacion_sin_agrupacion(): void
    {
        $cat = new CatalogoCamposReporte(EntidadRaiz::CASOS);
        $this->expectException(AgregacionRequiereAgrupacion::class);
        (new DefinicionReporte(
            1, 'x', 'X', EntidadRaiz::CASOS,
            [new ColumnaReporte('casos.cobranza.saldo_total', 'Suma', Agregacion::SUM)],
        ))->validar($cat);
    }

    public function test_definicion_acepta_agregacion_con_agrupacion(): void
    {
        $cat = new CatalogoCamposReporte(EntidadRaiz::CASOS);
        $def = new DefinicionReporte(
            proyectoId: 1,
            codigo: 'casos_por_estado',
            nombre: 'Casos por estado',
            entidad: EntidadRaiz::CASOS,
            columnas: [
                new ColumnaReporte('casos.estado.codigo', 'Estado'),
                new ColumnaReporte('casos.cobranza.saldo_total', 'Total', Agregacion::SUM),
            ],
            agrupaciones: ['casos.estado.codigo'],
        );
        $def->validar($cat);
        self::assertCount(2, $def->columnas);
    }

    public function test_columna_y_filtro_serializan_round_trip(): void
    {
        $col = new ColumnaReporte('casos.public_id', 'ID', Agregacion::COUNT);
        $arr = $col->toArray();
        $col2 = ColumnaReporte::fromArray($arr);
        self::assertSame($col->campo, $col2->campo);
        self::assertSame($col->agregacion, $col2->agregacion);

        $f = new FiltroReporte('casos.tipo_caso', OperadorFiltro::IGUAL, 'cobranza');
        $f2 = FiltroReporte::fromArray($f->toArray());
        self::assertSame('casos.tipo_caso', $f2->campo);
        self::assertSame(OperadorFiltro::IGUAL, $f2->operador);
        self::assertSame('cobranza', $f2->valor);
    }
}
