<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notificaciones;

use App\Modules\Notificaciones\Infrastructure\Http\Livewire\ListadoNotificaciones;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class MultiTenancyNotificacionesTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_listado_filtra_por_proyecto_activo(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();

        $u = $this->crearGestor($proyectoA);
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id,
            'proyecto_id' => $proyectoB->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', 'GESTOR')->value('id'),
            'activo' => true,
        ]);

        DB::table('notificaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoA->id,
            'destinatario_usuario_id' => $u->id,
            'tipo' => 'compromiso_por_vencer',
            'entidad_tipo' => 'compromiso',
            'entidad_id' => 9001,
            'titulo' => 'Aviso A',
            'creada_en' => Carbon::now(),
        ]);
        DB::table('notificaciones')->insert([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoB->id,
            'destinatario_usuario_id' => $u->id,
            'tipo' => 'compromiso_por_vencer',
            'entidad_tipo' => 'compromiso',
            'entidad_id' => 9002,
            'titulo' => 'Aviso B',
            'creada_en' => Carbon::now(),
        ]);

        $this->activarProyecto($proyectoA);
        $this->actingAs($u);

        $c = Livewire::test(ListadoNotificaciones::class);
        $items = $c->viewData('notificaciones');
        foreach ($items as $n) {
            $this->assertNotSame('Aviso B', $n->titulo);
        }
    }
}
