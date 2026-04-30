<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Contactos;

use App\Modules\Contactos\Application\DTOs\RegistrarContactoInput;
use App\Modules\Contactos\Application\UseCases\RegistrarContacto;
use App\Modules\Contactos\Domain\Exceptions\DatosContactoInvalidos;
use App\Modules\Contactos\Domain\ValueObjects\TipoContacto;
use App\Modules\Contactos\Infrastructure\Persistence\Models\ContactoModel;
use Database\Seeders\Catalogos\TiposIdentificacionSeeder;
use Database\Seeders\Tenancy\MandantesDemoSeeder;
use Database\Seeders\Tenancy\ProyectosDemoSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RegistrarContactoTest extends TestCase
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

    public function test_registra_contacto_y_respeta_scope_del_proyecto(): void
    {
        $proyectoA = $this->idProyecto('COBRANZA_DEMO_2026');
        $proyectoB = $this->crearProyectoExtra('OTRO_COB_2026');

        $personaA = $this->crearPersona($proyectoA, '0102030405');
        $personaB = $this->crearPersona($proyectoB, '0102030405');

        $useCase = $this->app->make(RegistrarContacto::class);

        $useCase->execute(new RegistrarContactoInput(
            proyectoId: $proyectoA,
            personaId: $personaA,
            tipo: TipoContacto::CORREO,
            valor: 'juan.a@correo.com',
            etiqueta: null,
            esPrincipal: true,
            creadaEn: new DateTimeImmutable,
        ));

        $useCase->execute(new RegistrarContactoInput(
            proyectoId: $proyectoB,
            personaId: $personaB,
            tipo: TipoContacto::CORREO,
            valor: 'juan.b@correo.com',
            etiqueta: null,
            esPrincipal: true,
            creadaEn: new DateTimeImmutable,
        ));

        // Global scope filtra por proyecto activo.
        $this->setProyectoActivo($proyectoA);
        $this->assertSame(1, ContactoModel::query()->count());
        $this->assertTrue(ContactoModel::query()->where('valor', 'juan.a@correo.com')->exists());
        $this->assertFalse(ContactoModel::query()->where('valor', 'juan.b@correo.com')->exists());
    }

    public function test_rechaza_valor_duplicado_para_la_misma_persona(): void
    {
        $proyectoId = $this->idProyecto('COBRANZA_DEMO_2026');
        $personaId = $this->crearPersona($proyectoId, '0102030405');
        $useCase = $this->app->make(RegistrarContacto::class);

        $input = fn () => new RegistrarContactoInput(
            proyectoId: $proyectoId,
            personaId: $personaId,
            tipo: TipoContacto::TELEFONO,
            valor: '+593 98 123 4567',
            etiqueta: null,
            esPrincipal: false,
            creadaEn: new DateTimeImmutable,
        );

        $useCase->execute($input());

        $this->expectException(DatosContactoInvalidos::class);
        $useCase->execute($input());
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
            'nombre' => "Proyecto extra {$codigo}",
            'tipo_operacion' => 'cobranza',
            'activo' => true,
        ]);
    }

    private function crearPersona(int $proyectoId, string $identificacion): int
    {
        $tipoCed = (int) DB::table('tipos_identificacion')->where('codigo', 'CED')->value('id');

        return (int) DB::table('personas')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'proyecto_id' => $proyectoId,
            'tipo_persona' => 'fisica',
            'tipo_identificacion_id' => $tipoCed,
            'identificacion' => $identificacion,
            'nombres' => 'Test',
            'apellidos' => 'User',
        ]);
    }

    private function setProyectoActivo(int $proyectoId): void
    {
        $proyecto = DB::table('proyectos')->find($proyectoId);
        $this->app->instance('tenancy.proyecto_activo', $proyecto);
    }
}
