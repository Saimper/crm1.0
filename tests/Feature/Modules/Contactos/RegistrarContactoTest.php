<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Contactos;

use App\Modules\Contactos\Application\DTOs\RegistrarContactoInput;
use App\Modules\Contactos\Application\UseCases\RegistrarContacto;
use App\Modules\Contactos\Domain\Exceptions\DatosContactoInvalidos;
use App\Modules\Contactos\Domain\ValueObjects\TipoContacto;
use App\Modules\Contactos\Infrastructure\Persistence\Models\ContactoModel;
use Database\Seeders\DatabaseSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class RegistrarContactoTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_registra_contacto_y_respeta_scope_del_proyecto(): void
    {
        $mandante = $this->crearMandante();
        $proyectoA = $this->crearProyectoCobranza($mandante);
        $proyectoB = $this->crearProyectoCobranza($mandante);

        // Misma identificación en ambos proyectos: personas son por-proyecto (§2).
        $personaA = $this->crearPersonaEn($proyectoA, '0102030405');
        $personaB = $this->crearPersonaEn($proyectoB, '0102030405');

        $useCase = $this->app->make(RegistrarContacto::class);

        $useCase->execute(new RegistrarContactoInput(
            proyectoId: (int) $proyectoA->id,
            personaId: (int) $personaA->id,
            tipo: TipoContacto::CORREO,
            valor: 'juan.a@correo.com',
            etiqueta: null,
            esPrincipal: true,
            creadaEn: new DateTimeImmutable,
        ));

        $useCase->execute(new RegistrarContactoInput(
            proyectoId: (int) $proyectoB->id,
            personaId: (int) $personaB->id,
            tipo: TipoContacto::CORREO,
            valor: 'juan.b@correo.com',
            etiqueta: null,
            esPrincipal: true,
            creadaEn: new DateTimeImmutable,
        ));

        // Global scope filtra por proyecto activo.
        $this->activarProyecto($proyectoA);
        $this->assertSame(1, ContactoModel::query()->count());
        $this->assertTrue(ContactoModel::query()->where('valor', 'juan.a@correo.com')->exists());
        $this->assertFalse(ContactoModel::query()->where('valor', 'juan.b@correo.com')->exists());
    }

    public function test_rechaza_valor_duplicado_para_la_misma_persona(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto, '0102030405');
        $useCase = $this->app->make(RegistrarContacto::class);

        $input = fn () => new RegistrarContactoInput(
            proyectoId: (int) $proyecto->id,
            personaId: (int) $persona->id,
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
}
