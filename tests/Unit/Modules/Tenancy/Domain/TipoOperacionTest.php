<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tenancy\Domain;

use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * «Caso» es correcto en el modelo y no significa nada para quien opera: en
 * cobranza es una cuenta, en soporte un ticket, en ventas una oportunidad.
 *
 * Estas pruebas clavan las dos garantías de las que depende toda la rotulación:
 * que el tipo del proyecto elige la palabra, y que un tipo desconocido —o
 * ninguno— cae en «caso» en vez de reventar la pantalla. Extiende
 * `Tests\TestCase` y no el de PHPUnit porque `etiquetaDe()` devuelve texto
 * traducido, y traducir necesita la aplicación levantada.
 */
final class TipoOperacionTest extends TestCase
{
    /** @var array<string, array{0: string, 1: string, 2: string}> singular, plural, artículo */
    private const ESPERADO_ES = [
        'cobranza' => ['cuenta', 'cuentas', 'una'],
        'cx' => ['ticket', 'tickets', 'un'],
        'venta' => ['oportunidad', 'oportunidades', 'una'],
        'servicio' => ['servicio', 'servicios', 'un'],
    ];

    /** @var array<string, array{0: string, 1: string, 2: string}> */
    private const ESPERADO_EN = [
        'cobranza' => ['account', 'accounts', 'an'],
        'cx' => ['ticket', 'tickets', 'a'],
        'venta' => ['opportunity', 'opportunities', 'an'],
        'servicio' => ['service', 'services', 'a'],
    ];

    // -----------------------------------------------------------------
    // Claves de traducción (dominio puro: no decide idioma, decide clave)
    // -----------------------------------------------------------------

    public function test_cada_tipo_expone_sus_tres_claves_de_traduccion(): void
    {
        foreach (TipoOperacion::cases() as $tipo) {
            $this->assertSame("casos.entidad_singular.{$tipo->value}", $tipo->claveEtiqueta());
            $this->assertSame("casos.entidad_plural.{$tipo->value}", $tipo->claveEtiquetaPlural());
            $this->assertSame("casos.entidad_articulo.{$tipo->value}", $tipo->claveArticulo());
        }
    }

    // -----------------------------------------------------------------
    // Español
    // -----------------------------------------------------------------

    public function test_etiquetas_en_espanol_por_tipo(): void
    {
        App::setLocale('es');

        foreach (self::ESPERADO_ES as $tipo => [$singular, $plural, $articulo]) {
            $this->assertSame($singular, TipoOperacion::etiquetaDe($tipo), "singular de {$tipo}");
            $this->assertSame($plural, TipoOperacion::etiquetaDe($tipo, plural: true), "plural de {$tipo}");
            $this->assertSame($articulo, TipoOperacion::articuloDe($tipo), "artículo de {$tipo}");
        }
    }

    public function test_espanol_distingue_el_genero_del_articulo(): void
    {
        App::setLocale('es');

        // El género es del idioma, no del dominio: «una cuenta» pero «un ticket».
        $this->assertSame('una', TipoOperacion::articuloDe('cobranza'));
        $this->assertSame('un', TipoOperacion::articuloDe('cx'));
    }

    // -----------------------------------------------------------------
    // Inglés
    // -----------------------------------------------------------------

    public function test_etiquetas_en_ingles_por_tipo(): void
    {
        App::setLocale('en');

        foreach (self::ESPERADO_EN as $tipo => [$singular, $plural, $articulo]) {
            $this->assertSame($singular, TipoOperacion::etiquetaDe($tipo), "singular de {$tipo}");
            $this->assertSame($plural, TipoOperacion::etiquetaDe($tipo, plural: true), "plural de {$tipo}");
            $this->assertSame($articulo, TipoOperacion::articuloDe($tipo), "artículo de {$tipo}");
        }
    }

    public function test_ingles_distingue_el_articulo_por_sonido(): void
    {
        App::setLocale('en');

        $this->assertSame('an', TipoOperacion::articuloDe('cobranza')); // an account
        $this->assertSame('a', TipoOperacion::articuloDe('cx'));        // a ticket
    }

    // -----------------------------------------------------------------
    // Lo que llega de la base: puede ser null, puede ser basura
    // -----------------------------------------------------------------

    /**
     * Las vistas leen `proyectos.tipo_operacion` como string suelto, no como
     * enum. Fuera de un proyecto activo no hay tipo, y una fila vieja o
     * manipulada puede traer cualquier cosa: en ambos casos la pantalla tiene
     * que seguir rotulando, con la palabra genérica.
     */
    public function test_sin_tipo_o_con_basura_cae_en_el_generico(): void
    {
        App::setLocale('es');

        foreach ([null, '', 'cobranzas', 'COBRANZA', ' cobranza', 'basura', '0'] as $entrada) {
            $pista = var_export($entrada, true);
            $this->assertSame('caso', TipoOperacion::etiquetaDe($entrada), "singular para {$pista}");
            $this->assertSame('casos', TipoOperacion::etiquetaDe($entrada, plural: true), "plural para {$pista}");
            $this->assertSame('un', TipoOperacion::articuloDe($entrada), "artículo para {$pista}");
        }
    }

    public function test_el_generico_tambien_existe_en_ingles(): void
    {
        App::setLocale('en');

        $this->assertSame('case', TipoOperacion::etiquetaDe(null));
        $this->assertSame('cases', TipoOperacion::etiquetaDe('basura', plural: true));
        $this->assertSame('a', TipoOperacion::articuloDe(null));
    }

    // -----------------------------------------------------------------
    // La red que importa: añadir un tipo de operación y olvidar traducirlo
    // -----------------------------------------------------------------

    /**
     * Un quinto tipo de operación en el enum sin su entrada en los dos idiomas
     * dejaría la clave cruda —«casos.entidad_singular.postventa»— en el menú
     * lateral y en cinco pantallas. Esto lo detecta al añadir el enum, no en
     * producción.
     */
    public function test_todo_tipo_del_enum_esta_traducido_en_todos_los_idiomas(): void
    {
        $idiomas = array_keys((array) config('locales.supported', ['es' => null, 'en' => null]));

        foreach ($idiomas as $idioma) {
            App::setLocale($idioma);

            foreach ([...array_map(fn (TipoOperacion $t): string => $t->value, TipoOperacion::cases()), 'generico'] as $tipo) {
                foreach (['singular', 'plural', 'articulo'] as $forma) {
                    $clave = "casos.entidad_{$forma}.{$tipo}";
                    $texto = __($clave);

                    $this->assertNotSame($clave, $texto, "falta {$clave} en lang/{$idioma}/casos.php");
                    $this->assertIsString($texto);
                    $this->assertNotSame('', trim($texto), "{$clave} está vacía en lang/{$idioma}");
                }
            }
        }
    }
}
