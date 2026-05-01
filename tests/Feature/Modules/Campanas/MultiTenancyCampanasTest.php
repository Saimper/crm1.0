<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Campanas;

use App\Modules\Campanas\Application\DTOs\RegistrarCampanaInput;
use App\Modules\Campanas\Application\UseCases\RegistrarCampana;
use App\Modules\Campanas\Domain\ValueObjects\CodigoCampana;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F34C — multi-tenancy: campañas aisladas por proyecto.
 */
final class MultiTenancyCampanasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_campanas_aisladas_entre_proyectos(): void
    {
        $proyectoA = (int) DB::table('proyectos')->where('codigo', 'COBRANZA_DEMO_2026')->value('id');
        $proyectoB = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');

        $useCase = $this->app->make(RegistrarCampana::class);
        $useCase->execute(new RegistrarCampanaInput(
            publicId: (string) Str::ulid(),
            proyectoId: $proyectoB,
            codigo: new CodigoCampana('CAMP_B_F34C'),
            nombre: 'Camp B',
            descripcion: null,
            fechaInicio: new DateTimeImmutable('2026-04-30'),
            fechaFin: null,
            creadaPorId: null,
            creadaEn: new DateTimeImmutable,
        ));

        $existeEnA = DB::table('campanas')
            ->where('proyecto_id', $proyectoA)
            ->where('codigo', 'CAMP_B_F34C')
            ->exists();
        $this->assertFalse($existeEnA);

        $existeEnB = DB::table('campanas')
            ->where('proyecto_id', $proyectoB)
            ->where('codigo', 'CAMP_B_F34C')
            ->exists();
        $this->assertTrue($existeEnB);
    }
}
