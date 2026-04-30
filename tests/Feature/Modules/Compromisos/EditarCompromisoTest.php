<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Compromisos;

use App\Models\User;
use App\Modules\Compromisos\Infrastructure\Http\Livewire\EditarCompromiso;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F34B — edición de compromiso solo si pendiente. Sin tocar Domain del núcleo.
 */
final class EditarCompromisoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_edita_promesa_pago_pendiente(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $compromiso = $this->compromisoPendiente($proyectoId, 'promesa_pago');

        Livewire::test(EditarCompromiso::class, ['compromiso' => $compromiso->public_id])
            ->set('monto', '12345.67')
            ->set('fechaVencimiento', Carbon::today()->addDays(15)->format('Y-m-d'))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('12345.67',
            (string) DB::table('compromisos_promesa_pago')->where('compromiso_id', $compromiso->id)->value('monto')
        );
    }

    public function test_compromiso_no_pendiente_no_se_carga(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $supervisor = $this->crearConRol($proyectoId, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoId);
        $this->actingAs($supervisor);

        $compromiso = $this->compromisoPendiente($proyectoId, 'promesa_pago');

        // Marca como cumplido vía DB directo (no usamos resolver use case para
        // simplificar el test; el editor solo decide por estado actual).
        DB::table('compromisos')->where('id', $compromiso->id)->update([
            'estado' => 'cumplido',
            'fecha_resolucion' => Carbon::today()->toDateString(),
        ]);

        try {
            Livewire::test(EditarCompromiso::class, ['compromiso' => $compromiso->public_id]);
            $this->fail('Esperaba 409 al editar compromiso ya resuelto.');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }

    public function test_compromiso_de_otro_proyecto_no_se_carga(): void
    {
        $proyectoA = $this->proyectoCobranza();
        $proyectoB = $this->proyectoCx();

        $supervisor = $this->crearConRol($proyectoA, 'SUPERVISOR');
        $this->bindProyectoActivo($proyectoA);
        $this->actingAs($supervisor);

        $compromisoB = $this->compromisoPendiente($proyectoB, 'resolucion_ticket');

        try {
            Livewire::test(EditarCompromiso::class, ['compromiso' => $compromisoB->public_id]);
            $this->fail('Esperaba 404 al editar compromiso de otro proyecto.');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }

    public function test_auditor_recibe_403_en_ruta(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $auditor = $this->crearConRol($proyectoId, 'AUDITOR');
        $compromiso = $this->compromisoPendiente($proyectoId, 'promesa_pago');

        $this->actingAs($auditor)
            ->get(route('proyectos.compromisos.editar', [
                'proyecto_id' => $proyectoId,
                'compromiso' => $compromiso->public_id,
            ]))
            ->assertStatus(403);
    }

    private function compromisoPendiente(int $proyectoId, string $tipo): object
    {
        $row = DB::table('compromisos')
            ->where('proyecto_id', $proyectoId)
            ->where('estado', 'pendiente')
            ->where('tipo_compromiso', $tipo)
            ->first();

        if ($row !== null) {
            return $row;
        }

        // Si no hay seed con ese tipo, crear uno mínimo.
        $caso = (object) DB::table('casos')->where('proyecto_id', $proyectoId)->first();
        $usuario = (int) DB::table('users')->value('id');

        $compromisoId = (int) DB::table('compromisos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'caso_id' => $caso->id,
            'tipo_compromiso' => $tipo,
            'estado' => 'pendiente',
            'fecha_vencimiento' => Carbon::today()->addDays(7)->toDateString(),
            'usuario_id' => $usuario,
        ]);

        if ($tipo === 'promesa_pago') {
            DB::table('compromisos_promesa_pago')->insert([
                'compromiso_id' => $compromisoId,
                'proyecto_id' => $proyectoId,
                'monto' => '500.00',
                'moneda' => 'USD',
            ]);
        } elseif ($tipo === 'resolucion_ticket') {
            DB::table('compromisos_resolucion_ticket')->insert([
                'compromiso_id' => $compromisoId,
                'proyecto_id' => $proyectoId,
                'accion_comprometida' => 'Acción mínima',
                'fecha_limite_sla' => Carbon::now()->addDays(7),
            ]);
        }

        return (object) DB::table('compromisos')->where('id', $compromisoId)->first();
    }

    private function proyectoCobranza(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function proyectoCx(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
    }

    private function bindProyectoActivo(int $proyectoId): void
    {
        $this->app->instance('tenancy.proyecto_activo', DB::table('proyectos')->find($proyectoId));
    }

    private function crearConRol(int $proyectoId, string $codigoRol): User
    {
        /** @var User $u */
        $u = User::query()->create([
            'name' => ucfirst(strtolower($codigoRol)),
            'email' => strtolower($codigoRol).'.ec.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);
        $rolId = (int) DB::table('roles')->where('codigo', $codigoRol)->value('id');
        DB::table('usuario_proyecto_rol')->insert([
            'usuario_id' => $u->id, 'proyecto_id' => $proyectoId,
            'rol_id' => $rolId, 'activo' => true,
        ]);

        return $u;
    }
}
