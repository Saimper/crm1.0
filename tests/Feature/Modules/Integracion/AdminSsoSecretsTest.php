<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Modules\Integracion\Infrastructure\Http\Livewire\AdminSsoSecrets;
use App\Modules\Integracion\Infrastructure\Jobs\EmitirWebhookSecretRotado;
use App\Modules\Integracion\Infrastructure\Jobs\EmitirWebhookStatusMandante;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class AdminSsoSecretsTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_global_accede_a_pantalla_y_ve_mandantes(): void
    {
        $admin = $this->crearAdminGlobal();
        $mandante = $this->crearMandante('TEST_MAND', 'Mandante Test');

        $this->actingAs($admin)
            ->get('/admin/integracion/secrets')
            ->assertOk()
            ->assertSee('SSO secrets por mandante')
            ->assertSee('TEST_MAND');
    }

    public function test_no_admin_recibe_403_o_redirect(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $usuario = $this->crearGestor($proyecto);

        $response = $this->actingAs($usuario)->get('/admin/integracion/secrets');

        $this->assertContains($response->status(), [302, 403]);
    }

    public function test_secret_se_enmascara_por_defecto_en_render(): void
    {
        $admin = $this->crearAdminGlobal();
        $mandante = $this->crearMandante();
        $secretReal = (string) DB::table('mandantes')->where('id', $mandante->id)->value('sso_secret');

        $component = Livewire::actingAs($admin)->test(AdminSsoSecrets::class);

        $html = $component->html();
        $this->assertStringNotContainsString($secretReal, $html, 'Secret completo no debe filtrarse en render inicial.');
        $this->assertStringContainsString(substr($secretReal, -8), $html, 'Solo se muestran últimos 8 chars enmascarados.');
    }

    public function test_revelar_muestra_secret_completo(): void
    {
        $admin = $this->crearAdminGlobal();
        $mandante = $this->crearMandante();
        $secretReal = (string) DB::table('mandantes')->where('id', $mandante->id)->value('sso_secret');

        Livewire::actingAs($admin)
            ->test(AdminSsoSecrets::class)
            ->call('revelar', (int) $mandante->id)
            ->assertSee($secretReal);
    }

    public function test_rotar_genera_nuevo_secret_y_mueve_el_anterior(): void
    {
        Queue::fake();
        $admin = $this->crearAdminGlobal();
        $mandante = $this->crearMandante();
        $secretAntes = (string) DB::table('mandantes')->where('id', $mandante->id)->value('sso_secret');

        Livewire::actingAs($admin)
            ->test(AdminSsoSecrets::class)
            ->call('rotar', (int) $mandante->id)
            ->assertSet('rotadoId', (int) $mandante->id);

        $row = DB::table('mandantes')->where('id', $mandante->id)
            ->first(['sso_secret', 'sso_secret_old', 'sso_secret_old_expires_at']);

        $this->assertNotSame($secretAntes, (string) $row->sso_secret);
        $this->assertSame(64, strlen((string) $row->sso_secret));
        $this->assertSame($secretAntes, (string) $row->sso_secret_old);
        $this->assertNotNull($row->sso_secret_old_expires_at);
    }

    public function test_rotar_dispatch_webhook_si_url_configurada(): void
    {
        Queue::fake();
        $admin = $this->crearAdminGlobal();
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update([
            'webhook_url_secret_rotated' => 'https://wrapper.example.com/api/integracion/secret-rotated',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminSsoSecrets::class)
            ->call('rotar', (int) $mandante->id);

        Queue::assertPushed(EmitirWebhookSecretRotado::class);
    }

    public function test_rotar_no_dispatch_webhook_si_url_vacia(): void
    {
        Queue::fake();
        $admin = $this->crearAdminGlobal();
        $mandante = $this->crearMandante();

        Livewire::actingAs($admin)
            ->test(AdminSsoSecrets::class)
            ->call('rotar', (int) $mandante->id);

        Queue::assertNotPushed(EmitirWebhookSecretRotado::class);
    }

    public function test_guardar_webhooks_persiste_urls(): void
    {
        $admin = $this->crearAdminGlobal();
        $mandante = $this->crearMandante();

        Livewire::actingAs($admin)
            ->test(AdminSsoSecrets::class)
            ->call('abrirWebhooks', (int) $mandante->id)
            ->set('webhookUrlSecretRotated', 'https://w.example.com/sr')
            ->set('webhookUrlStatusChanged', 'https://w.example.com/sc')
            ->call('guardarWebhooks');

        $row = DB::table('mandantes')->where('id', $mandante->id)
            ->first(['webhook_url_secret_rotated', 'webhook_url_status_changed']);

        $this->assertSame('https://w.example.com/sr', (string) $row->webhook_url_secret_rotated);
        $this->assertSame('https://w.example.com/sc', (string) $row->webhook_url_status_changed);
    }

    public function test_probar_webhook_status_dispatch_job(): void
    {
        Queue::fake();
        $admin = $this->crearAdminGlobal();
        $mandante = $this->crearMandante();
        DB::table('mandantes')->where('id', $mandante->id)->update([
            'webhook_url_status_changed' => 'https://w.example.com/status',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminSsoSecrets::class)
            ->call('probarWebhookStatus', (int) $mandante->id);

        Queue::assertPushed(EmitirWebhookStatusMandante::class);
    }
}
