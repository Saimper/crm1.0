<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Servicio;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class MultiTenancyServicioTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_tipos_accion_servicio_aislados(): void
    {
        $servicio = $this->crearProyectoServicio();
        $cx = $this->crearProyectoCx();

        DB::table('tipos_accion_servicio')->insert([
            'proyecto_id' => $servicio->id,
            'codigo' => 'INST',
            'nombre' => 'Instalación',
            'activo' => true,
        ]);

        $this->assertSame(
            0,
            (int) DB::table('tipos_accion_servicio')->where('proyecto_id', $cx->id)->count()
        );
        $this->assertSame(
            1,
            (int) DB::table('tipos_accion_servicio')->where('proyecto_id', $servicio->id)->count()
        );
    }
}
