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
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

final class AdminSsoSecretsTest extends TestCase
{
    use EscenarioMultiMandante;
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

    // ---------------------------------------------------------------------
    // Autorización por acción. El `admin.global` de la ruta cubre el GET de la
    // página; cada acción llega después por /livewire/update sin volver a pasar
    // por él. Lo que se prueba aquí es que el commit también está cerrado.
    // ---------------------------------------------------------------------

    public function test_un_no_admin_no_llega_ni_a_renderizar_el_listado_de_secrets(): void
    {
        $mandante = $this->crearMandante('LST_GESTOR');
        $proyecto = $this->crearProyectoCobranza($mandante);

        // La guarda está en el computed, así que el componente ni siquiera se
        // monta: el listado ES la fuga (trae el sso_secret de cada mandante).
        Livewire::actingAs($this->crearGestor($proyecto))
            ->test(AdminSsoSecrets::class)
            ->assertForbidden();

        Livewire::actingAs($this->crearAdminDeMandante($mandante, 'lst'))
            ->test(AdminSsoSecrets::class)
            ->assertForbidden();
    }

    public function test_gestor_no_puede_rotar_el_secret_aunque_herede_el_componente_montado(): void
    {
        Queue::fake();
        $mandante = $this->crearMandante('ROT_GESTOR');
        $proyecto = $this->crearProyectoCobranza($mandante);
        $secretAntes = (string) DB::table('mandantes')->where('id', $mandante->id)->value('sso_secret');

        // El componente lo monta quien puede; el POST de la acción llega
        // después y no vuelve a pasar por el middleware de la ruta. Ese es el
        // agujero que se cierra, y por eso el actor se cambia entre medias.
        $componente = Livewire::actingAs($this->crearAdminGlobal())->test(AdminSsoSecrets::class);

        $this->actingAs($this->crearGestor($proyecto));

        $componente->call('rotar', (int) $mandante->id)->assertForbidden();

        $this->assertSame(
            $secretAntes,
            (string) DB::table('mandantes')->where('id', $mandante->id)->value('sso_secret'),
            'FUGA: un gestor rotó el secret del canal SSO y dejó al wrapper fuera.'
        );
        Queue::assertNotPushed(EmitirWebhookSecretRotado::class);
    }

    public function test_admin_de_mandante_no_puede_rotar_ni_el_secret_de_su_propio_mandante(): void
    {
        Queue::fake();
        $mandante = $this->crearMandante('ROT_ADMMND');
        $secretAntes = (string) DB::table('mandantes')->where('id', $mandante->id)->value('sso_secret');

        $componente = Livewire::actingAs($this->crearAdminGlobal())->test(AdminSsoSecrets::class);

        // ADMIN_MANDANTE administra los proyectos de su mandante (F38), pero
        // esta ruta le está vetada (F39): el secret es del canal wrapper↔CRM.
        $this->actingAs($this->crearAdminDeMandante($mandante, 'rot'));

        $componente->call('rotar', (int) $mandante->id)->assertForbidden();

        $this->assertSame(
            $secretAntes,
            (string) DB::table('mandantes')->where('id', $mandante->id)->value('sso_secret')
        );
        Queue::assertNotPushed(EmitirWebhookSecretRotado::class);
    }

    public function test_gestor_no_puede_revelar_un_secret(): void
    {
        $mandante = $this->crearMandante('REV_GESTOR');
        $proyecto = $this->crearProyectoCobranza($mandante);
        $secretReal = (string) DB::table('mandantes')->where('id', $mandante->id)->value('sso_secret');

        $componente = Livewire::actingAs($this->crearAdminGlobal())->test(AdminSsoSecrets::class);

        $this->actingAs($this->crearGestor($proyecto));

        $componente->call('revelar', (int) $mandante->id)->assertForbidden();
        $this->assertStringNotContainsString($secretReal, $componente->html());
    }

