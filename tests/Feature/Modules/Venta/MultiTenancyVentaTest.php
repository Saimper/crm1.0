<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Venta;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class MultiTenancyVentaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_productos_venta_aislados(): void
    {
        $venta = $this->crearProyectoVenta();
        $servicio = $this->crearProyectoServicio();

        DB::table('productos_venta')->insert([
            'proyecto_id' => $venta->id,
            'codigo' => 'PROD_A',
            'nombre' => 'Producto A',
            'activo' => true,
        ]);

        $this->assertSame(
            0,
            (int) DB::table('productos_venta')->where('proyecto_id', $servicio->id)->count()
        );
        $this->assertSame(
            1,
            (int) DB::table('productos_venta')->where('proyecto_id', $venta->id)->count()
        );
    }
}
