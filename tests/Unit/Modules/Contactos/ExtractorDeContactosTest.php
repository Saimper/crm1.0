<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Contactos;

use App\Modules\Contactos\Domain\ValueObjects\ExtractorDeContactos;
use App\Modules\Contactos\Domain\ValueObjects\TipoContacto;
use PHPUnit\Framework\TestCase;

/**
 * Sacar contactos de las celdas de texto libre que trae la importación.
 *
 * Todos los ejemplos de aquí salen de la réplica de producción, no de la
 * imaginación: son las tres formas que aparecen de verdad y la basura que
 * aparece con ellas.
 */
final class ExtractorDeContactosTest extends TestCase
{
    private ExtractorDeContactos $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new ExtractorDeContactos;
    }

    /** Forma 1: un valor por columna (`tel_1`..`tel_5` de los proyectos grandes). */
    public function test_un_numero_solo(): void
    {
        $contactos = $this->extractor->telefonos('65500723');

        $this->assertCount(1, $contactos);
        $this->assertSame('65500723', $contactos[0]->valor);
        $this->assertSame(TipoContacto::TELEFONO, $contactos[0]->tipo);
    }

    /** Forma 2: varios en una celda separados por espacios (campo `telefonos`). */
    public function test_varios_numeros_en_una_celda(): void
    {
        $valores = array_map(
            fn ($c): string => $c->valor,
            $this->extractor->telefonos('61750650   65976897   66214303'),
        );

        $this->assertSame(['61750650', '65976897', '66214303'], $valores);
    }

    public function test_no_repite_el_mismo_numero_de_la_misma_celda(): void
    {
        // Caso real: la celda trae el mismo número dos veces.
        $this->assertCount(2, $this->extractor->telefonos('64594305 64747693 64594305'));
    }

    public function test_acepta_fijos_de_siete_digitos(): void
    {
        $this->assertCount(1, $this->extractor->telefonos('3979025'));
    }

    /**
     * Basura que aparece de verdad en la base y no puede convertirse en un
     * contacto: relleno, y cadenas de 9 dígitos que son dos números pegados —
     * adivinar dónde parten sería inventarse un teléfono.
     */
    public function test_descarta_lo_que_no_es_un_telefono(): void
    {
        $this->assertSame([], $this->extractor->telefonos('000000'));
        $this->assertSame([], $this->extractor->telefonos('231573007'));
        $this->assertSame([], $this->extractor->telefonos('270164006'));
        $this->assertSame([], $this->extractor->telefonos('   '));
        $this->assertSame([], $this->extractor->telefonos('SIN TELEFONO'));
    }

    /** Un móvil panameño de 8 dígitos empieza por 6. */
    public function test_descarta_ochos_digitos_que_no_empiezan_por_seis(): void
    {
        $this->assertSame([], $this->extractor->telefonos('12345678'));
        $this->assertCount(1, $this->extractor->telefonos('61234567'));
    }

    /**
     * El prefijo se quita cuando viene pegado al número, que es como aparece.
     * No se intenta recomponer `+507 6575 1234`: el espacio es el separador
     * entre números distintos en las celdas que traen varios, y unirlos rompería
     * el caso frecuente para arreglar uno que no existe en la base.
     */
    public function test_quita_el_prefijo_internacional(): void
    {
        foreach (['+50766754380', '507-6675-4380'] as $bruto) {
            $contactos = $this->extractor->telefonos($bruto);

            $this->assertCount(1, $contactos, $bruto);
            $this->assertSame('66754380', $contactos[0]->valor, $bruto);
        }
    }

    /** Forma 3: nombre y número emparejados (campo `referencias`). */
    public function test_referencias_conservan_el_nombre_de_quien_contesta(): void
    {
        $contactos = $this->extractor->referencias(
            'GUSTAVO GARRIDO(61750650), JEISON  BARRIA (61750651), MIRIAM ANGEL BILL (65976897)'
        );

        $this->assertCount(3, $contactos);
        $this->assertSame('GUSTAVO GARRIDO', $contactos[0]->etiqueta);
        $this->assertSame('61750650', $contactos[0]->valor);
        $this->assertSame('JEISON BARRIA', $contactos[1]->etiqueta, 'los espacios dobles se colapsan');
        $this->assertSame('MIRIAM ANGEL BILL', $contactos[2]->etiqueta);
    }

    public function test_una_referencia_sin_parentesis_se_trata_como_lista_de_telefonos(): void
    {
        $contactos = $this->extractor->referencias('61750650 65976897');

        $this->assertCount(2, $contactos);
        $this->assertNull($contactos[0]->etiqueta);
    }

    /**
     * La etiqueta del campo sólo sirve cuando distingue a un número. En
     * `celular_codeudor` dice de quién es; en `telefonos`, que trae seis en la
     * misma celda, diría «Teléfonos» seis veces.
     */
    public function test_la_etiqueta_del_campo_solo_se_usa_si_hay_un_solo_numero(): void
    {
        $uno = $this->extractor->telefonos('65500723', 'Celular codeudor');
        $this->assertSame('Celular codeudor', $uno[0]->etiqueta);

        $varios = $this->extractor->telefonos('61750650 65976897', 'Telefonos');
        $this->assertNull($varios[0]->etiqueta);
        $this->assertNull($varios[1]->etiqueta);
    }

    public function test_correos(): void
    {
        $contactos = $this->extractor->correos('MaruAmaya2000@Yahoo.com');

        $this->assertCount(1, $contactos);
        $this->assertSame('maruamaya2000@yahoo.com', $contactos[0]->valor, 'se guarda en minúscula');
        $this->assertSame(TipoContacto::CORREO, $contactos[0]->tipo);
    }

    public function test_descarta_correos_que_no_lo_son(): void
    {
        $this->assertSame([], $this->extractor->correos('no-tiene-arroba'));
        $this->assertSame([], $this->extractor->correos('SIN CORREO'));
        $this->assertSame([], $this->extractor->correos(''));
    }

    public function test_varios_correos_en_una_celda(): void
    {
        $this->assertCount(2, $this->extractor->correos('uno@ejemplo.com, dos@ejemplo.com'));
    }

    public function test_la_clave_sirve_para_deduplicar_entre_celdas(): void
    {
        $a = $this->extractor->telefonos('61750650')[0];
        $b = $this->extractor->referencias('ALGUIEN (61750650)')[0];

        $this->assertSame($a->clave(), $b->clave(), 'el mismo número, venga de donde venga, es el mismo contacto');
    }
}
