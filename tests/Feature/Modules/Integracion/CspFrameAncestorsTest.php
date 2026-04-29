<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Models\User;
use App\Modules\Integracion\Application\DTOs\EmitirTokenSsoInput;
use App\Modules\Integracion\Application\UseCases\EmitirTokenSso;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

        $usuario = $this->crearUsuario();
        $output  = app(EmitirTokenSso::class)->execute(new EmitirTokenSsoInput(
            usuarioId: (int) $usuario->id,
            proyectoId: null,
            identificacion: null,
            tipoIdentificacionCodigo: null,
            redirectPath: null,
            ipOrigen: '127.0.0.1',
            userAgent: 'test',
        ));

        // Consumir el token para llegar a la ruta web con el middleware CSP
        $response = $this->get($output->handshakeUrl);

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp, 'Se esperaba header Content-Security-Policy');
        $this->assertStringContainsString('frame-ancestors', (string) $csp);
        $this->assertStringContainsString('https://wrapper.example.com', (string) $csp);
    }

    public function test_sin_wrapper_domain_x_frame_options_permanece(): void
    {
        Config::set('integracion.wrapper_domain', null);

        $usuario = $this->crearUsuario();
        $output  = app(EmitirTokenSso::class)->execute(new EmitirTokenSsoInput(
            usuarioId: (int) $usuario->id,
            proyectoId: null,
            identificacion: null,
            tipoIdentificacionCodigo: null,
            redirectPath: null,
            ipOrigen: '127.0.0.1',
            userAgent: 'test',
        ));

        $response = $this->get($output->handshakeUrl);

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNull($csp, 'NO se espera Content-Security-Policy sin WRAPPER_DOMAIN');
    }

    private function crearUsuario(): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name'     => 'CSP Test',
            'email'    => 'csp.' . Str::random(6) . '@crm.local',
            'password' => Hash::make('x'),
            'activo'   => true,
        ]);

        return $u;
    }
}
