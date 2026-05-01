<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Servicio;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F34C — multi-tenancy: casos_servicio + tipos_accion_servicio aislados.
 */
final class MultiTenancyServicioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_casos_servicio_aislados_entre_proyectos(): void
    {
        $proyectoServicio = (int) DB::table('proyectos')->where('codigo', 'SERVICIO_DEMO_2026')->value('id');
        $proyectoCobranza = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');

        $countServicio = (int) DB::table('casos_servicio')
            ->where('proyecto_id', $proyectoServicio)->count();
        $countCobranzaScope = (int) DB::table('casos_servicio')
            ->where('proyecto_id', $proyectoCobranza)->count();

        $this->assertGreaterThan(0, $countServicio);
        $this->assertSame(0, $countCobranzaScope);
    }

    public function test_tipos_accion_servicio_aislados(): void
    {
        $proyectoServicio = (int) DB::table('proyectos')->where('codigo', 'SERVICIO_DEMO_2026')->value('id');
        $proyectoCx = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        $count = (int) DB::table('tipos_accion_servicio')
            ->where('proyecto_id', $proyectoCx)->count();
        $this->assertSame(0, $count);

        $countPropio = (int) DB::table('tipos_accion_servicio')
            ->where('proyecto_id', $proyectoServicio)->count();
        $this->assertGreaterThan(0, $countPropio);
    }
}
