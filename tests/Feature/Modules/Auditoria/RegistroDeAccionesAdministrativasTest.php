<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auditoria;

use App\Modules\Auditoria\Domain\Contracts\RegistroDeAccionesAdministrativas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El contrato por el que las acciones administrativas dejan rastro.
 *
 * Existe porque el observer genérico no llega: los roles viven en pivotes de
 * clave compuesta, que ningún observer Eloquent ve pasar, y las cuentas viven
 * en `users`, donde una foto de todos los atributos arrastraría el hash de la
 * contraseña. Aquí se comprueba lo que el contrato promete por su cuenta, sin
 * pasar por ninguna pantalla.
 */
final class RegistroDeAccionesAdministrativasTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_un_evento_con_proyecto_hereda_el_cliente_de_ese_proyecto(): void
    {
        $mandante = $this->crearMandante('MND_HEREDA');
        $proyecto = $this->crearProyectoCobranza($mandante);

        $this->registro()->alta('usuario_proyecto_rol', 7, ['rol_codigo' => 'GESTOR'], (int) $proyecto->id);

        $this->assertSame(
            (int) $mandante->id,
            (int) DB::table('auditorias')->orderByDesc('id')->value('mandante_id'),
            'El evento se guardó sin dueño teniendo un proyecto delante: atribuirlo en la lectura, saltando '
            .'a `proyectos`, hace que el historial se reescriba solo si el proyecto cambia de cliente.'
        );
    }

    public function test_un_evento_sin_proyecto_puede_llevar_su_cliente_de_todos_modos(): void
    {
        $mandante = $this->crearMandante('MND_SIN_PROYECTO');

        // El caso de toda acción administrativa: una cuenta no pertenece a
        // ningún proyecto, y sin `mandante_id` el evento no sería de nadie.
        $this->registro()->alta('users', 12, ['email' => 'alguien@crm.local'], null, (int) $mandante->id);

        $fila = DB::table('auditorias')->orderByDesc('id')->first();

        $this->assertNull($fila->proyecto_id);
        $this->assertSame((int) $mandante->id, (int) $fila->mandante_id);
    }

    public function test_un_cambio_sin_diferencias_no_escribe_nada(): void
    {
        $this->registro()->cambio('users', 12, []);

        $this->assertSame(
            0,
            DB::table('auditorias')->count(),
            'Guardar sin tocar nada escribió un evento: la auditoría se lee cuando algo va mal, y el ruido '
            .'es lo que hace que no se lea.'
        );
    }

    public function test_el_evento_recuerda_quien_lo_hizo(): void
    {
        $adminGlobal = $this->crearAdminGlobal();
        $this->actingAs($adminGlobal);

        $this->registro()->baja('usuario_global_rol', 12, ['rol_codigo' => 'ADMIN_GLOBAL']);

        $fila = DB::table('auditorias')->orderByDesc('id')->first();

        $this->assertSame((int) $adminGlobal->id, (int) $fila->usuario_id);
        $this->assertSame('eliminado', (string) $fila->evento);
        $this->assertSame(
            ['rol_codigo' => 'ADMIN_GLOBAL'],
            json_decode((string) $fila->datos_antes, true),
            'De una baja sólo queda lo que se guardó aquí: la fila que la provocó ya no existe.'
        );
    }

    private function registro(): RegistroDeAccionesAdministrativas
    {
        return app(RegistroDeAccionesAdministrativas::class);
    }
}
