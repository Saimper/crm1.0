<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Asignaciones;

use App\Modules\Asignaciones\Infrastructure\Http\Livewire\Bandeja;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Aterrizaje del screen-pop cuando el handshake trae una identificación que no
 * existe en el proyecto: la bandeja avisa y ofrece crear la persona con esos
 * datos en vez de aterrizar en silencio.
 */
final class BandejaAvisoSinPersonaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_muestra_el_aviso_y_el_enlace_para_crear_la_persona(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearGestor($proyecto));

        Livewire::withQueryParams(['sin_persona' => '8-123-456', 'tipo' => 'ced'])
            ->test(Bandeja::class)
            ->assertSet('avisoSinPersona', ['identificacion' => '8-123-456', 'tipo' => 'CED', 'ambigua' => false])
            ->assertSet('puedeCrearPersona', true)
            ->assertSee(__('asignaciones.sso_sin_persona_title'))
            ->assertSee('8-123-456')
            ->assertSee(__('asignaciones.sso_crear_persona'))
            ->assertSee("/proyectos/{$proyecto->id}/personas/crear?identificacion=8-123-456&tipo=CED");
    }

    public function test_por_la_ruta_real_el_aviso_llega_a_la_pantalla(): void
    {
        $proyecto = $this->crearProyectoCobranza();

        $this->actingAs($this->crearGestor($proyecto))
            ->get("/proyectos/{$proyecto->id}/bandeja?sin_persona=99999999&tipo=CED")
            ->assertOk()
            ->assertSee(__('asignaciones.sso_sin_persona_title'))
            ->assertSee("/proyectos/{$proyecto->id}/personas/crear?identificacion=99999999&tipo=CED");
    }

    public function test_identificacion_ambigua_avisa_sin_ofrecer_crear(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearGestor($proyecto));

        Livewire::withQueryParams(['sin_persona' => '44455566', 'ambigua' => '1'])
            ->test(Bandeja::class)
            ->assertSet('avisoSinPersona', ['identificacion' => '44455566', 'tipo' => null, 'ambigua' => true])
            ->assertSee(__('asignaciones.sso_ambigua_body', ['identificacion' => '44455566']))
            ->assertDontSee(__('asignaciones.sso_crear_persona'));
    }

    public function test_un_valor_que_no_parece_identificacion_se_ignora(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearGestor($proyecto));

        Livewire::withQueryParams(['sin_persona' => '<script>alert(1)</script>'])
            ->test(Bandeja::class)
            ->assertSet('avisoSinPersona', null)
            ->assertDontSee(__('asignaciones.sso_sin_persona_title'))
            ->assertDontSee('<script>', false);
    }

    public function test_sin_parametro_no_hay_aviso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearGestor($proyecto));

        Livewire::test(Bandeja::class)
            ->assertSet('avisoSinPersona', null)
            ->assertDontSee(__('asignaciones.sso_sin_persona_title'));
    }
}
