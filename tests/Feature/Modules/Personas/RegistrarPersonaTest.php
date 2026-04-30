<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Personas;

use App\Modules\Personas\Application\DTOs\RegistrarPersonaInput;
use App\Modules\Personas\Application\UseCases\RegistrarPersona;
use App\Modules\Personas\Domain\Exceptions\IdentificacionYaRegistradaEnProyecto;
use App\Modules\Personas\Domain\ValueObjects\Identificacion;
use App\Modules\Personas\Domain\ValueObjects\TipoPersona;
use App\Modules\Personas\Infrastructure\Persistence\Models\PersonaModel;
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RegistrarPersonaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            MandantesDemoSeeder::class,
            ProyectosDemoSeeder::class,
            TiposIdentificacionSeeder::class,
        ]);
    }

    public function test_registra_persona_fisica_en_proyecto_demo(): void
    {
        $proyectoId = $this->idProyecto('COBRANZA_DEMO_2026');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');

        $output = $this->app->make(RegistrarPersona::class)->execute(new RegistrarPersonaInput(
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
        ));

        $this->assertDatabaseHas('personas', [
            'id' => $output->id,
            'proyecto_id' => $proyectoId,
            'identificacion' => '0102030405',
            'nombres' => 'Juan',
        ]);
    }

    public function test_throws_al_registrar_identificacion_duplicada_en_mismo_proyecto(): void
    {
        $proyectoId = $this->idProyecto('COBRANZA_DEMO_2026');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $useCase = $this->app->make(RegistrarPersona::class);

        $make = fn () => $useCase->execute(new RegistrarPersonaInput(
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
        ));

        $make();

        $this->expectException(IdentificacionYaRegistradaEnProyecto::class);
        $make();
    }

    public function test_permite_misma_identificacion_en_proyectos_distintos_y_aisla_lectura(): void
    {
        $proyectoA = $this->idProyecto('COBRANZA_DEMO_2026');
        $proyectoB = $this->crearProyectoExtra('OTRO_COB_2026');
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');

        $inputBase = fn (int $proyectoId) => new RegistrarPersonaInput(
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

        $useCase = $this->app->make(RegistrarPersona::class);
        $useCase->execute($inputBase($proyectoA));
        $useCase->execute($inputBase($proyectoB));

        $this->assertSame(
            2,
            (int) DB::table('personas')->where('identificacion', '0102030405')->count(),
            'La misma identificación debe poder existir en dos proyectos distintos.',
        );

        // Con proyecto A activo, el scope del trait debe devolver solo 1 persona.
        $this->setProyectoActivo($proyectoA);
        $this->assertSame(1, PersonaModel::query()->where('identificacion', '0102030405')->count());

        $this->setProyectoActivo($proyectoB);
        $this->assertSame(1, PersonaModel::query()->where('identificacion', '0102030405')->count());
    }

    private function idProyecto(string $codigo): int
    {
        return (int) DB::table('proyectos')->where('codigo', $codigo)->value('id');
    }

    private function crearProyectoExtra(string $codigo): int
    {
        $mandanteId = (int) DB::table('mandantes')->where('codigo', 'BPO_DEMO')->value('id');

        return (int) DB::table('proyectos')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'mandante_id' => $mandanteId,
            'codigo' => $codigo,
            'nombre' => "Proyecto adicional {$codigo}",
            'tipo_operacion' => 'cobranza',
            'activo' => true,
        ]);
    }

    private function setProyectoActivo(int $proyectoId): void
    {
        $proyecto = DB::table('proyectos')->find($proyectoId);
        $this->app->instance('tenancy.proyecto_activo', $proyecto);
    }
}
