<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Casos;

use App\Modules\Casos\Infrastructure\Http\Livewire\NuevaGestion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El botón «Usar» junto a un contacto de la ficha rellena el contacto de la
 * gestión sin ir a buscarlo en el desplegable. El id lo elige el navegador,
 * así que sólo valen los contactos de esta persona. Y el canal se elige con
 * chips: la cascada tipo → resultado se reinicia igual que con el desplegable.
 */
final class UsarContactoDesdeFichaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_usar_un_contacto_de_la_persona_lo_deja_elegido(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['persona' => $persona]);
        $contacto = $this->crearContactoEn($persona);
        $this->actingAs($this->crearGestor($proyecto));

        Livewire::test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => (int) $persona->id, 'tipoCaso' => 'cobranza'])
            ->dispatch('usar-contacto', contactoId: (int) $contacto->id)
            ->assertSet('contactoId', (int) $contacto->id);
    }

    public function test_un_contacto_de_otra_persona_no_se_acepta(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['persona' => $persona]);
        $ajeno = $this->crearContactoEn($this->crearPersonaEn($proyecto));
        $this->actingAs($this->crearGestor($proyecto));

        Livewire::test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => (int) $persona->id, 'tipoCaso' => 'cobranza'])
            ->dispatch('usar-contacto', contactoId: (int) $ajeno->id)
            ->assertSet('contactoId', null);
    }

    public function test_elegir_el_canal_con_el_chip_reinicia_la_cascada(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['persona' => $persona]);
        $cascada = $this->crearCascadaGestionEn($proyecto);
        $this->actingAs($this->crearGestor($proyecto));

        Livewire::test(NuevaGestion::class, ['casoId' => $casoId, 'personaId' => (int) $persona->id, 'tipoCaso' => 'cobranza'])
            ->set('canalId', $cascada['canal_id'])
            ->set('tipoGestionId', $cascada['tipo_gestion_id'])
            ->set('resultadoId', $cascada['resultado_id'])
            ->call('elegirCanal', $cascada['canal_id'])
            ->assertSet('canalId', $cascada['canal_id'])
            ->assertSet('tipoGestionId', null)
            ->assertSet('resultadoId', null);
    }
}
