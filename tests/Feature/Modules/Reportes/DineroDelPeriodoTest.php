<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Reportes\Infrastructure\Http\Livewire\PanelDelDia;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El importe cobrado del periodo daba 0,00 todos los días.
 *
 * Las dos cifras salían de una sola consulta filtrada por `c.creada_en`, así que
 * en «cumplido» sólo entraban las promesas creadas Y cumplidas dentro del mismo
 * rango — y una promesa creada hoy casi nunca vence hoy. Mientras tanto la
 * tarjeta de al lado contaba las promesas resueltas hoy por `fecha_resolucion`:
 * dos poblaciones distintas bajo etiquetas que el supervisor lee como la misma.
 */
final class DineroDelPeriodoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_lo_cobrado_cuenta_por_fecha_de_resolucion_no_de_creacion(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        // El caso que el panel no veía: promesa nacida hace un mes y cobrada hoy.
        // Es lo normal en cobranza —se promete a plazo y se paga al vencer— y era
        // justo lo que el filtro por fecha de creación dejaba fuera.
        $this->crearPromesa($proyecto, '500.00', creada: Carbon::today()->subDays(30), estado: 'cumplido', resuelta: Carbon::today());

        $dinero = Livewire::test(PanelDelDia::class, ['proyectoId' => (int) $proyecto->id])
            ->viewData('dinero');

        $this->assertSame(500.0, $dinero->cumplido, 'Se cobró hoy: tiene que contar hoy.');
        $this->assertSame(0.0, $dinero->prometido, 'No se prometió nada hoy.');
    }

    public function test_lo_prometido_cuenta_por_fecha_de_creacion(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $this->crearPromesa($proyecto, '300.00', creada: Carbon::now(), estado: 'pendiente');

        $dinero = Livewire::test(PanelDelDia::class, ['proyectoId' => (int) $proyecto->id])
            ->viewData('dinero');

        $this->assertSame(300.0, $dinero->prometido);
        $this->assertSame(0.0, $dinero->cumplido);
    }

    public function test_el_importe_cuadra_con_el_contador_de_promesas_cumplidas(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        $this->crearPromesa($proyecto, '100.00', creada: Carbon::today()->subDays(20), estado: 'cumplido', resuelta: Carbon::today());
        $this->crearPromesa($proyecto, '250.00', creada: Carbon::today()->subDays(15), estado: 'cumplido', resuelta: Carbon::today());

        $componente = Livewire::test(PanelDelDia::class, ['proyectoId' => (int) $proyecto->id]);

        // Las dos tarjetas están una al lado de la otra: si el número dice dos y
        // el importe dice cero, una de las dos miente.
        $this->assertSame(2, $componente->viewData('cumplidas'));
        $this->assertSame(350.0, $componente->viewData('dinero')->cumplido);
    }

    public function test_el_porcentaje_mide_lo_resuelto_y_no_puede_pasar_de_cien(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        // Cobrado hoy sin nada prometido hoy: con la fórmula vieja
        // (cobrado / prometido) esto era una división por cero o un porcentaje
        // desbocado.
        $this->crearPromesa($proyecto, '400.00', creada: Carbon::today()->subDays(10), estado: 'cumplido', resuelta: Carbon::today());
        $this->crearPromesa($proyecto, '100.00', creada: Carbon::today()->subDays(10), estado: 'roto', resuelta: Carbon::today());

        $dinero = Livewire::test(PanelDelDia::class, ['proyectoId' => (int) $proyecto->id])
            ->viewData('dinero');

        $this->assertSame(400.0, $dinero->cumplido);
        $this->assertSame(100.0, $dinero->roto);

        // 400 de 500 resueltos = 80 %, un número con significado.
        $resuelto = $dinero->cumplido + $dinero->roto;
        $this->assertSame(80, (int) round($dinero->cumplido * 100 / $resuelto));
    }

    private function crearPromesa(
        stdClass $proyecto,
        string $monto,
        Carbon $creada,
        string $estado = 'pendiente',
        ?Carbon $resuelta = null,
    ): int {
        $id = (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $this->crearCasoEn($proyecto),
            'tipo_compromiso' => 'promesa_pago',
            'estado' => $estado,
            'fecha_vencimiento' => $creada->copy()->addDays(15)->toDateString(),
            'fecha_resolucion' => $resuelta?->toDateString(),
            'usuario_id' => $this->crearGestor($proyecto)->id,
            'creada_en' => $creada,
            'actualizada_en' => $resuelta ?? $creada,
        ]);

        DB::table('compromisos_promesa_pago')->insert([
            'compromiso_id' => $id,
            'proyecto_id' => $proyecto->id,
            'monto' => $monto,
            'moneda' => 'USD',
            'creada_en' => $creada,
            'actualizada_en' => $creada,
        ]);

        return $id;
    }
}
