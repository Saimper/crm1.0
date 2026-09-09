<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CamposPersonalizados;

use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use Tests\TestCase;

/**
 * Cada tipo de campo tiene su control, y hay uno solo que los pinta.
 *
 * El mismo `@switch` estaba copiado en seis blades con tres criterios distintos:
 * en unos `moneda` era `type=text`, en otros `type=number step=0.01`; en unos
 * `booleano` era un `<select>`, en otros un checkbox. Y `seleccion_unica` y
 * `seleccion_multiple` no estaban en ninguno: caían en el `@default`, se
 * escribían a mano como texto y el servicio los guardaba como `0`.
 */
final class ControlPorTipoTest extends TestCase
{
    use InteractsWithViews;

    public function test_cada_tipo_pinta_su_control(): void
    {
        $esperado = [
            'texto_corto' => 'type="text"',
            'texto_largo' => '<textarea',
            'numero_entero' => 'step="1"',
            'numero_decimal' => 'step="0.01"',
            'fecha' => 'type="date"',
            'fecha_hora' => 'type="datetime-local"',
            'booleano' => 'type="checkbox"',
            'moneda' => 'step="0.01"',
            'seleccion_unica' => '<select',
            'seleccion_multiple' => 'multiple',
        ];

        foreach ($esperado as $tipo => $marca) {
            $html = $this->render($tipo);

            $this->assertStringContainsString($marca, $html, "el tipo {$tipo} no pinta su control");
        }
    }

    /**
     * La regla que no se rompe: la máscara vive en la presentación y el modelo
     * lleva el valor crudo. `<input type="date">` lo pinta en el formato del
     * usuario y bindea ISO. Un picker que bindease «05/12/2026» guardaría 12 de
     * mayo sin dar error.
     */
    public function test_la_fecha_usa_el_control_nativo_y_no_un_texto_con_formato(): void
    {
        $html = $this->render('fecha');

        $this->assertStringContainsString('type="date"', $html);
        $this->assertStringNotContainsString('dd/mm', $html);
    }

    /** El símbolo de la divisa va fuera del input o contaminaría el valor guardado. */
    public function test_la_moneda_deja_el_simbolo_fuera_del_input(): void
    {
        $html = $this->render('moneda');

        $this->assertStringContainsString('USD', $html);
        $this->assertMatchesRegularExpression('/<span[^>]*>\s*USD\s*<\/span>/', $html);
        $this->assertStringContainsString('inputmode="decimal"', $html);
    }

    public function test_los_numeros_van_alineados_a_la_derecha(): void
    {
        foreach (['numero_entero', 'numero_decimal', 'moneda'] as $tipo) {
            $this->assertStringContainsString('text-right', $this->render($tipo), $tipo);
        }
    }

    public function test_la_seleccion_unica_lista_las_opciones_del_campo(): void
    {
        $html = $this->render('seleccion_unica', [
            (object) ['id' => 7, 'etiqueta' => 'Mensual'],
            (object) ['id' => 8, 'etiqueta' => 'Quincenal'],
        ]);

        $this->assertStringContainsString('value="7"', $html);
        $this->assertStringContainsString('Mensual', $html);
        $this->assertStringContainsString('Quincenal', $html);
    }

    public function test_en_solo_lectura_el_control_va_deshabilitado(): void
    {
        $html = $this->blade(
            '<x-cp.control :campo="$campo" model="v.x" :disabled="true" />',
            ['campo' => (object) ['tipo' => 'texto_corto', 'codigo' => 'x', 'etiqueta' => 'X']],
        )->__toString();

        $this->assertStringContainsString('disabled', $html);
    }

    /** @param list<object> $opciones */
    private function render(string $tipo, array $opciones = []): string
    {
        return $this->blade(
            '<x-cp.control :campo="$campo" model="v.x" :opciones="$opciones" />',
            [
                'campo' => (object) ['tipo' => $tipo, 'codigo' => 'x', 'etiqueta' => 'X'],
                'opciones' => $opciones,
            ],
        )->__toString();
    }
}
