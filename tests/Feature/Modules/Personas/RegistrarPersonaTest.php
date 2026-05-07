<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Personas;

use App\Modules\Personas\Application\DTOs\RegistrarPersonaInput;
use App\Modules\Personas\Application\UseCases\RegistrarPersona;
use App\Modules\Personas\Domain\Exceptions\IdentificacionYaRegistradaEnProyecto;
use App\Modules\Personas\Domain\ValueObjects\Identificacion;
use App\Modules\Personas\Domain\ValueObjects\TipoPersona;
use App\Modules\Personas\Infrastructure\Http\Livewire\CrearPersona;
use App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class RegistrarPersonaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_crea_persona_con_solo_identificacion_y_nombres(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $this->actuarComoSupervisor($proyecto);

        Livewire::test(CrearPersona::class)
            ->set('tipoIdentificacionId', $tipoCed)
            ->set('identificacion', '0102030405')
            ->set('nombres', 'Juan')
            ->set('apellidos', 'Pérez')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas', [
            'proyecto_id' => $proyecto->id,
            'identificacion' => '0102030405',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'tipo_persona' => 'fisica',
            'razon_social' => null,
            'fecha_nacimiento' => null,
        ]);
    }

    public function test_apellidos_son_opcionales(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $this->actuarComoSupervisor($proyecto);

        Livewire::test(CrearPersona::class)
            ->set('tipoIdentificacionId', $tipoCed)
            ->set('identificacion', '9988776655')
            ->set('nombres', 'Maria')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas', [
            'proyecto_id' => $proyecto->id,
            'identificacion' => '9988776655',
            'nombres' => 'Maria',
            'apellidos' => null,
        ]);
    }

    public function test_falla_sin_nombres(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $this->actuarComoSupervisor($proyecto);

        Livewire::test(CrearPersona::class)
            ->set('tipoIdentificacionId', $tipoCed)
            ->set('identificacion', '1111111111')
            ->call('guardar')
            ->assertHasErrors(['nombres']);
    }

    public function test_falla_sin_tipo_identificacion(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->actuarComoSupervisor($proyecto);

        Livewire::test(CrearPersona::class)
            ->set('identificacion', '1111111111')
            ->set('nombres', 'X')
            ->call('guardar')
            ->assertHasErrors(['tipoIdentificacionId']);
    }

    public function test_falla_sin_identificacion(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $this->actuarComoSupervisor($proyecto);

        Livewire::test(CrearPersona::class)
            ->set('tipoIdentificacionId', $tipoCed)
            ->set('nombres', 'X')
            ->call('guardar')
            ->assertHasErrors(['identificacion']);
    }

    public function test_form_no_renderiza_radio_tipo_persona_ni_fecha_nacimiento_ni_razon_social(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $supervisor = $this->crearSupervisor($proyecto);
        $this->activarProyecto($proyecto);

        $resp = $this->actingAs($supervisor)->get(route('proyectos.personas.crear', [
            'proyecto_id' => $proyecto->id,
        ]));

        $resp->assertOk();
        $resp->assertDontSee('Persona jurídica');
        $resp->assertDontSee('Razón social');
        $resp->assertDontSee('Fecha de nacimiento');
        $resp->assertDontSee('wire:model.live="tipoPersona"', false);
    }

    public function test_persistencia_guarda_tipo_persona_fisica_por_defecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $this->actuarComoSupervisor($proyecto);

        Livewire::test(CrearPersona::class)
            ->set('tipoIdentificacionId', $tipoCed)
            ->set('identificacion', '5544332211')
            ->set('nombres', 'Ana')
            ->call('guardar')
            ->assertHasNoErrors();

        $persona = DB::table('personas')
            ->where('proyecto_id', $proyecto->id)
            ->where('identificacion', '5544332211')
            ->first();

        $this->assertNotNull($persona);
        $this->assertSame('fisica', (string) $persona->tipo_persona);
    }

    public function test_use_case_directo_persona_fisica_se_persiste(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');

        $output = $this->app->make(RegistrarPersona::class)->execute(new RegistrarPersonaInput(
            publicId: (string) Str::ulid(),
            proyectoId: (int) $proyecto->id,
            tipoPersona: TipoPersona::FISICA,
            tipoIdentificacionId: $tipoCed,
            identificacion: new Identificacion('0102030405'),
            nombres: 'Juan',
            apellidos: 'Pérez',
            razonSocial: null,
            fechaNacimiento: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        ));

        $this->assertDatabaseHas('personas', [
            'id' => $output->id,
            'proyecto_id' => $proyecto->id,
            'identificacion' => '0102030405',
            'nombres' => 'Juan',
        ]);
    }

    public function test_use_case_rechaza_identificacion_duplicada_en_mismo_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $useCase = $this->app->make(RegistrarPersona::class);

        $make = fn () => $useCase->execute(new RegistrarPersonaInput(
            publicId: (string) Str::ulid(),
            proyectoId: (int) $proyecto->id,
            tipoPersona: TipoPersona::FISICA,
            tipoIdentificacionId: $tipoCed,
            identificacion: new Identificacion('0102030405'),
            nombres: 'Juan',
            apellidos: 'Pérez',
            razonSocial: null,
            fechaNacimiento: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        ));

        $make();
        $this->expectException(IdentificacionYaRegistradaEnProyecto::class);
        $make();
    }

    public function test_misma_identificacion_se_aisla_entre_proyectos(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoCx();
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');

        $useCase = $this->app->make(RegistrarPersona::class);
        $build = fn (int $proyectoId) => new RegistrarPersonaInput(
            publicId: (string) Str::ulid(),
            proyectoId: $proyectoId,
            tipoPersona: TipoPersona::FISICA,
            tipoIdentificacionId: $tipoCed,
            identificacion: new Identificacion('0102030405'),
            nombres: 'Juan',
            apellidos: 'Pérez',
            razonSocial: null,
            fechaNacimiento: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        );

        $useCase->execute($build((int) $proyectoA->id));
        $useCase->execute($build((int) $proyectoB->id));

        $this->assertSame(
            2,
            (int) DB::table('personas')->where('identificacion', '0102030405')->count()
        );

        $this->activarProyecto($proyectoA);
        $this->assertSame(1, PersonaModel::query()->where('identificacion', '0102030405')->count());

        $this->activarProyecto($proyectoB);
        $this->assertSame(1, PersonaModel::query()->where('identificacion', '0102030405')->count());
    }

    private function actuarComoSupervisor(stdClass $proyecto): void
    {
        $this->activarProyecto($proyecto);
        $this->actingAs($this->crearSupervisor($proyecto));
    }
}
