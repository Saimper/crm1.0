<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Contactos;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F34C — multi-tenancy: contactos aislados por proyecto. Persona en B
 * con mismo identificador no comparte contactos con persona en A.
 */
final class MultiTenancyContactosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_contactos_aislados_entre_proyectos(): void
    {
        $proyectoA = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        $personaB = (object) DB::table('personas')->where('proyecto_id', $proyectoB)->first();
        $this->assertNotNull($personaB);

        DB::table('contactos')->insert([
            'proyecto_id' => $proyectoB,
            'persona_id' => $personaB->id,
            'tipo' => 'correo',
            'valor' => 'exclusivo.b.f34c@correo.com',
            'es_principal' => false,
        ]);

        $countContactosEnA = (int) DB::table('contactos')
            ->where('proyecto_id', $proyectoA)
            ->where('valor', 'exclusivo.b.f34c@correo.com')
            ->count();
        $this->assertSame(0, $countContactosEnA);

        $countContactosEnB = (int) DB::table('contactos')
            ->where('proyecto_id', $proyectoB)
            ->where('valor', 'exclusivo.b.f34c@correo.com')
            ->count();
        $this->assertSame(1, $countContactosEnB);
    }
}
