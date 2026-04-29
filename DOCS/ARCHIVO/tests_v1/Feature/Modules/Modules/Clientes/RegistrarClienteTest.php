<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Clientes;

use App\Modules\Clientes\Application\DTOs\RegistrarClienteInput;
use App\Modules\Clientes\Application\Exceptions\IdentificacionYaExistente;
use App\Modules\Clientes\Application\UseCases\RegistrarCliente;
use App\Modules\Clientes\Domain\ValueObjects\Identificacion;
use App\Modules\Clientes\Domain\ValueObjects\TipoPersona;
use Database\Seeders\CatalogosSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RegistrarClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);
    }

    public function test_registra_persona_fisica_y_persiste_en_bd(): void
    {
        $tipoId = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');

        $output = $this->app->make(RegistrarCliente::class)->execute(new RegistrarClienteInput(
            publicId:             (string) Str::ulid(),
            tipoPersona:          TipoPersona::FISICA,
            tipoIdentificacionId: $tipoId,
            identificacion:       new Identificacion('0102030405'),
            nombres:              'Juan',
            apellidos:            'Pérez',
            razonSocial:          null,
            fechaNacimiento:      null,
            creadaEn:             new DateTimeImmutable('2026-04-17'),
        ));

        $this->assertGreaterThan(0, $output->id);
        $this->assertSame('Juan Pérez', $output->nombreCompleto);
        $this->assertDatabaseHas('clientes', [
            'id'             => $output->id,
            'identificacion' => '0102030405',
            'tipo_persona'   => 'fisica',
            'nombres'        => 'Juan',
            'apellidos'      => 'Pérez',
            'razon_social'   => null,
        ]);
    }

    public function test_registra_persona_juridica_y_descarta_nombres(): void
    {
        $tipoId = (int) DB::table('tipos_identificacion')->where('codigo', 'RUC')->value('id');

        $output = $this->app->make(RegistrarCliente::class)->execute(new RegistrarClienteInput(
            publicId:             (string) Str::ulid(),
            tipoPersona:          TipoPersona::JURIDICA,
            tipoIdentificacionId: $tipoId,
            identificacion:       new Identificacion('1792345678001'),
            nombres:              'Ignorado',
            apellidos:            'Ignorado',
            razonSocial:          'Comercial Austral S.A.',
            fechaNacimiento:      null,
            creadaEn:             new DateTimeImmutable('2026-04-17'),
        ));

        $this->assertDatabaseHas('clientes', [
            'id'           => $output->id,
            'tipo_persona' => 'juridica',
            'razon_social' => 'Comercial Austral S.A.',
            'nombres'      => null,
            'apellidos'    => null,
        ]);
    }

    public function test_throws_cuando_identificacion_ya_existe(): void
    {
        $tipoId = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');
        $useCase = $this->app->make(RegistrarCliente::class);

        $input = fn (string $id, string $publicId) => new RegistrarClienteInput(
            publicId:             $publicId,
            tipoPersona:          TipoPersona::FISICA,
            tipoIdentificacionId: $tipoId,
            identificacion:       new Identificacion($id),
            nombres:              'Juan',
            apellidos:            'Pérez',
            razonSocial:          null,
            fechaNacimiento:      null,
            creadaEn:             new DateTimeImmutable('2026-04-17'),
        );

        $useCase->execute($input('0102030405', (string) Str::ulid()));

        $this->expectException(IdentificacionYaExistente::class);
        $useCase->execute($input('0102030405', (string) Str::ulid()));
    }
}
