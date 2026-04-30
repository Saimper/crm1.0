<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Modules\Tenancy\Application\DTOs\RegistrarMandanteInput;
use App\Modules\Tenancy\Application\DTOs\RegistrarProyectoInput;
use App\Modules\Tenancy\Application\UseCases\RegistrarMandante;
use App\Modules\Tenancy\Application\UseCases\RegistrarProyecto;
use App\Modules\Tenancy\Domain\Exceptions\CodigoProyectoDuplicadoEnMandante;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoMandante;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoProyecto;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RegistrarProyectoTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_proyecto_con_mandante_existente(): void
    {
        $mandante = $this->app->make(RegistrarMandante::class)->execute(new RegistrarMandanteInput(
            publicId: (string) Str::ulid(),
            codigo: new CodigoMandante('BPO_TEST'),
            nombre: 'BPO Test',
            documento: '0000000000001',
            creadaEn: new DateTimeImmutable('2026-04-17'),
        ));

        $output = $this->app->make(RegistrarProyecto::class)->execute(new RegistrarProyectoInput(
            publicId: (string) Str::ulid(),
            mandanteId: $mandante->id,
            codigo: new CodigoProyecto('COB_A'),
            nombre: 'Cobranza A',
            descripcion: null,
            tipoOperacion: TipoOperacion::COBRANZA,
            fechaInicio: null,
            fechaFin: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        ));

        $this->assertDatabaseHas('proyectos', [
            'id' => $output->id,
            'mandante_id' => $mandante->id,
            'codigo' => 'COB_A',
            'tipo_operacion' => 'cobranza',
        ]);
    }

    public function test_rechaza_codigo_duplicado_en_mismo_mandante(): void
    {
        $mandante = $this->app->make(RegistrarMandante::class)->execute(new RegistrarMandanteInput(
            publicId: (string) Str::ulid(),
            codigo: new CodigoMandante('BPO_DUP'),
            nombre: 'BPO Dup',
            documento: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        ));

        $caseUse = $this->app->make(RegistrarProyecto::class);

        $caseUse->execute(new RegistrarProyectoInput(
            publicId: (string) Str::ulid(),
            mandanteId: $mandante->id,
            codigo: new CodigoProyecto('COB_UNICO'),
            nombre: 'Cobranza Única',
            descripcion: null,
            tipoOperacion: TipoOperacion::COBRANZA,
            fechaInicio: null,
            fechaFin: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        ));

        $this->expectException(CodigoProyectoDuplicadoEnMandante::class);
        $caseUse->execute(new RegistrarProyectoInput(
            publicId: (string) Str::ulid(),
            mandanteId: $mandante->id,
            codigo: new CodigoProyecto('COB_UNICO'),
            nombre: 'Otro intento',
            descripcion: null,
            tipoOperacion: TipoOperacion::VENTA,
            fechaInicio: null,
            fechaFin: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        ));
    }

    public function test_permite_mismo_codigo_en_mandantes_distintos(): void
    {
        $regMandante = $this->app->make(RegistrarMandante::class);
        $regProyecto = $this->app->make(RegistrarProyecto::class);

        $m1 = $regMandante->execute(new RegistrarMandanteInput(
            publicId: (string) Str::ulid(),
            codigo: new CodigoMandante('BANCO_A'),
            nombre: 'Banco A',
            documento: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        ));
        $m2 = $regMandante->execute(new RegistrarMandanteInput(
            publicId: (string) Str::ulid(),
            codigo: new CodigoMandante('BANCO_B'),
            nombre: 'Banco B',
            documento: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        ));

        $regProyecto->execute(new RegistrarProyectoInput(
            publicId: (string) Str::ulid(),
            mandanteId: $m1->id,
            codigo: new CodigoProyecto('COB_2026'),
            nombre: 'Cobranza Banco A 2026',
            descripcion: null,
            tipoOperacion: TipoOperacion::COBRANZA,
            fechaInicio: null,
            fechaFin: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        ));
        $regProyecto->execute(new RegistrarProyectoInput(
            publicId: (string) Str::ulid(),
            mandanteId: $m2->id,
            codigo: new CodigoProyecto('COB_2026'),
            nombre: 'Cobranza Banco B 2026',
            descripcion: null,
            tipoOperacion: TipoOperacion::COBRANZA,
            fechaInicio: null,
            fechaFin: null,
            creadaEn: new DateTimeImmutable('2026-04-17'),
        ));

        $this->assertSame(2, DB::table('proyectos')->where('codigo', 'COB_2026')->count());
    }
}
