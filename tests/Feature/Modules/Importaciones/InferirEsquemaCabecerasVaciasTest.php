<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Importaciones;

use App\Modules\Importaciones\Application\UseCases\InferirEsquemaDesdeHeaders;
use App\Modules\Importaciones\Application\UseCases\InferirEsquemaInput;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use Tests\TestCase;

/**
 * Una celda de cabecera en blanco no se propone como campo personalizado.
 *
 * Los lectores la bautizan «columna» («columna_2», …). Proponerla como campo
 * bloqueaba la carga semanal a quien no puede crear campos, por dos celdas
 * vacías que nadie quería en la ficha.
 */
final class InferirEsquemaCabecerasVaciasTest extends TestCase
{
    public function test_una_cabecera_vacia_se_ignora_por_defecto(): void
    {
        $salida = app(InferirEsquemaDesdeHeaders::class)->execute(new InferirEsquemaInput(
            headers: ['CEDULA', 'columna', 'TIPIFICACION', 'columna_2'],
            filasMuestra: [['CEDULA' => '8-1-1', 'columna' => 'x', 'TIPIFICACION' => 'A', 'columna_2' => 'y']],
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 1,
        ));

        $acciones = [];
        foreach ($salida->columnas as $columna) {
            $acciones[$columna->nombreOriginal] = $columna->accion;
        }

        self::assertSame(AccionColumna::IGNORAR, $acciones['columna']);
        self::assertSame(AccionColumna::IGNORAR, $acciones['columna_2']);
        self::assertSame(AccionColumna::CREAR_CP, $acciones['TIPIFICACION'], 'Una columna con nombre real sigue proponiéndose como campo.');
    }
}
