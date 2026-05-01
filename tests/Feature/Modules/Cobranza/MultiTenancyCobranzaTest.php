<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cobranza;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F34C — multi-tenancy Cobranza: casos_cobranza, tramos_mora, tipos_pago,
 * compromisos_promesa_pago aislados por proyecto.
 */
final class MultiTenancyCobranzaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_casos_cobranza_aislados_entre_proyectos(): void
    {
        $proyectoCobranza = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoCx = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        $this->assertGreaterThan(
            0,
            (int) DB::table('casos_cobranza')->where('proyecto_id', $proyectoCobranza)->count()
        );
        $this->assertSame(
            0,
            (int) DB::table('casos_cobranza')->where('proyecto_id', $proyectoCx)->count()
        );
    }

    public function test_tramos_mora_aislados(): void
    {
        $proyectoCobranza = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoVenta = (int) DB::table('proyectos')->where('codigo', 'VENTA_DEMO_2026')->value('id');

        $this->assertGreaterThan(
            0,
            (int) DB::table('tramos_mora')->where('proyecto_id', $proyectoCobranza)->count()
        );
        $this->assertSame(
            0,
            (int) DB::table('tramos_mora')->where('proyecto_id', $proyectoVenta)->count()
        );
    }

    public function test_tipos_pago_aislados(): void
    {
        $proyectoCobranza = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoCx = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        $this->assertSame(
            0,
            (int) DB::table('tipos_pago')->where('proyecto_id', $proyectoCx)->count()
        );
        $this->assertGreaterThan(
            0,
            (int) DB::table('tipos_pago')->where('proyecto_id', $proyectoCobranza)->count()
        );
    }
}
