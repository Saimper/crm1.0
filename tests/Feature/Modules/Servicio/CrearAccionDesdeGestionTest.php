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
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class CrearAccionDesdeGestionTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
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
            canalId: $ctx['canalId'],
            tipoGestionId: $ctx['tipoGestionId'],
            resultadoId: $ctx['resultadoId'],
            motivoNoContactoId: null,
            causaId: null,
            usuarioId: $ctx['usuarioId'],
            notas: 'Se coordinó visita técnica.',
            duracion: null,
            creadaEn: new DateTimeImmutable('2026-04-20 10:00:00'),
            datosCompromiso: new DatosAccionServicio(
                descripcion: new DescripcionAccion('Instalación de equipos en domicilio del cliente'),
                fechaProgramada: new FechaProgramada(new DateTimeImmutable('2026-04-25 10:00:00')),
                tipoAccionServicioId: $ctx['tipoAccionServicioId'],
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

    /** @param array<string, int> $ctx */
    private function registrarAccion(array $ctx): void
    {
        $this->app->make(RegistrarGestion::class)->execute(new RegistrarGestionInput(
            publicId: (string) Str::ulid(),
            proyectoId: $ctx['proyectoId'],
            casoId: $ctx['casoId'],
            personaId: $ctx['personaId'],
            contactoId: null,
            canalId: $ctx['canalId'],
            tipoGestionId: $ctx['tipoGestionId'],
            resultadoId: $ctx['resultadoId'],
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

    /**
     * Escenario mínimo de un proyecto de servicio: cartera, persona, estado,
     * gestor y la cascada canal → tipo → resultado con `requiere_compromiso`,
     * que es lo que dispara al listener `CrearAccionDesdeGestion`.
     *
     * @return array<string, int>
     */
    private function contexto(): array
    {
        $proyecto = $this->crearProyectoServicio();
        $cartera = $this->crearCarteraEn($proyecto, 'RESIDENCIAL');
        $persona = $this->crearPersonaEn($proyecto);
        $estado = $this->crearEstadoCasoEn($proyecto, 'PENDIENTE');
        $usuario = $this->crearGestor($proyecto);

        $cascada = $this->crearCascadaGestionEn($proyecto, [
            'requiere_compromiso' => true,
            'codigo_tipo' => 'COORDINACION',
            'codigo_resultado' => 'AGENDADO',
        ]);

        $out = $this->app->make(RegistrarCasoServicio::class)->execute(new RegistrarCasoServicioInput(
            proyectoId: (int) $proyecto->id,
            carteraId: (int) $cartera->id,
            personaId: (int) $persona->id,
            estadoCasoId: (int) $estado->id,
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
            'proyectoId' => (int) $proyecto->id,
            'casoId' => $out->casoId,
            'personaId' => (int) $persona->id,
            'usuarioId' => (int) $usuario->id,
            'canalId' => $cascada['canal_id'],
            'tipoGestionId' => $cascada['tipo_gestion_id'],
            'resultadoId' => $cascada['resultado_id'],
            'tipoAccionServicioId' => $this->crearTipoAccionServicioEn($proyecto, 'INSTALACION'),
        ];
    }

    /** No hay helper en EscenarioOperativo para los catálogos de servicio (§8). */
    private function crearTipoAccionServicioEn(stdClass $proyecto, string $codigo): int
    {
        return (int) DB::table('tipos_accion_servicio')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => $codigo,
            'nombre' => 'Tipo acción '.$codigo,
            'activo' => true,
            'orden' => 10,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);
    }
}
