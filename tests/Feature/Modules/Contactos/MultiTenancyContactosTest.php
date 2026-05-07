<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Contactos;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class MultiTenancyContactosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_contactos_aislados_entre_proyectos(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        $personaB = $this->crearPersonaEn($proyectoB);

        DB::table('contactos')->insert([
            'proyecto_id' => $proyectoB->id,
            'persona_id' => $personaB->id,
            'tipo' => 'correo',
            'valor' => 'exclusivo.b.f34c@correo.com',
            'es_principal' => false,
        ]);

        $countContactosEnA = (int) DB::table('contactos')
            ->where('proyecto_id', $proyectoA->id)
            ->where('valor', 'exclusivo.b.f34c@correo.com')
            ->count();
        $this->assertSame(0, $countContactosEnA);

        $countContactosEnB = (int) DB::table('contactos')
            ->where('proyecto_id', $proyectoB->id)
            ->where('valor', 'exclusivo.b.f34c@correo.com')
            ->count();
        $this->assertSame(1, $countContactosEnB);
    }
}
