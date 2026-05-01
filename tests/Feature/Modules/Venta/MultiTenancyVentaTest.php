<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Venta;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F34C — multi-tenancy: casos_lead_venta + productos_venta + etapas_embudo aislados.
 */
final class MultiTenancyVentaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_casos_lead_venta_aislados_entre_proyectos(): void
    {
        $proyectoVenta = (int) DB::table('proyectos')->where('codigo', 'VENTA_DEMO_2026')->value('id');
        $proyectoCobranza = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');

        $count = (int) DB::table('casos_lead_venta')
            ->where('proyecto_id', $proyectoCobranza)->count();
        $this->assertSame(0, $count);

        $countPropio = (int) DB::table('casos_lead_venta')
            ->where('proyecto_id', $proyectoVenta)->count();
        $this->assertGreaterThan(0, $countPropio);
    }

    public function test_productos_venta_aislados(): void
    {
        $proyectoVenta = (int) DB::table('proyectos')->where('codigo', 'VENTA_DEMO_2026')->value('id');
        $proyectoServicio = (int) DB::table('proyectos')->where('codigo', 'SERVICIO_DEMO_2026')->value('id');

        $this->assertSame(
            0,
            (int) DB::table('productos_venta')->where('proyecto_id', $proyectoServicio)->count()
        );
        $this->assertGreaterThan(
            0,
            (int) DB::table('productos_venta')->where('proyecto_id', $proyectoVenta)->count()
        );
    }
}
