<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Campanas;

use App\Modules\Campanas\Application\DTOs\RegistrarCampanaInput;
use App\Modules\Campanas\Application\UseCases\RegistrarCampana;
use App\Modules\Campanas\Domain\Events\CampanaCreada;
use App\Modules\Campanas\Domain\Exceptions\CodigoCampanaDuplicadoEnProyecto;
use App\Modules\Campanas\Domain\ValueObjects\CodigoCampana;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F34C — feature test del UseCase RegistrarCampana cubriendo creación,
 * código duplicado y multi-tenancy (mismo código en distintos proyectos OK).
 */
final class RegistrarCampanaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_registra_campana_dispara_evento_y_persiste(): void
    {
        Event::fake([CampanaCreada::class]);

        $proyectoId = $this->proyectoCobranza();
        $useCase = $this->app->make(RegistrarCampana::class);

        $output = $useCase->execute(new RegistrarCampanaInput(
            publicId: (string) Str::ulid(),
            proyectoId: $proyectoId,
            codigo: new CodigoCampana('F34C_TEST'),
            nombre: 'Test Campaña F34C',
            descripcion: 'descripción test',
            fechaInicio: new DateTimeImmutable('2026-04-30'),
            fechaFin: null,
            creadaPorId: null,
            creadaEn: new DateTimeImmutable,
        ));

        $this->assertGreaterThan(0, $output->id);
        $this->assertSame('F34C_TEST', $output->codigo);
        $this->assertDatabaseHas('campanas', [
            'id' => $output->id,
            'proyecto_id' => $proyectoId,
            'codigo' => 'F34C_TEST',
        ]);

        Event::assertDispatched(CampanaCreada::class);
    }

    public function test_codigo_duplicado_en_mismo_proyecto_falla(): void
    {
        $proyectoId = $this->proyectoCobranza();
        $useCase = $this->app->make(RegistrarCampana::class);

        $useCase->execute($this->input($proyectoId, 'DUP_F34C'));

        $this->expectException(CodigoCampanaDuplicadoEnProyecto::class);
        $useCase->execute($this->input($proyectoId, 'DUP_F34C'));
    }

    public function test_mismo_codigo_en_distintos_proyectos_OK(): void
    {
        $proyectoA = $this->proyectoCobranza();
        $proyectoB = $this->proyectoCx();
        $useCase = $this->app->make(RegistrarCampana::class);

        $useCase->execute($this->input($proyectoA, 'CROSS_TEST'));
        $useCase->execute($this->input($proyectoB, 'CROSS_TEST'));

        $this->assertSame(2, (int) DB::table('campanas')->where('codigo', 'CROSS_TEST')->count());
    }

    private function input(int $proyectoId, string $codigo): RegistrarCampanaInput
    {
        return new RegistrarCampanaInput(
            publicId: (string) Str::ulid(),
            proyectoId: $proyectoId,
            codigo: new CodigoCampana($codigo),
            nombre: $codigo,
            descripcion: null,
            fechaInicio: new DateTimeImmutable('2026-04-30'),
            fechaFin: null,
            creadaPorId: null,
            creadaEn: new DateTimeImmutable,
        );
    }

    private function proyectoCobranza(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
    }

    private function proyectoCx(): int
    {
        return (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
    }
}
