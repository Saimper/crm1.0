<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class CspFrameAncestorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_con_wrapper_domain_header_csp_presente_en_handshake(): void
    {
        Config::set('integracion.wrapper_domain', 'https://wrapper.example.com');

        // Token mal formado: provoca 400, pero el middleware CSP corre igual.
        $response = $this->get('/integracion/handshake?token=token-invalido');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp, 'Se esperaba header Content-Security-Policy');
        $this->assertStringContainsString('frame-ancestors', (string) $csp);
        $this->assertStringContainsString('https://wrapper.example.com', (string) $csp);
    }

    public function test_sin_wrapper_domain_no_se_agrega_csp(): void
    {
        Config::set('integracion.wrapper_domain', null);

        $response = $this->get('/integracion/handshake?token=token-invalido');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNull($csp, 'NO se espera Content-Security-Policy sin WRAPPER_DOMAIN');
    }

    /**
     * El screen-pop navega dentro del iframe por páginas normales del CRM, no
     * sólo por el 302 del handshake: todas deben declarar quién las embebe.
     */
    public function test_con_wrapper_domain_toda_pagina_web_declara_frame_ancestors(): void
    {
        Config::set('integracion.wrapper_domain', 'https://wrapper.example.com');

        $response = $this->get('/login');

        $response->assertOk();
        $this->assertSame(
            "frame-ancestors 'self' https://wrapper.example.com",
            (string) $response->headers->get('Content-Security-Policy'),
        );
        $this->assertNull($response->headers->get('X-Frame-Options'));
    }

    public function test_sin_wrapper_domain_las_paginas_web_no_llevan_csp(): void
    {
        Config::set('integracion.wrapper_domain', null);

        $this->get('/login')
            ->assertOk()
            ->assertHeaderMissing('Content-Security-Policy');
    }
}
