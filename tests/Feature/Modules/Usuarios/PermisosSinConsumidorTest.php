<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventarioDePermisos;
use Tests\TestCase;

/**
 * Trinquete de permisos que no gobiernan nada.
 *
 * Un permiso sembrado que ningún `can:`, `tienePermiso`, `@can` o Gate
 * comprueba es peor que no tenerlo: aparece en el selector de roles custom y
 * en la matriz de permisos, así que un administrador se lo asigna a alguien
 * creyendo que le está dando —o quitando— algo. Al cerrar la ola 04 había 36 de
 * 85, con grupos enteros que se quedaron sin dueño cuando su pantalla se
 * retiró (los seis `catalogos.*` los dejó huérfanos F36 al sustituir aquellas
 * pantallas por el configurador).
 *
 * Este test no exige arreglarlos: exige que no aparezcan MÁS. Es el mismo
 * trato que `bin/verificar-fugas.sh` hace con las fugas de aislamiento. Si
 * añades un permiso, cablealo; si cableas uno de los de la lista, quítalo del
 * fichero de referencia y el test te lo dirá.
 */
final class PermisosSinConsumidorTest extends TestCase
{
    use RefreshDatabase;

    private const REFERENCIA = 'tests/permisos-huerfanos.baseline';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_ningun_permiso_nuevo_se_queda_sin_quien_lo_comprueba(): void
    {
        $huerfanos = InventarioDePermisos::sinConsumidor($this->codigosSembrados());
        $conocidos = $this->referencia();

        $nuevos = array_values(array_diff($huerfanos, $conocidos));

        $this->assertSame([], $nuevos, sprintf(
            "Permisos sembrados que nadie comprueba:\n  - %s\nO los cableas, o los quitas del seeder. "
            .'Si de verdad tienen que esperar, añádelos a %s con el motivo en el commit.',
            implode("\n  - ", $nuevos),
            self::REFERENCIA,
        ));
    }

    public function test_la_lista_de_referencia_no_guarda_permisos_ya_resueltos(): void
    {
        $huerfanos = InventarioDePermisos::sinConsumidor($this->codigosSembrados());
        $resueltos = array_values(array_diff($this->referencia(), $huerfanos));

        $this->assertSame([], $resueltos, sprintf(
            "Estos permisos ya tienen quien los comprueba y siguen en la lista de deuda:\n  - %s\nQuítalos de %s.",
            implode("\n  - ", $resueltos),
            self::REFERENCIA,
        ));
    }

    /** @return list<string> */
    private function codigosSembrados(): array
    {
        /** @var list<string> $codigos */
        $codigos = DB::table('permisos')->where('activo', true)->orderBy('codigo')->pluck('codigo')->all();

        return $codigos;
    }

    /** @return list<string> */
    private function referencia(): array
    {
        $lineas = file(base_path(self::REFERENCIA), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertNotFalse($lineas, 'Falta '.self::REFERENCIA);

        $codigos = array_values(array_filter(
            array_map('trim', $lineas),
            static fn (string $linea): bool => $linea !== '' && ! str_starts_with($linea, '#'),
        ));

        sort($codigos);

        return $codigos;
    }
}
