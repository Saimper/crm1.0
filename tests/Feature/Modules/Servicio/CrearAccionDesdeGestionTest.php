<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Servicio;

use App\Modules\Compromisos\Application\DTOs\ResolverCompromisoInput;
use App\Modules\Gestiones\Application\DTOs\RegistrarGestionInput;
use App\Modules\Gestiones\Application\UseCases\RegistrarGestion;
use App\Modules\Servicio\Application\DTOs\RegistrarCasoServicioInput;
use App\Modules\Servicio\Application\UseCases\CancelarAccion;
use App\Modules\Servicio\Application\UseCases\MarcarAccionEjecutada;
use App\Modules\Servicio\Application\UseCases\MarcarAccionFallida;
use App\Modules\Servicio\Application\UseCases\RegistrarCasoServicio;
use App\Modules\Servicio\Domain\ValueObjects\DatosAccionServicio;
use App\Modules\Servicio\Domain\ValueObjects\DescripcionAccion;
use App\Modules\Servicio\Domain\ValueObjects\FechaProgramada;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CrearAccionDesdeGestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_registrar_gestion_con_agenda_crea_compromiso_y_accion(): void
    {
        $ctx = $this->contexto();

        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $this->idGlobal('canales', 'TELEFONO'),
            tipoGestionId: $this->idProyecto('tipos_gestion', 'COORDINACION', $ctx['proyectoId']),
            resultadoId: $this->idProyecto('resultados', 'AGENDADO', $ctx['proyectoId']),
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: $ctx['usuarioId'],
            notas: 'Se coordinó visita técnica.',
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-20 10:00:00'),
            datosCompromiso: new DatosAccionServicio(
                descripcion: new DescripcionAccion('Instalación de equipos en domicilio del cliente'),
                fechaProgramada: new FechaProgramada(new DateTimeImmutable('2026-04-25 10:00:00')),
                tipoAccionServicioId: $this->idProyecto('tipos_accion_servicio', 'INSTALACION', $ctx['proyectoId']),
                tecnicoAsignado: 'Carlos Peña',
            ),
        ));

        $this->assertDatabaseHas('compromisos', [
            'caso_id' => $ctx['casoId'],
            'tipo_compromiso' => 'accion_servicio',
            'estado' => 'pendiente',
        ]);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');
        $this->assertDatabaseHas('compromisos_accion_servicio', [
            'compromiso_id' => $compromisoId,
            'descripcion_accion' => 'Instalación de equipos en domicilio del cliente',
            'tecnico_asignado' => 'Carlos Peña',
        ]);
        $this->assertTrue((bool) DB::table('casos')->where('id', $ctx['casoId'])->value('tiene_compromiso_vigente'));
    }

    public function test_marcar_accion_ejecutada(): void
    {
        $ctx = $this->contexto();
        $this->registrarAccion($ctx);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');

        $this->app->make(MarcarAccionEjecutada::class)->execute(new ResolverCompromisoInput(
            compromisoId: $compromisoId,
            fechaResolucion: new DateTimeImmutable('2026-04-25 18:00:00'),
        ));

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'cumplido']);
        $this->assertFalse((bool) DB::table('casos')->where('id', $ctx['casoId'])->value('tiene_compromiso_vigente'));
    }

    public function test_marcar_accion_fallida(): void
    {
        $ctx = $this->contexto();
        $this->registrarAccion($ctx);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');

        $this->app->make(MarcarAccionFallida::class)->execute(new ResolverCompromisoInput(
            compromisoId: $compromisoId,
            fechaResolucion: new DateTimeImmutable('2026-04-26'),
        ));

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'roto']);
    }

    public function test_cancelar_accion(): void
    {
        $ctx = $this->contexto();
        $this->registrarAccion($ctx);
        $compromisoId = (int) DB::table('compromisos')->where('caso_id', $ctx['casoId'])->value('id');

        $this->app->make(CancelarAccion::class)->execute(new ResolverCompromisoInput(
            compromisoId: $compromisoId,
            fechaResolucion: new DateTimeImmutable('2026-04-21'),
        ));

        $this->assertDatabaseHas('compromisos', ['id' => $compromisoId, 'estado' => 'cancelado']);
    }

    /** @param array{proyectoId:int, casoId:int, personaId:int, usuarioId:int} $ctx */
    private function registrarAccion(array $ctx): void
    {
        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $this->idGlobal('canales', 'TELEFONO'),
            tipoGestionId: $this->idProyecto('tipos_gestion', 'COORDINACION', $ctx['proyectoId']),
            resultadoId: $this->idProyecto('resultados', 'AGENDADO', $ctx['proyectoId']),
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: $ctx['usuarioId'],
            notas: null,
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-20 10:00:00'),
            datosCompromiso: new DatosAccionServicio(
                descripcion: new DescripcionAccion('Acción estándar'),
                fechaProgramada: new FechaProgramada(new DateTimeImmutable('2026-04-25 10:00:00')),
            ),
        ));
    }

    /** @return array{proyectoId:int, casoId:int, personaId:int, usuarioId:int} */
    private function contexto(): array
    {
        $proyectoId = (int) DB::table('proyectos')->where('codigo', 'SERVICIO_DEMO_2026')->value('id');
        $carteraId = (int) DB::table('carteras')->where('proyecto_id', $proyectoId)->where('codigo', 'RESIDENCIAL')->value('id');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $estadoId = (int) DB::table('estados_caso')->where('proyecto_id', $proyectoId)->where('codigo', 'PENDIENTE')->value('id');

        $usuarioId = (int) DB::table('users')->insertGetId([
            'name' => 'UC', 'email' => 'uc.'.Str::random(6).'@crm.local',
            'password' => bcrypt('x'), 'activo' => true,
        ]);
        $personaId = (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(), 'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica', 'tipo_identificacion_id' => $tipoCed,
            'identificacion' => (string) random_int(1_000_000_000, 9_999_999_999),
            'nombres' => 'Tester', 'apellidos' => 'Servicio',
        ]);

        $out = $this->app->make(RegistrarCasoServicio::class)->execute(new RegistrarCasoServicioInput(
            proyectoId: $proyectoId,
            carteraId: $carteraId,
            personaId: $personaId,
            estadoCasoId: $estadoId,
            fechaIngreso: new DateTimeImmutable('2026-04-20'),
            prioridad: 100,
            codigoServicio: 'SVC-CROSS-'.Str::random(4),
            tipoAccionServicioId: null,
            estadoTecnicoId: null,
            direccionServicio: null,
            tecnicoAsignado: null,
            fechaSolicitud: new DateTimeImmutable('2026-04-20'),
            fechaProgramada: null,
        ));

        return [
            'proyectoId' => $proyectoId,
            'casoId' => $out->casoId,
            'personaId' => $personaId,
            'usuarioId' => $usuarioId,
        ];
    }

    private function idGlobal(string $tabla, string $codigo): int
    {
        return (int) DB::table($tabla)->where('codigo', $codigo)->value('id');
    }

    private function idProyecto(string $tabla, string $codigo, int $proyectoId): int
    {
        return (int) DB::table($tabla)->where('proyecto_id', $proyectoId)->where('codigo', $codigo)->value('id');
    }
}