    public function test_gestor_no_puede_guardar_las_urls_de_webhook(): void
    {
        $mandante = $this->crearMandante('WHK_GESTOR');
        $proyecto = $this->crearProyectoCobranza($mandante);

        // El drawer lo abre el admin; el commit llega después, y es ahí donde
        // otro usuario podría reenviar el POST con el snapshot heredado.
        $componente = Livewire::actingAs($this->crearAdminGlobal())
            ->test(AdminSsoSecrets::class)
            ->call('abrirWebhooks', (int) $mandante->id)
            ->set('webhookUrlStatusChanged', 'https://atacante.example.com/robar');

        $this->actingAs($this->crearGestor($proyecto));

        $componente->call('guardarWebhooks')->assertForbidden();

        $this->assertNull(
            DB::table('mandantes')->where('id', $mandante->id)->value('webhook_url_status_changed'),
            'FUGA: se reapuntó a dónde el CRM manda el secret rotado.'
        );
    }

    public function test_gestor_no_puede_disparar_el_webhook_de_prueba(): void
    {
        Queue::fake();
        $mandante = $this->crearMandante('PRB_GESTOR');
        $proyecto = $this->crearProyectoCobranza($mandante);
        DB::table('mandantes')->where('id', $mandante->id)->update([
            'webhook_url_status_changed' => 'https://w.example.com/status',
        ]);

        $componente = Livewire::actingAs($this->crearAdminGlobal())->test(AdminSsoSecrets::class);

        $this->actingAs($this->crearGestor($proyecto));

        $componente->call('probarWebhookStatus', (int) $mandante->id)->assertForbidden();

        Queue::assertNotPushed(EmitirWebhookStatusMandante::class);
    }

    public function test_rotar_un_mandante_borrado_o_inexistente_da_404(): void
    {
        Queue::fake();
        $admin = $this->crearAdminGlobal();
        $borrado = $this->crearMandante('MND_BORRADO');
        DB::table('mandantes')->where('id', $borrado->id)->update(['eliminada_en' => now()]);

        Livewire::actingAs($admin)
            ->test(AdminSsoSecrets::class)
            ->call('rotar', (int) $borrado->id)
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(AdminSsoSecrets::class)
            ->call('abrirWebhooks', 999999)
            ->assertNotFound();

        Queue::assertNotPushed(EmitirWebhookSecretRotado::class);
    }

    public function test_el_mandante_en_edicion_no_se_reapunta_desde_el_cliente(): void
    {
        $admin = $this->crearAdminGlobal();
        $propio = $this->crearMandante('LOCK_A');
        $otro = $this->crearMandante('LOCK_B');

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($admin)
            ->test(AdminSsoSecrets::class)
            ->call('abrirWebhooks', (int) $propio->id)
            ->set('editandoMandanteId', (int) $otro->id);
    }

    public function test_admin_global_sigue_pudiendo_rotar_y_guardar_webhooks(): void
    {
        Queue::fake();
        $admin = $this->crearAdminGlobal();
        $mandante = $this->crearMandante('OK_ADMIN');
        $secretAntes = (string) DB::table('mandantes')->where('id', $mandante->id)->value('sso_secret');

        Livewire::actingAs($admin)
            ->test(AdminSsoSecrets::class)
            ->call('abrirWebhooks', (int) $mandante->id)
            ->set('webhookUrlStatusChanged', 'https://w.example.com/status')
            ->call('guardarWebhooks')
            ->assertHasNoErrors()
            ->call('rotar', (int) $mandante->id)
            ->assertSet('rotadoId', (int) $mandante->id);

        $row = DB::table('mandantes')->where('id', $mandante->id)
            ->first(['sso_secret', 'sso_secret_old', 'webhook_url_status_changed']);

        $this->assertSame('https://w.example.com/status', (string) $row->webhook_url_status_changed);
        $this->assertNotSame($secretAntes, (string) $row->sso_secret);
        $this->assertSame($secretAntes, (string) $row->sso_secret_old);
    }
}
