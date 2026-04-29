<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\UI;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fase 25: smoke tests del design system.
 *
 * Los componentes x-ui.* son blocks de presentación — no tienen lógica compleja.
 * Estos tests verifican que compilen, rendericen y respondan a variantes.
 */
final class DesignSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_badge_renderiza_con_tone(): void
    {
        $html = Blade::render('<x-ui.badge tone="success">OK</x-ui.badge>');
        $this->assertStringContainsString('OK', $html);
        $this->assertStringContainsString('bg-success-50', $html);
        $this->assertStringContainsString('text-success-700', $html);
    }

    public function test_badge_tone_neutral_por_defecto(): void
    {
        $html = Blade::render('<x-ui.badge>Hola</x-ui.badge>');
        $this->assertStringContainsString('bg-surface-100', $html);
    }

    public function test_button_primary_por_defecto(): void
    {
        $html = Blade::render('<x-ui.button>Guardar</x-ui.button>');
        $this->assertStringContainsString('Guardar', $html);
        $this->assertStringContainsString('bg-brand-600', $html);
    }

    public function test_button_variants(): void
    {
        foreach (['primary', 'secondary', 'ghost', 'danger', 'success'] as $v) {
            $html = Blade::render('<x-ui.button variant="'.$v.'">X</x-ui.button>');
            $this->assertStringContainsString('X', $html, "falló variant={$v}");
        }
    }

    public function test_button_as_link(): void
    {
        $html = Blade::render('<x-ui.button as="a" href="/foo">Ir</x-ui.button>');
        $this->assertStringContainsString('<a ', $html);
        $this->assertStringContainsString('href="/foo"', $html);
    }

    public function test_card_titulo_y_contenido(): void
    {
        $html = Blade::render('<x-ui.card title="Mi tarjeta" subtitle="Sub">Contenido</x-ui.card>');
        $this->assertStringContainsString('Mi tarjeta', $html);
        $this->assertStringContainsString('Sub', $html);
        $this->assertStringContainsString('Contenido', $html);
    }

    public function test_stat_card_con_tono(): void
    {
        $html = Blade::render('<x-ui.stat-card label="Ventas" value="123" tone="success" />');
        $this->assertStringContainsString('Ventas', $html);
        $this->assertStringContainsString('123', $html);
        $this->assertStringContainsString('text-success-700', $html);
    }

    public function test_empty_state(): void
    {
        $html = Blade::render('<x-ui.empty-state title="Nada aquí" message="Crea tu primero" />');
        $this->assertStringContainsString('Nada aquí', $html);
        $this->assertStringContainsString('Crea tu primero', $html);
    }

    public function test_alert_variantes(): void
    {
        foreach (['info', 'success', 'warning', 'danger'] as $t) {
            $html = Blade::render('<x-ui.alert tone="'.$t.'">msg</x-ui.alert>');
            $this->assertStringContainsString('msg', $html);
        }
    }

    public function test_form_field_con_error(): void
    {
        $html = Blade::render('<x-ui.form-field label="Email" :error="$e"><input/></x-ui.form-field>', ['e' => 'Requerido']);
        $this->assertStringContainsString('Email', $html);
        $this->assertStringContainsString('Requerido', $html);
        $this->assertStringContainsString('text-danger-600', $html);
    }

    public function test_table_con_head_y_rows(): void
    {
        $html = Blade::render(<<<'BLADE'
<x-ui.table>
    <x-slot name="head">
        <x-ui.th>Nombre</x-ui.th>
    </x-slot>
    <tr><x-ui.td>Ana</x-ui.td></tr>
</x-ui.table>
BLADE);
        $this->assertStringContainsString('Nombre', $html);
        $this->assertStringContainsString('Ana', $html);
    }

    public function test_icon_renderiza_svg(): void
    {
        $html = Blade::render('<x-ui.icon name="plus" />');
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('d="M12 4.5v15', $html);
    }

    public function test_proyecto_dashboard_renderiza_con_design_system(): void
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $supervisor = User::query()->create([
            'name' => 'Sup', 'email' => 'sup.ds.'.Str::random(4).'@crm.local',
            'password' => Hash::make('x'), 'activo' => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $supervisor->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);

        $response = $this->actingAs($supervisor)
            ->get(route('proyectos.dashboard', ['proyecto_id' => $proyectoId]))
            ->assertStatus(200);

        // El nuevo dashboard usa los componentes del design system.
        $response->assertSee('shadow-card', false);
        $response->assertSee('text-ink-900', false);
    }
}
