<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notificaciones;

use App\Models\User;
use App\Modules\Notificaciones\Infrastructure\Http\Livewire\ListadoNotificaciones;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * La bandeja de notificaciones es por usuario, no por permiso.
 *
 * `marcarLeida($id)` recibe el id como argumento del método, así que lo elige
 * entero el cliente: el `can:notificaciones.ver` de la ruta se comprueba al
 * cargar la página y no vuelve a mirarse en el POST a /livewire/update. Sin
 * guarda, un `$wire.marcarLeida(N)` desde la consola tachaba la bandeja del
 * compañero de al lado, o la del mismo usuario en otro proyecto.
 */
final class ListadoNotificacionesAutorizacionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_no_se_puede_marcar_leida_la_notificacion_de_un_companero(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        $yo = $this->crearGestor($proyecto);
        $companero = $this->crearGestor($proyecto);
        $ajena = $this->crearNotificacion($proyecto, (int) $companero->id);

        $this->actingAs($yo);

        // Mismo proyecto y con `notificaciones.ver`: el permiso lo tiene. Lo que
        // no tiene es la notificación.
        Livewire::test(ListadoNotificaciones::class)
            ->call('marcarLeida', $ajena)
            ->assertNotFound();

        $this->assertNull(
            DB::table('notificaciones')->where('id', $ajena)->value('leida_en'),
            'FUGA: se marcó como leída la notificación de otro usuario.'
        );
    }

    public function test_no_se_puede_marcar_leida_la_notificacion_de_otro_proyecto(): void
    {
        $propio = $this->crearProyectoCobranza();
        $ajeno = $this->crearProyectoCobranza();

        $yo = $this->crearGestor($propio);
        // El mismo usuario también opera en el proyecto ajeno, pero el activo es
        // el propio: lo que pertenezca al otro no se toca desde aquí.
        $this->asignarRol($yo, $ajeno, 'GESTOR');
        $ajena = $this->crearNotificacion($ajeno, (int) $yo->id);

        $this->activarProyecto($propio);
        $this->actingAs($yo);

        Livewire::test(ListadoNotificaciones::class)
            ->call('marcarLeida', $ajena)
            ->assertNotFound();

        $this->assertNull(
            DB::table('notificaciones')->where('id', $ajena)->value('leida_en'),
            'FUGA: se escribió sobre una notificación de otro proyecto.'
        );
    }

    public function test_usuario_sin_rol_en_el_proyecto_no_puede_marcar_leida(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        /** @var User $intruso */
        $intruso = User::query()->create([
            'name' => 'Sin rol',
            'email' => 'sinrol.'.Str::random(8).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
        // Sin pivot: no tiene `notificaciones.ver` en este proyecto. La ruta se
        // lo negaría; el commit de Livewire tiene que negárselo también.
        $suya = $this->crearNotificacion($proyecto, (int) $intruso->id);

        $this->actingAs($intruso);

        Livewire::test(ListadoNotificaciones::class)
            ->call('marcarLeida', $suya)
            ->assertForbidden();

        $this->assertNull(DB::table('notificaciones')->where('id', $suya)->value('leida_en'));
    }

    public function test_el_destinatario_si_puede_marcar_la_suya(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        $yo = $this->crearGestor($proyecto);
        $mia = $this->crearNotificacion($proyecto, (int) $yo->id);

        $this->actingAs($yo);

        Livewire::test(ListadoNotificaciones::class)
            ->call('marcarLeida', $mia)
            ->assertHasNoErrors();

        $this->assertNotNull(
            DB::table('notificaciones')->where('id', $mia)->value('leida_en'),
            'El camino legítimo tiene que seguir vivo: el destinatario marca la suya.'
        );
    }

    public function test_marcar_todas_leidas_solo_alcanza_las_del_usuario_en_el_proyecto_activo(): void
    {
        $propio = $this->crearProyectoCobranza();
        $ajeno = $this->crearProyectoCobranza();

        $yo = $this->crearGestor($propio);
        $this->asignarRol($yo, $ajeno, 'GESTOR');
        $companero = $this->crearGestor($propio);

        $mia = $this->crearNotificacion($propio, (int) $yo->id);
        $delCompanero = $this->crearNotificacion($propio, (int) $companero->id);
        $miaEnElAjeno = $this->crearNotificacion($ajeno, (int) $yo->id);

        $this->activarProyecto($propio);
        $this->actingAs($yo);

        Livewire::test(ListadoNotificaciones::class)
            ->call('marcarTodasLeidas')
            ->assertHasNoErrors();

        $this->assertNotNull(DB::table('notificaciones')->where('id', $mia)->value('leida_en'));
        $this->assertNull(
            DB::table('notificaciones')->where('id', $delCompanero)->value('leida_en'),
            'FUGA: "marcar todas" tachó la bandeja del compañero.'
        );
        $this->assertNull(
            DB::table('notificaciones')->where('id', $miaEnElAjeno)->value('leida_en'),
            'FUGA: "marcar todas" salió del proyecto activo.'
        );
    }

    public function test_usuario_sin_rol_no_puede_marcar_todas_leidas(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->activarProyecto($proyecto);

        /** @var User $intruso */
        $intruso = User::query()->create([
            'name' => 'Sin rol',
            'email' => 'sinrol.'.Str::random(8).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
        $suya = $this->crearNotificacion($proyecto, (int) $intruso->id);

        $this->actingAs($intruso);

        Livewire::test(ListadoNotificaciones::class)
            ->call('marcarTodasLeidas')
            ->assertForbidden();

        $this->assertNull(DB::table('notificaciones')->where('id', $suya)->value('leida_en'));
    }

    /** Una notificación no leída, insertada directa: el generador no es lo que se prueba aquí. */
    private function crearNotificacion(stdClass $proyecto, int $destinatarioId): int
    {
        $casoId = $this->crearCasoEn($proyecto);
        $commitmentId = DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyecto->id,
            'caso_id' => $casoId, 'tipo_compromiso' => 'promesa_pago', 'estado' => 'pendiente',
            'fecha_vencimiento' => now()->subDay()->toDateString(), 'usuario_id' => $destinatarioId,
        ]);

        return (int) DB::table('notificaciones')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'destinatario_usuario_id' => $destinatarioId,
            'tipo' => 'compromiso_vencido',
            'entidad_tipo' => 'compromiso',
            'entidad_id' => $commitmentId,
            'titulo' => 'Compromiso vencido',
            'mensaje' => 'Revisa el compromiso.',
            'metadata' => json_encode(['caso_id' => $casoId]),
            'leida_en' => null,
            'creada_en' => now(),
        ]);
    }

    /** Asigna un usuario ya existente a otro proyecto: el trait sólo sabe crearlo con su rol. */
    private function asignarRol(User $usuario, stdClass $proyecto, string $codigoRol): void
    {
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $usuario->id,
            'proyecto_id' => $proyecto->id,
            'rol_id' => (int) DB::table('roles')->where('codigo', $codigoRol)->value('id'),
            'activo' => true,
        ]);
    }
}
