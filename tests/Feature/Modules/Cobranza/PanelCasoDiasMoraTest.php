<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cobranza;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Con la mora envejeciendo cada día, el gestor necesita distinguir la mora que
 * informó el banco de la que calculó el reloj. El panel lo dice debajo de los
 * días de mora.
 */
final class PanelCasoDiasMoraTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_el_panel_dice_cuando_informo_el_cliente_la_mora(): void
    {
        [$proyecto, $url] = $this->escenario(confirmadaEn: '2026-09-02');
        $gestor = $this->crearGestor($proyecto);

        $html = $this->actingAs($gestor)->get($url)->assertOk()->getContent();

        $this->assertIsString($html);
        $this->assertStringContainsString('mora confirmada por última vez el 02/09/2026', $html);
    }

    public function test_sin_confirmacion_el_panel_no_inventa_una_fecha(): void
    {
        [$proyecto, $url] = $this->escenario(confirmadaEn: null);
        $gestor = $this->crearGestor($proyecto);

        $html = $this->actingAs($gestor)->get($url)->assertOk()->getContent();

        $this->assertIsString($html);
        $this->assertStringNotContainsString('mora confirmada por última vez', $html);
    }

    /** @return array{0: \stdClass, 1: string} */
    private function escenario(?string $confirmadaEn): array
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $casoPublicId = (string) Str::ulid();

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => $casoPublicId,
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $this->crearCarteraEn($proyecto)->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $this->crearEstadoCasoEn($proyecto, 'ABIERTO')->id,
            'fecha_ingreso' => Carbon::today(),
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        DB::table('casos_cobranza')->insert([
            'caso_id' => $casoId,
            'proyecto_id' => $proyecto->id,
            'numero_prestamo' => "P-{$casoId}",
            'dias_mora' => 37,
            'dias_mora_actualizado_en' => '2026-09-08',
            'dias_mora_confirmado_en' => $confirmadaEn,
            'fecha_desembolso' => '2025-01-15',
            'fecha_vencimiento' => '2026-01-15',
        ]);

        return [$proyecto, "/proyectos/{$proyecto->id}/trabajo/{$persona->public_id}/{$casoPublicId}"];
    }
}
