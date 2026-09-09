<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cobranza;

use App\Modules\Cobranza\Infrastructure\Http\Livewire\ResolverPromesa;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Las dos formas de abusar de este componente, cerradas.
 *
 * El `can:casos.ver` de la ruta /trabajo deja entrar al AUDITOR, que es un rol
 * de sólo lectura, y monta el componente con los tres botones. Y el commit de
 * Livewire llega con las propiedades que mande el cliente, así que el id del
 * compromiso era elegible desde la consola del navegador.
 */
final class ResolverPromesaAutorizacionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_auditor_no_puede_resolver_una_promesa(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $compromisoId = $this->crearPromesaEn($proyecto);

        $this->actingAs($this->crearAuditor($proyecto));

        Livewire::test(ResolverPromesa::class, ['compromisoId' => $compromisoId])
            ->set('accion', 'cumplida')
            ->set('fechaResolucion', now()->toDateString())
            ->call('confirmar')
            ->assertForbidden();

        $this->assertSame(
            'pendiente',
            DB::table('compromisos')->where('id', $compromisoId)->value('estado'),
            'El AUDITOR es un rol de sólo lectura: la promesa no debe haberse movido.'
        );
    }

    public function test_gestor_no_puede_cancelar_aunque_si_pueda_resolver(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $compromisoId = $this->crearPromesaEn($proyecto);

        $this->actingAs($this->crearGestor($proyecto));

        Livewire::test(ResolverPromesa::class, ['compromisoId' => $compromisoId])
            ->set('accion', 'cancelada')
            ->set('fechaResolucion', now()->toDateString())
            ->call('confirmar')
            ->assertForbidden();

        $this->assertSame('pendiente', DB::table('compromisos')->where('id', $compromisoId)->value('estado'));
    }

    public function test_gestor_si_puede_marcarla_cumplida(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $compromisoId = $this->crearPromesaEn($proyecto);

        $this->actingAs($this->crearGestor($proyecto));

        Livewire::test(ResolverPromesa::class, ['compromisoId' => $compromisoId])
            ->set('accion', 'cumplida')
            ->set('fechaResolucion', now()->toDateString())
            ->call('confirmar')
            ->assertHasNoErrors();

        $this->assertSame(
            'cumplido',
            DB::table('compromisos')->where('id', $compromisoId)->value('estado'),
            'El camino legítimo tiene que seguir funcionando: esto no es un endurecimiento que rompa la operación.'
        );
    }

    public function test_no_se_puede_resolver_la_promesa_de_otro_mandante(): void
    {
        $propio = $this->crearProyectoCobranza();
        $ajeno = $this->crearProyectoCobranza();          // otro mandante: crearProyecto crea el suyo
        $compromisoAjeno = $this->crearPromesaEn($ajeno);

        $this->activarProyecto($propio);
        $this->actingAs($this->crearSupervisor($propio));

        // El supervisor tiene compromisos.resolver EN SU PROYECTO. Si sólo se
        // comprobara el permiso, arrastraría esa autorización al proyecto ajeno.
        Livewire::test(ResolverPromesa::class, ['compromisoId' => $compromisoAjeno])
            ->set('accion', 'cumplida')
            ->set('fechaResolucion', now()->toDateString())
            ->call('confirmar')
            ->assertNotFound();

        $this->assertSame(
            'pendiente',
            DB::table('compromisos')->where('id', $compromisoAjeno)->value('estado'),
            'FUGA: se resolvió el compromiso de otro mandante.'
        );
    }

    public function test_el_id_del_compromiso_no_se_puede_reapuntar_desde_el_cliente(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);
        $montado = $this->crearPromesaEn($proyecto);
        $otro = $this->crearPromesaEn($proyecto);

        $this->actingAs($this->crearSupervisor($proyecto));

        // #[Locked]: Livewire rechaza el intento de cambiar la propiedad.
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(ResolverPromesa::class, ['compromisoId' => $montado])
            ->set('compromisoId', $otro);
    }

    /** Una promesa pendiente, nacida por el camino real: gestión con resultado que la exige. */
    private function crearPromesaEn(stdClass $proyecto): int
    {
        $persona = $this->crearPersonaEn($proyecto);
        $casoId = $this->crearCasoEn($proyecto, ['persona' => $persona]);

        $compromisoId = (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId,
            'tipo_compromiso' => 'promesa_pago',
            'estado' => 'pendiente',
            'fecha_vencimiento' => now()->addDays(7)->toDateString(),
            'usuario_id' => $this->crearGestor($proyecto)->id,
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);

        DB::table('compromisos_promesa_pago')->insert([
            'compromiso_id' => $compromisoId,
            'proyecto_id' => $proyecto->id,
            'monto' => '250.00',
            'moneda' => 'USD',
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);

        return $compromisoId;
    }
}
