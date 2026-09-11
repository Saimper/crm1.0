<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Asignaciones;

use App\Modules\Asignaciones\Application\UseCases\AsignarCuentasSinDueno;
use App\Modules\Asignaciones\Infrastructure\Http\Livewire\AsignarMasivamente;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class DirectAdvisorAssignmentTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_successive_batches_assign_to_advisors_without_teams_and_preserve_existing_owners(): void
    {
        $p = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($p);
        $supervisor = $this->crearSupervisor($p);
        $uno = $this->crearGestor($p);
        $dos = $this->crearGestor($p);
        for ($i = 0; $i < 5; $i++) {
            $this->crearCasoEn($p, ['cartera' => $cartera]);
        }
        $useCase = app(AsignarCuentasSinDueno::class);
        $primera = $useCase->execute((int) $p->id, (int) $supervisor->id, asesorId: (int) $uno->id, carteraId: (int) $cartera->id, limite: 2);
        $idsPrimera = DB::table('asignaciones')->where('usuario_id', $uno->id)->pluck('caso_id')->all();
        $segunda = $useCase->execute((int) $p->id, (int) $supervisor->id, asesorId: (int) $dos->id, carteraId: (int) $cartera->id, limite: 2);
        self::assertSame(2, $primera->asignadas);
        self::assertSame(2, $segunda->asignadas);
        self::assertSame($idsPrimera, DB::table('asignaciones')->where('usuario_id', $uno->id)->pluck('caso_id')->all());
        self::assertSame(0, DB::table('equipos')->where('proyecto_id', $p->id)->count());
        self::assertSame(1, $useCase->execute((int) $p->id, (int) $supervisor->id, asesorId: (int) $dos->id, limite: 2)->asignadas);
    }

    public function test_only_live_open_accounts_and_selected_portfolio_are_assigned(): void
    {
        $p = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($p);
        $activa = $this->crearCasoEn($p, ['cartera' => $cartera]);
        $cerrada = $this->crearCasoEn($p, ['cartera' => $cartera]);
        $eliminada = $this->crearCasoEn($p, ['cartera' => $cartera]);
        $personaEliminada = $this->crearPersonaEn($p);
        $this->crearCasoEn($p, ['cartera' => $cartera, 'persona' => $personaEliminada]);
        $this->crearCasoEn($p);
        $this->crearCasoEn($this->crearProyectoCobranza());
        DB::table('casos')->where('id', $cerrada)->update(['cerrado_en' => now()]);
        DB::table('casos')->where('id', $eliminada)->update(['eliminada_en' => now()]);
        DB::table('personas')->where('id', $personaEliminada->id)->update(['eliminada_en' => now()]);
        $supervisor = $this->crearSupervisor($p);
        $asesor = $this->crearGestor($p);

        $resultado = app(AsignarCuentasSinDueno::class)->execute((int) $p->id, (int) $supervisor->id, asesorId: (int) $asesor->id, carteraId: (int) $cartera->id);
        self::assertSame(1, $resultado->asignadas);
        self::assertSame([$activa], DB::table('asignaciones')->where('proyecto_id', $p->id)->pluck('caso_id')->map(fn ($id): int => (int) $id)->all());
    }

    public function test_foreign_or_non_operational_destination_cannot_receive_accounts(): void
    {
        $p = $this->crearProyectoCobranza();
        $this->crearCasoEn($p);
        $supervisor = $this->crearSupervisor($p);
        $ajeno = $this->crearGestor($this->crearProyectoCobranza());
        $this->expectException(DomainException::class);
        app(AsignarCuentasSinDueno::class)->execute((int) $p->id, (int) $supervisor->id, asesorId: (int) $ajeno->id);
    }

    public function test_destination_portfolio_restriction_is_enforced(): void
    {
        $p = $this->crearProyectoCobranza();
        $a = $this->crearCarteraEn($p);
        $b = $this->crearCarteraEn($p);
        $this->crearCasoEn($p, ['cartera' => $a]);
        $asesor = $this->crearGestor($p);
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $asesor->id, 'proyecto_id' => $p->id, 'cartera_id' => $b->id,
            'rol_id' => DB::table('roles')->where('codigo', 'GESTOR')->value('id'),
        ]);
        $supervisor = $this->crearSupervisor($p);
        $this->expectException(DomainException::class);
        app(AsignarCuentasSinDueno::class)->execute((int) $p->id, (int) $supervisor->id, asesorId: (int) $asesor->id, carteraId: (int) $a->id);
    }

    public function test_actor_portfolio_restriction_is_enforced_in_use_case(): void
    {
        $p = $this->crearProyectoCobranza();
        $a = $this->crearCarteraEn($p);
        $b = $this->crearCarteraEn($p);
        $this->crearCasoEn($p, ['cartera' => $b]);
        $supervisor = $this->crearSupervisor($p);
        DB::table('usuario_proyecto_rol_cartera')->insert([
            'usuario_id' => $supervisor->id, 'proyecto_id' => $p->id, 'cartera_id' => $a->id,
            'rol_id' => DB::table('roles')->where('codigo', 'SUPERVISOR')->value('id'),
        ]);
        $asesor = $this->crearGestor($p);
        $this->expectException(AuthorizationException::class);
        app(AsignarCuentasSinDueno::class)->execute((int) $p->id, (int) $supervisor->id, asesorId: (int) $asesor->id, carteraId: (int) $b->id);
    }

    public function test_livewire_assigns_to_advisor_without_team(): void
    {
        $p = $this->crearProyectoCobranza();
        $this->crearCasoEn($p);
        $this->activarProyecto($p);
        $this->actingAs($this->crearSupervisor($p));
        $asesor = $this->crearGestor($p);
        Livewire::test(AsignarMasivamente::class)->set('asesorId', $asesor->id)->set('limite', 1)
            ->call('asignar')->assertHasNoErrors()->assertSet('ultAsignadas', 1);
        $this->assertDatabaseHas('asignaciones', ['proyecto_id' => $p->id, 'usuario_id' => $asesor->id]);
    }
}
