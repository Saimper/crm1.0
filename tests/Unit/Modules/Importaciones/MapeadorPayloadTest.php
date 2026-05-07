<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\Importaciones\Application\Services\MapeadorPayload;
use PHPUnit\Framework\TestCase;

final class MapeadorPayloadTest extends TestCase
{
    public function test_payload_canonico_traduce_columnas_libres_a_keys_sistema(): void
    {
        $headers = ['ced', 'nom', 'ape'];
        $fila = ['100', 'Ana', 'Diaz'];
        $mapeo = [
            'identificacion' => 'ced',
            'nombres' => 'nom',
            'apellidos' => 'ape',
        ];

        $out = (new MapeadorPayload)->aPayloadCanonico($headers, $fila, $mapeo);

        $this->assertSame([
            'identificacion' => '100',
            'nombres' => 'Ana',
            'apellidos' => 'Diaz',
        ], $out);
    }

    public function test_campos_sin_mapeo_no_aparecen_en_payload(): void
    {
        $headers = ['ced', 'nom'];
        $fila = ['100', 'Ana'];
        $mapeo = [
            'identificacion' => 'ced',
            'nombres' => '',
            'apellidos' => '',
        ];

        $out = (new MapeadorPayload)->aPayloadCanonico($headers, $fila, $mapeo);
        $this->assertSame(['identificacion' => '100'], $out);
    }

    public function test_columna_inexistente_se_ignora(): void
    {
        $headers = ['ced'];
        $fila = ['100'];
        $mapeo = [
            'identificacion' => 'ced',
            'nombres' => 'columna_que_no_existe',
        ];

        $out = (new MapeadorPayload)->aPayloadCanonico($headers, $fila, $mapeo);
        $this->assertSame(['identificacion' => '100'], $out);
    }

    public function test_valores_son_trim(): void
    {
        $headers = ['nom'];
        $fila = ['  Ana  '];
        $mapeo = ['nombres' => 'nom'];

        $out = (new MapeadorPayload)->aPayloadCanonico($headers, $fila, $mapeo);
        $this->assertSame(['nombres' => 'Ana'], $out);
    }

    public function test_auto_mapear_match_exacto_case_insensitive(): void
    {
        $headers = ['Identificacion', 'NOMBRES', 'apellidos'];
        $codigos = ['identificacion', 'nombres', 'apellidos'];

        $mapeo = (new MapeadorPayload)->autoMapear($codigos, $headers);

        $this->assertSame([
            'identificacion' => 'Identificacion',
            'nombres' => 'NOMBRES',
            'apellidos' => 'apellidos',
        ], $mapeo);
    }

    public function test_auto_mapear_normaliza_separadores(): void
    {
        $headers = ['tipo identificacion codigo', 'numero-prestamo', 'fecha desembolso'];
        $codigos = ['tipo_identificacion_codigo', 'numero_prestamo', 'fecha_desembolso'];

        $mapeo = (new MapeadorPayload)->autoMapear($codigos, $headers);

        $this->assertSame('tipo identificacion codigo', $mapeo['tipo_identificacion_codigo']);
        $this->assertSame('numero-prestamo', $mapeo['numero_prestamo']);
        $this->assertSame('fecha desembolso', $mapeo['fecha_desembolso']);
    }

    public function test_auto_mapear_sin_match_devuelve_vacio(): void
    {
        $headers = ['xx', 'yy'];
        $codigos = ['identificacion', 'nombres'];

        $mapeo = (new MapeadorPayload)->autoMapear($codigos, $headers);
        $this->assertSame(['identificacion' => '', 'nombres' => ''], $mapeo);
    }
}
