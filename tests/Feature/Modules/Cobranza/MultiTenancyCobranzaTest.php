<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cobranza;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class MultiTenancyCobranzaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_tramos_mora_aislados(): void
    {
        $cobranza = $this->crearProyectoCobranza();
        $venta = $this->crearProyectoVenta();

        DB::table('tramos_mora')->insert([
            'proyecto_id' => $cobranza->id,
            'codigo' => 'MORA_30',
            'nombre' => '0-30',
            'dias_desde' => 0,
            'dias_hasta' => 30,
            'orden' => 10,
            'activo' => true,
        ]);

        $this->assertSame(
            1,
            (int) DB::table('tramos_mora')->where('proyecto_id', $cobranza->id)->count()
        );
        $this->assertSame(
            0,
            (int) DB::table('tramos_mora')->where('proyecto_id', $venta->id)->count()
        );
    }

    public function test_tipos_pago_aislados(): void
    {
        $cobranza = $this->crearProyectoCobranza();
        $cx = $this->crearProyectoCx();

        DB::table('tipos_pago')->insert([
            'proyecto_id' => $cobranza->id,
            'codigo' => 'EFECTIVO',
            'nombre' => 'Efectivo',
            'orden' => 10,
            'activo' => true,
        ]);

        $this->assertSame(
            0,
            (int) DB::table('tipos_pago')->where('proyecto_id', $cx->id)->count()
        );
        $this->assertSame(
            1,
            (int) DB::table('tipos_pago')->where('proyecto_id', $cobranza->id)->count()
        );
    }
}
