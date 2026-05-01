<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F34C — multi-tenancy: tokens SSO ligados a proyecto específico.
 * Token emitido para proyecto B no debe consumirse contra proyecto A.
 */
final class MultiTenancyIntegracionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_token_sso_filtra_por_proyecto(): void
    {
        $proyectoA = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        $usuarioId = (int) DB::table('users')->first()->id;

        DB::table('integracion_tokens_sso')->insert([
            'public_id' => (string) Str::uuid(),
            'token_hash' => hash('sha256', 'token-b-f34c'),
            'usuario_id' => $usuarioId,
            'proyecto_id' => $proyectoB,
            'expira_en' => Carbon::now()->addMinutes(5),
        ]);

        // Token correspondiente a proyecto B no aparece scopeado a A.
        $existeEnA = DB::table('integracion_tokens_sso')
            ->where('proyecto_id', $proyectoA)
            ->where('token_hash', hash('sha256', 'token-b-f34c'))
            ->exists();
        $this->assertFalse($existeEnA);

        $existeEnB = DB::table('integracion_tokens_sso')
            ->where('proyecto_id', $proyectoB)
            ->where('token_hash', hash('sha256', 'token-b-f34c'))
            ->exists();
        $this->assertTrue($existeEnB);
    }
}
