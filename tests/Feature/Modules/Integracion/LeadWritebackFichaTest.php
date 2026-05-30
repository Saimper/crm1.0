<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Modules\Casos\Infrastructure\Http\Livewire\EditarCaso;
use App\Modules\Casos\Infrastructure\Http\Livewire\NuevaGestion;
use App\Modules\Contactos\Infrastructure\Http\Livewire\ListaContactos;
use App\Modules\Gestiones\Domain\Events\GestionRegistrada;
use App\Modules\Integracion\Domain\Contracts\EmisorWritebackFicha;
use App\Modules\Integracion\Infrastructure\Jobs\EmitirWebhookLeadWriteback;
use App\Modules\Personas\Infrastructure\Http\Livewire\EditarPersona;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * Writeback CRM→ViciDial — emisión al guardar la ficha desde los Livewire.
 * El Job se mockea con Queue::fake; se verifica el payload despachado.
 */
final class LeadWritebackFichaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    private const URL_STATUS = 'https://wrapper.example.com/api/integracion/status-changed';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_editar_persona_emite_grupo_persona(): void
    {
        Queue::fake();

        $mandante = $this->crearMandanteConWebhook();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $persona = $this->crearPersonaEn($proyecto);
        $this->enContextoIframe($proyecto, $mandante, 'sync-persona');

        Livewire::test(EditarPersona::class, ['persona' => $persona->public_id])
            ->set('nombres', 'Mariana')
            ->set('apellidos', 'Soto')
            ->call('guardar')
            ->assertHasNoErrors();

        Queue::assertPushed(EmitirWebhookLeadWriteback::class, function (EmitirWebhookLeadWriteback $job) use ($mandante): bool {
            return $job->mandanteId === (int) $mandante->id
                && $job->cuerpo['sync_ref'] === 'sync-persona'
                && $job->cuerpo['changes']['persona']['nombres'] === 'Mariana'
                && $job->cuerpo['changes']['persona']['apellidos'] === 'Soto'
                && $job->webhookUrl === 'https://wrapper.example.com/api/integracion/lead-writeback';
        });
    }

    public function test_lista_contactos_emite_grupo_contacto_principal(): void
    {
        Queue::fake();

        $mandante = $this->crearMandanteConWebhook();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $persona = $this->crearPersonaEn($proyecto);
        $this->enContextoIframe($proyecto, $mandante, 'sync-contacto');

        Livewire::test(ListaContactos::class, ['persona' => $persona->public_id])
            ->set('tipo', 'telefono')
            ->set('valor', '+593 0991112233')
            ->set('esPrincipal', true)
            ->call('agregar')
            ->assertHasNoErrors();

        Queue::assertPushed(EmitirWebhookLeadWriteback::class, function (EmitirWebhookLeadWriteback $job): bool {
            return ($job->cuerpo['changes']['contacto']['telefono'] ?? null) === '+593 0991112233';
        });
    }

    public function test_editar_caso_emite_grupo_custom(): void
    {
        Queue::fake();

        $mandante = $this->crearMandanteConWebhook();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);

        $casoPublicId = (string) Str::ulid();
        DB::table('casos')->insert([
            'public_id' => $casoPublicId,
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => '2026-01-01',
            'prioridad' => 100,
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $cartera->id,
            'tipo' => 'texto_corto',
            'codigo' => 'vici_col',
            'etiqueta' => 'Columna ViciDial',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 1,
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);

        $this->enContextoIframe($proyecto, $mandante, 'sync-caso');

        Livewire::test(EditarCaso::class, ['caso' => $casoPublicId])
            ->set('valoresCamposCaso', ['vici_col' => 'VALOR-X'])
            ->call('guardar')
            ->assertHasNoErrors();

        Queue::assertPushed(EmitirWebhookLeadWriteback::class, function (EmitirWebhookLeadWriteback $job): bool {
            return ($job->cuerpo['changes']['custom']['vici_col'] ?? null) === 'VALOR-X'
                && ($job->cuerpo['changes']['custom_labels']['vici_col'] ?? null) === 'Columna ViciDial';
        });
    }

    public function test_nueva_gestion_emite_custom_del_caso_al_guardar(): void
    {
        Queue::fake();
        Event::fake([GestionRegistrada::class]); // evita el listener de desnormalización

        $mandante = $this->crearMandanteConWebhook();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $cartera = $this->crearCarteraEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto);
        $persona = $this->crearPersonaEn($proyecto);

        $casoId = (int) DB::table('casos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyecto->id,
            'cartera_id' => $cartera->id,
            'persona_id' => $persona->id,
            'tipo_caso' => 'cobranza',
            'estado_caso_id' => $estado->id,
            'fecha_ingreso' => '2026-01-01',
            'prioridad' => 100,
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);

        DB::table('campos_personalizados')->insert([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $cartera->id,
            'tipo' => 'texto_corto',
            'codigo' => 'saldo',
            'etiqueta' => 'Saldo Actual',
            'obligatorio' => false,
            'activo' => true,
            'orden' => 1,
            'creada_en' => now(),
            'actualizada_en' => now(),
        ]);

        $canalId = (int) DB::table('canales')->where('activo', true)->value('id');
        $tipoGestionId = (int) DB::table('tipos_gestion')->insertGetId([
            'proyecto_id' => $proyecto->id, 'codigo' => 'LLAMADA', 'nombre' => 'Llamada',
            'activo' => true, 'orden' => 1, 'creada_en' => now(), 'actualizada_en' => now(),
        ]);
        $resultadoId = (int) DB::table('resultados')->insertGetId([
            'proyecto_id' => $proyecto->id, 'codigo' => 'CONTACTO', 'nombre' => 'Contacto',
            'activo' => true, 'orden' => 1,
            'es_contacto_efectivo' => true, 'requiere_compromiso' => false, 'requiere_causa' => false,
            'creada_en' => now(), 'actualizada_en' => now(),
        ]);

        $this->enContextoIframe($proyecto, $mandante, 'sync-gestion');

        Livewire::test(NuevaGestion::class, [
            'casoId' => $casoId, 'personaId' => (int) $persona->id, 'tipoCaso' => 'cobranza',
        ])
            ->set('canalId', $canalId)
            ->set('tipoGestionId', $tipoGestionId)
            ->set('resultadoId', $resultadoId)
            ->set('valoresCamposCaso', ['saldo' => '1500'])
            ->call('guardar')
            ->assertHasNoErrors();

        Queue::assertPushed(EmitirWebhookLeadWriteback::class, function (EmitirWebhookLeadWriteback $job): bool {
            return ($job->cuerpo['changes']['custom']['saldo'] ?? null) === '1500'
                && ($job->cuerpo['changes']['custom_labels']['saldo'] ?? null) === 'Saldo Actual';
        });
    }

    public function test_custom_castea_todos_los_valores_a_string(): void
    {
        Queue::fake();

        $mandante = $this->crearMandanteConWebhook();

        app(EmisorWritebackFicha::class)->emitir((int) $mandante->id, 'sync-cast', ['custom' => [
            'edad' => 30,
            'activo' => true,
            'inactivo' => false,
            'tasa' => 1.5,
            'opciones' => [1, 2],   // selección múltiple → descartado
            'vacio' => '',           // vacío → descartado
            'larga' => str_repeat('x', 300), // truncado a 255
        ]]);

        Queue::assertPushed(EmitirWebhookLeadWriteback::class, function (EmitirWebhookLeadWriteback $job): bool {
            $custom = $job->cuerpo['changes']['custom'];

            return $custom['edad'] === '30'
                && $custom['activo'] === '1'
                && $custom['inactivo'] === '0'
                && $custom['tasa'] === '1.5'
                && ! array_key_exists('opciones', $custom)
                && ! array_key_exists('vacio', $custom)
                && strlen($custom['larga']) === 255;
        });
    }

    public function test_no_emite_sin_sync_ref_en_sesion(): void
    {
        Queue::fake();

        $mandante = $this->crearMandanteConWebhook();
        $proyecto = $this->crearProyectoCobranza($mandante);
        $persona = $this->crearPersonaEn($proyecto);

        // Sin contexto de iframe: activamos proyecto + auth pero NO seteamos sesión.
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));

        Livewire::test(EditarPersona::class, ['persona' => $persona->public_id])
            ->set('nombres', 'Sin Sync')
            ->call('guardar')
            ->assertHasNoErrors();

        Queue::assertNotPushed(EmitirWebhookLeadWriteback::class);
    }

    public function test_usa_mandante_id_de_sesion_no_del_proyecto_activo(): void
    {
        Queue::fake();

        $mandanteProyecto = $this->crearMandante('MAND_PROY');
        $mandanteSesion = $this->crearMandanteConWebhook('MAND_SES');

        $proyecto = $this->crearProyectoCobranza($mandanteProyecto);
        $persona = $this->crearPersonaEn($proyecto);

        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));
        // El sync_ref/mandante de la sesión apuntan a OTRO mandante (el del handshake).
        session(['crm_sync_ref' => 'sync-x', 'crm_mandante_id' => (int) $mandanteSesion->id]);

        Livewire::test(EditarPersona::class, ['persona' => $persona->public_id])
            ->set('nombres', 'Cross')
            ->call('guardar')
            ->assertHasNoErrors();

        Queue::assertPushed(EmitirWebhookLeadWriteback::class, function (EmitirWebhookLeadWriteback $job) use ($mandanteSesion): bool {
            return $job->mandanteId === (int) $mandanteSesion->id
                && str_starts_with($job->webhookUrl, 'https://wrapper.example.com');
        });
    }

    private function crearMandanteConWebhook(?string $codigo = null): stdClass
    {
        $mandante = $this->crearMandante($codigo);
        DB::table('mandantes')->where('id', $mandante->id)->update([
            'webhook_url_status_changed' => self::URL_STATUS,
        ]);

        return $mandante;
    }

    private function enContextoIframe(stdClass $proyecto, stdClass $mandante, string $syncRef): void
    {
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));
        session(['crm_sync_ref' => $syncRef, 'crm_mandante_id' => (int) $mandante->id]);
    }
}
