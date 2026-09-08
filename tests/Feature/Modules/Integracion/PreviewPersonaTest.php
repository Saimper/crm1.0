<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use App\Models\User;
use App\Modules\Integracion\Application\UseCases\EmitirSanctumTokenDesdeJwt;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class PreviewPersonaTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_con_sanctum_auth_y_persona_existente_devuelve_200_con_json(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $usuario = $this->crearGestor($proyecto);
        $tiCodigo = $this->codigoTipoIdentificacionDe($persona->tipo_identificacion_id);

        Sanctum::actingAs($usuario, $this->habilidadesPara($proyecto));

        $response = $this->getJson("/api/integracion/persona?identificacion={$persona->identificacion}&tipo_identificacion_codigo={$tiCodigo}&proyecto_id={$proyecto->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'persona' => ['public_id', 'nombre', 'identificacion', 'tipo_identificacion'],
                'casos',
                'compromiso_vigente',
                'ultima_gestion',
            ]);
    }

    public function test_sin_auth_devuelve_401(): void
    {
        $response = $this->getJson('/api/integracion/persona?identificacion=123&tipo_identificacion_codigo=CC&proyecto_id=1');
        $response->assertStatus(401);
    }

    public function test_persona_en_otro_proyecto_devuelve_404(): void
    {
        $proyectoA = $this->crearProyectoCobranza();
        $proyectoB = $this->crearProyectoServicio();
        $usuario = $this->crearGestor($proyectoA);

        // Persona del proyecto A buscada con proyecto_id del proyecto B
        $persona = $this->crearPersonaEn($proyectoA);
        $tiCodigo = $this->codigoTipoIdentificacionDe($persona->tipo_identificacion_id);

        Sanctum::actingAs($usuario, $this->habilidadesPara($proyectoA));

        $response = $this->getJson("/api/integracion/persona?identificacion={$persona->identificacion}&tipo_identificacion_codigo={$tiCodigo}&proyecto_id={$proyectoB->id}");

        // El usuario no tiene acceso al proyecto B, entonces 403
        $response->assertStatus(403);
    }

    public function test_usuario_sin_rol_en_proyecto_devuelve_403(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $tiCodigo = $this->codigoTipoIdentificacionDe($persona->tipo_identificacion_id);

        // Usuario sin rol en ningún proyecto
        /** @var User $sinRol */
        $sinRol = User::query()->create([
            'name' => 'Sin Rol',
            'email' => 'sinrol.'.Str::random(6).'@crm.local',
            'password' => Hash::make('x'),
            'activo' => true,
        ]);

        Sanctum::actingAs($sinRol, $this->habilidadesPara($proyecto));

        $response = $this->getJson("/api/integracion/persona?identificacion={$persona->identificacion}&tipo_identificacion_codigo={$tiCodigo}&proyecto_id={$proyecto->id}");

        $response->assertStatus(403);
    }

    public function test_persona_inexistente_devuelve_404(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $usuario = $this->crearGestor($proyecto);

        Sanctum::actingAs($usuario, $this->habilidadesPara($proyecto));

        $response = $this->getJson("/api/integracion/persona?identificacion=99999999NOEXISTE&tipo_identificacion_codigo=CC&proyecto_id={$proyecto->id}");

        $response->assertStatus(404);
    }

    private function codigoTipoIdentificacionDe(int $tipoIdentificacionId): string
    {
        return (string) DB::table('tipos_identificacion')->where('id', $tipoIdentificacionId)->value('codigo');
    }

    /**
     * Un proyecto archivado deja de existir para todo el mundo, también para el
     * wrapper. Se cerraron sus tres caminos de dentro —listado, selector y
     * URL— y esta puerta se quedaba abierta.
     */
    public function test_un_proyecto_archivado_no_entrega_fichas_por_la_api(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $usuario = $this->crearGestor($proyecto);
        $tiCodigo = $this->codigoTipoIdentificacionDe($persona->tipo_identificacion_id);
        $url = "/api/integracion/persona?identificacion={$persona->identificacion}&tipo_identificacion_codigo={$tiCodigo}&proyecto_id={$proyecto->id}";

        Sanctum::actingAs($usuario, $this->habilidadesPara($proyecto));
        $this->getJson($url)->assertStatus(200);

        DB::table('proyectos')->where('id', $proyecto->id)->update(['eliminada_en' => now()]);

        $this->getJson($url)->assertStatus(403);
    }

    /**
     * Las habilidades con las que se emite un token de verdad: las dos de la
     * API más la del mandante dueño del proyecto. Sin la última, la ficha
     * responde 403 aunque el usuario tenga acceso — que es justo lo que este
     * token tiene que garantizar.
     *
     * @return list<string>
     */
    private function habilidadesPara(stdClass $proyecto): array
    {
        return [
            ...EmitirSanctumTokenDesdeJwt::HABILIDADES,
            EmitirSanctumTokenDesdeJwt::habilidadDeMandante((int) $proyecto->mandante_id),
        ];
    }

    /**
     * Un token del OTRO cliente: mismas habilidades de API, distinto mandante.
     * Es el caso que la ficha dejaba pasar cuando el mandante sólo iba escrito
     * en el nombre del token y nadie lo leía.
     */
    public function test_un_token_de_otro_mandante_no_alcanza_la_ficha(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $usuario = $this->crearGestor($proyecto);
        $tiCodigo = $this->codigoTipoIdentificacionDe($persona->tipo_identificacion_id);

        $ajeno = $this->crearProyectoCobranza($this->crearMandante());

        Sanctum::actingAs($usuario, $this->habilidadesPara($ajeno));

        $this->getJson("/api/integracion/persona?identificacion={$persona->identificacion}&tipo_identificacion_codigo={$tiCodigo}&proyecto_id={$proyecto->id}")
            ->assertStatus(403);
    }

    /** Un token anterior a este cambio no lleva mandante, y se le niega. */
    public function test_un_token_sin_habilidad_de_mandante_no_alcanza_la_ficha(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $persona = $this->crearPersonaEn($proyecto);
        $usuario = $this->crearGestor($proyecto);
        $tiCodigo = $this->codigoTipoIdentificacionDe($persona->tipo_identificacion_id);

        Sanctum::actingAs($usuario, EmitirSanctumTokenDesdeJwt::HABILIDADES);

        $this->getJson("/api/integracion/persona?identificacion={$persona->identificacion}&tipo_identificacion_codigo={$tiCodigo}&proyecto_id={$proyecto->id}")
            ->assertStatus(403);
    }
}
