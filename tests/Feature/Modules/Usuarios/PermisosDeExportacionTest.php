<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Usuarios;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * Exportar es sacar datos, no verlos: cada listado tiene su permiso y la
 * matriz de roles base lo reparte con criterio. El auditor audita actividad
 * (gestiones, compromisos) y no se lleva el padrón ni la cartera.
 */
final class PermisosDeExportacionTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_los_cuatro_permisos_existen_en_el_grupo_de_su_modulo(): void
    {
        foreach (['personas', 'casos', 'gestiones', 'compromisos'] as $modulo) {
            $permiso = DB::table('permisos')->where('codigo', $modulo.'.exportar')->first();

            $this->assertNotNull($permiso, "Falta {$modulo}.exportar.");
            $this->assertSame($modulo, $permiso->grupo);
            $this->assertTrue((bool) $permiso->activo);
        }
    }

    public function test_el_auditor_exporta_actividad_pero_no_el_padron_ni_la_cartera(): void
    {
        $this->assertSame(
            ['compromisos.exportar', 'gestiones.exportar'],
            $this->exportacionesDe('AUDITOR'),
        );
    }

    public function test_supervisor_y_admin_mandante_se_llevan_las_cuatro(): void
    {
        $todas = ['casos.exportar', 'compromisos.exportar', 'gestiones.exportar', 'personas.exportar'];

        $this->assertSame($todas, $this->exportacionesDe('SUPERVISOR'));
        $this->assertSame($todas, $this->exportacionesDe('ADMIN_MANDANTE'));
    }

    public function test_el_gestor_no_exporta_nada(): void
    {
        $this->assertSame([], $this->exportacionesDe('GESTOR'));
    }

    public function test_los_permisos_se_evaluan_en_el_proyecto_como_el_resto(): void
    {
        $mandante = $this->crearMandante();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $otroMandante = $this->crearMandante();

        $auditor = $this->crearAuditor($proyecto);
        $adminPropio = $this->crearAdminDeMandante($mandante, 'propio');
        $adminAjeno = $this->crearAdminDeMandante($otroMandante, 'ajeno');

        $this->assertTrue($auditor->tienePermiso('gestiones.exportar', (int) $proyecto->id));
        $this->assertFalse($auditor->tienePermiso('personas.exportar', (int) $proyecto->id));

        $this->assertTrue($adminPropio->tienePermiso('personas.exportar', (int) $proyecto->id));
        $this->assertFalse($adminAjeno->tienePermiso('personas.exportar', (int) $proyecto->id));
    }

    /** @return list<string> */
    private function exportacionesDe(string $rol): array
    {
        /** @var list<string> $codigos */
        $codigos = DB::table('rol_permiso as rp')
            ->join('roles as r', 'r.id', '=', 'rp.rol_id')
            ->join('permisos as p', 'p.id', '=', 'rp.permiso_id')
            ->where('r.codigo', $rol)
            ->where('p.codigo', 'like', '%.exportar')
            ->whereIn('p.grupo', ['personas', 'casos', 'gestiones', 'compromisos'])
            ->orderBy('p.codigo')
            ->pluck('p.codigo')
            ->all();

        return $codigos;
    }
}
