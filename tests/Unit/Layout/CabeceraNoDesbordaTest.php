<?php

declare(strict_types=1);

namespace Tests\Unit\Layout;

use PHPUnit\Framework\TestCase;

/**
 * La cabecera no puede ensanchar la rejilla del layout.
 *
 * `.app` es una rejilla y en móvil su única pista es `1fr`, o sea
 * `minmax(auto, 1fr)`: el mínimo de la pista es el min-content de sus ítems.
 * `.app-main` no aporta nada porque tiene `overflow` y su mínimo automático es
 * cero. `.app-header` sí, y entre la miga de pan y el menú de usuario pedía
 * 458px en un móvil de 390. La pista se ensanchaba y arrastraba el documento:
 * el scroll horizontal de las 39 pantallas salía de esa única declaración
 * ausente.
 *
 * Medido con las dos: sin `min-width: 0` el documento mide 458 en un viewport
 * de 390; con ella, 386 de 386. Y el desbordamiento variaba entre 431 y 542
 * según el largo del nombre del proyecto y del usuario, así que no era un caso
 * de datos largos sino estructural.
 *
 * Esta prueba no mide el layout —para eso hace falta un navegador— sino que
 * vigila que nadie borre la declaración sin enterarse de para qué estaba.
 */
final class CabeceraNoDesbordaTest extends TestCase
{
    private const CSS = __DIR__.'/../../../resources/css/app.css';

    public function test_la_cabecera_declara_min_width_cero(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.app-header\s*\{[^}]*min-width:\s*0/s',
            $this->css(),
            '.app-header necesita min-width: 0 o vuelve a ensanchar la rejilla y el documento entero',
        );
    }

    public function test_la_miga_de_pan_trunca_en_vez_de_empujar(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression('/\.breadcrumb\s*\{[^}]*min-width:\s*0/s', $css);
        $this->assertMatchesRegularExpression('/\.breadcrumb\s*\{[^}]*overflow:\s*hidden/s', $css);
        $this->assertStringContainsString('.breadcrumb > * { min-width: 0; }', $css);
    }

    /** El nombre del usuario y su rol pedían 192 de los 390px disponibles. */
    public function test_el_menu_de_usuario_esconde_el_nombre_en_movil(): void
    {
        $nav = (string) file_get_contents(__DIR__.'/../../../resources/views/livewire/layout/navigation.blade.php');

        $this->assertStringContainsString('hidden lg:block', $nav);
    }

    private function css(): string
    {
        $this->assertFileExists(self::CSS);

        return (string) file_get_contents(self::CSS);
    }
}
