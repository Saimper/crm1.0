<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalogos;

use App\Modules\Tenancy\Application\DTOs\RegistrarProyectoInput;
use App\Modules\Tenancy\Application\UseCases\RegistrarProyecto;
use App\Modules\Tenancy\Domain\Events\ProyectoCreado;
use App\Modules\Tenancy\Domain\ValueObjects\CodigoProyecto;
use App\Modules\Tenancy\Domain\ValueObjects\TipoOperacion;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * F35-D: al crear un proyecto se siembran estados ABIERTO + CERRADO automáticamente
 * para que el flujo Crear Caso funcione sin requerir setup manual previo.
 */
final class SeedEstadosCasoPorDefectoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_crear_proyecto_siembra_4_estados_default(): void
    {
        $mandante = $this->crearMandante();

        $output = app(RegistrarProyecto::class)->execute(new RegistrarProyectoInput(
            publicId: (string) Str::ulid(),
            mandanteId: $mandante->id,
            codigo: new CodigoProyecto('TEST_'.Str::random(6)),
            nombre: 'Proyecto Test',
            descripcion: null,
            tipoOperacion: TipoOperacion::COBRANZA,
            fechaInicio: null,
            fechaFin: null,
            creadaEn: new DateTimeImmutable,
        ));

        $estados = DB::table('estados_caso')
            ->where('proyecto_id', $output->id)
            ->orderBy('orden')
            ->get();

        $this->assertSame(4, $estados->count(), 'Debe sembrar Abierto, Asignado, En progreso, Finalizado.');

        $codigos = $estados->pluck('codigo')->all();
        $this->assertSame(['ABIERTO', 'ASIGNADO', 'EN_PROGRESO', 'FINALIZADO'], $codigos);

        $finalizado = $estados->firstWhere('codigo', 'FINALIZADO');
        $this->assertSame(1, (int) $finalizado->es_terminal, 'Solo Finalizado es terminal.');

        foreach (['ABIERTO', 'ASIGNADO', 'EN_PROGRESO'] as $codigo) {
            $estado = $estados->firstWhere('codigo', $codigo);
            $this->assertSame(0, (int) $estado->es_terminal, "{$codigo} no debe ser terminal.");
        }
    }

    public function test_proyecto_con_estados_existentes_no_se_re_siembra(): void
    {
        $mandante = $this->crearMandante();

        $output = app(RegistrarProyecto::class)->execute(new RegistrarProyectoInput(
            publicId: (string) Str::ulid(),
            mandanteId: $mandante->id,
            codigo: new CodigoProyecto('TEST_'.Str::random(6)),
            nombre: 'Proyecto Test',
            descripcion: null,
            tipoOperacion: TipoOperacion::CX,
            fechaInicio: null,
            fechaFin: null,
            creadaEn: new DateTimeImmutable,
        ));

        $totalAntes = (int) DB::table('estados_caso')->where('proyecto_id', $output->id)->count();
        $this->assertSame(4, $totalAntes);

        // Re-disparar el listener no duplica
        event(new ProyectoCreado(
            proyectoId: $output->id,
            publicId: $output->publicId,
            mandanteId: $mandante->id,
            tipoOperacion: TipoOperacion::CX,
            creadaEn: new DateTimeImmutable,
        ));

        $totalDespues = (int) DB::table('estados_caso')->where('proyecto_id', $output->id)->count();
        $this->assertSame(4, $totalDespues, 'Listener idempotente: no re-siembra si ya hay estados.');
    }
}
