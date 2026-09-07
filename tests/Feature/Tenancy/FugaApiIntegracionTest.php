<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use stdClass;
use Tests\Support\EscenarioMultiMandante;
use Tests\TestCase;

/**
 * Fase 0 — red de fugas de la API de integración (routes/api.php).
 *
 * La API tiene DOS puertas y sólo una sabe qué es un mandante:
 *
 *  - GET /api/integracion/proyectos entra por `hmac.mandante`, que firma con el
 *    sso_secret del mandante e inyecta `mandante_id` en el request. Ahí el
 *    límite existe y estos tests lo fijan para que nadie lo borre.
 *
 *  - POST /api/integracion/sanctum-token emite un PAT de Sanctum a partir del
 *    JWT del wrapper. El JWT sí trae `mandante_id` y el AutenticadorPorJwt lo
 *    valida... y acto seguido lo tira a la basura: el PAT sale con
 *    `createToken('wrapper-sso')` — abilities ['*'] y `expires_at` NULL, porque
 *    config/sanctum.php:53 tiene `'expiration' => null`. El bearer resultante
 *    no recuerda por qué mandante fue emitido.
 *
 *    Aguas abajo, GET /api/integracion/persona sólo pregunta
 *    `tieneAccesoAProyecto($proyectoId)` (PreviewPersonaController.php:27). Esa
 *    pregunta es del mundo PROYECTO, no del mundo MANDANTE: si el mismo correo
 *    entró alguna vez por el wrapper de otro cliente, el PAT del mandante A
 *    lee datos del mandante B sin que nadie lo note.
 *
 * Los tests que exigen esa comprobación FALLAN hoy. Cada fallo es la fuga.
 */
final class FugaApiIntegracionTest extends TestCase
{
    use EscenarioMultiMandante;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // ---------------------------------------------------------------- HMAC --

    /**
     * El único endpoint con contexto de mandante de verdad. Se fija su
     * comportamiento correcto para que la Fase 1 no lo rompa al generalizar.
     */
    public function test_hmac_del_mandante_a_solo_lista_proyectos_de_a(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $response = $this->get(
            '/api/integracion/proyectos',
            $this->firmarHmac((int) $a['mandante']->id, (string) $a['mandante']->sso_secret)
        );

        $response->assertOk();

        $this->assertSame((int) $a['mandante']->id, $response->json('mandante_id'));
        $this->assertSame(
            [(int) $a['proyecto']->id],
            $this->idsDe($response->json('proyectos')),
            'GET /api/integracion/proyectos debe listar sólo proyectos del mandante que firmó.'
        );

        $this->assertNoSeFiltra(
            (string) $response->getContent(),
            $b,
            'GET /api/integracion/proyectos con HMAC de A'
        );
    }

    /**
     * El mandante lo decide la FIRMA, no la cabecera: firmar con el secret de A
     * y declarar `X-Mandante-Id: B` no debe abrir los proyectos de B.
     */
    public function test_hmac_firmada_por_a_no_sirve_para_pedir_los_proyectos_de_b(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $timestamp = time();
        $headers = [
            'X-Mandante-Id' => (string) $b['mandante']->id,
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => hash_hmac('sha256', (string) $timestamp, (string) $a['mandante']->sso_secret),
        ];

        $response = $this->get('/api/integracion/proyectos', $headers);

        $this->assertSame(
            401,
            $response->status(),
            'Con el secret de A no se puede impersonar al mandante B por cabecera.'
        );

        $this->assertNoSeFiltra(
            (string) $response->getContent(),
            $b,
            'GET /api/integracion/proyectos firmado por A pidiendo B'
        );
    }

    // ------------------------------------------------- Forma del PAT emitido -

    /**
     * FUGA: EmitirSanctumTokenDesdeJwt.php:30 llama a `createToken('wrapper-sso')`
     * sin abilities, así que Sanctum guarda ["*"]. Un bearer pensado para
     * previsualizar una ficha queda habilitado para cualquier API futura.
     */
    public function test_el_pat_del_wrapper_no_debe_emitirse_con_abilities_de_comodin(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $this->emitirPat($a['mandante'], $a['proyecto'], 'pat.abilities@wrap.io');

        $abilities = $this->abilitiesDelUltimoPat();

        $this->assertNotContains(
            '*',
            $abilities,
            'El PAT del wrapper se emite con abilities ["*"]: sirve para cualquier endpoint, no sólo para la integración.'
        );
        $this->assertNotSame(
            [],
            $abilities,
            'El PAT debe declarar explícitamente qué puede hacer.'
        );
    }

    /**
     * FUGA: `config/sanctum.php:53` deja `'expiration' => null` y el use case no
     * pasa `expiresAt`, así que el PAT del wrapper NUNCA caduca. Un token
     * filtrado del wrapper del mandante A vale para siempre.
     */
    public function test_el_pat_del_wrapper_debe_caducar(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $this->emitirPat($a['mandante'], $a['proyecto'], 'pat.caducidad@wrap.io');

        $pat = $this->ultimoPat();

        $this->assertNotNull(
            $pat->expires_at,
            'El PAT emitido desde el JWT del wrapper no tiene expires_at: es un bearer eterno.'
        );
    }

    /**
     * FUGA de trazabilidad: el JWT trae `mandante_id`, el use case lo devuelve en
     * el JSON... y no lo graba en ninguna parte del token. Después nadie puede
     * preguntarle al bearer de qué mandante viene, que es exactamente lo que
     * hace falta para cerrar la fuga del test siguiente.
     */
    public function test_el_pat_emitido_debe_ser_atribuible_al_mandante_que_lo_pidio(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $this->emitirPat($a['mandante'], $a['proyecto'], 'pat.atribuible@wrap.io');

        $pat = $this->ultimoPat();
        $huella = ((string) $pat->name).'|'.((string) ($pat->abilities ?? ''));

        $this->assertStringContainsString(
            (string) $a['mandante']->id,
            $huella,
            "El PAT no guarda rastro del mandante {$a['mandante']->id} que lo emitió (name='{$pat->name}', abilities='{$pat->abilities}'): el token es un ciudadano sin patria."
        );
    }

    // ----------------------------------------------- Límite en preview persona

    /**
     * LA FUGA CENTRAL de esta superficie.
     *
     * Escenario nada exótico en un BPO: el mismo correo trabaja para dos
     * clientes y entra por los dos wrappers. Cada handshake le deja un pivot de
     * GESTOR en el proyecto de su mandante. A partir de ahí, el PAT emitido por
     * el wrapper de A vale para leer la ficha de una persona del mandante B,
     * porque PreviewPersonaController.php:27 sólo comprueba el acceso al
     * PROYECTO y nunca que el proyecto sea del mandante que emitió el token.
     */
    public function test_el_pat_del_mandante_a_no_puede_leer_una_persona_del_mandante_b(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $correoCompartido = 'agente.compartido@wrap.io';

        $patA = $this->emitirPat($a['mandante'], $a['proyecto'], $correoCompartido);
        $this->emitirPat($b['mandante'], $b['proyecto'], $correoCompartido);

        $response = $this->consultarPersona($patA, $b['proyecto'], $b['persona']);

        $this->assertSame(
            403,
            $response->status(),
            'Un PAT emitido por el wrapper del mandante A leyó la ficha de una persona del mandante B.'
        );

        $this->assertStringNotContainsString(
            (string) $b['persona']->public_id,
            (string) $response->getContent(),
            'La respuesta filtró el public_id de la persona del otro mandante.'
        );
    }

    /**
     * El caso simple del mismo límite: un usuario que sólo existe en A pide un
     * proyecto de B. Aquí el chequeo de proyecto sí alcanza, y se fija.
     */
    public function test_usuario_de_a_no_puede_leer_una_persona_de_b(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $response = $this->actingAs($a['gestor'], 'sanctum')
            ->getJson($this->urlPersona($b['proyecto'], $b['persona']));

        $this->assertSame(
            403,
            $response->status(),
            'El gestor del mandante A no debe poder consultar personas del mandante B.'
        );

        $this->assertStringNotContainsString(
            (string) $b['persona']->public_id,
            (string) $response->getContent(),
            'La respuesta filtró el public_id de la persona del otro mandante.'
        );
    }

    /**
     * Misma cédula en los dos mandantes — el caso real que rompe cualquier
     * búsqueda por identificación. Se consulta con un PAT que SÍ tiene acceso a
     * los dos proyectos (el correo compartido del test anterior): si el filtro
     * por proyecto no estuviera, aquí es donde aparecería la gemela. Con un
     * usuario que sólo ve A el test no probaría nada.
     */
    public function test_misma_identificacion_en_dos_mandantes_devuelve_solo_la_del_proyecto_pedido(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $identificacion = (string) $a['persona']->identificacion;
        $gemelaEnB = $this->crearPersonaEn($b['proyecto'], $identificacion);

        $correoCompartido = 'gemela.compartida@wrap.io';
        $patA = $this->emitirPat($a['mandante'], $a['proyecto'], $correoCompartido);
        $this->emitirPat($b['mandante'], $b['proyecto'], $correoCompartido);

        $response = $this->consultarPersona($patA, $a['proyecto'], $a['persona']);

        $response->assertOk();

        $this->assertSame(
            (string) $a['persona']->public_id,
            (string) $response->json('persona.public_id'),
            'Debe devolver la persona del proyecto consultado.'
        );
        $this->assertStringNotContainsString(
            (string) $gemelaEnB->public_id,
            (string) $response->getContent(),
            'Se filtró la persona homónima del otro mandante.'
        );

        $publicIdCasoDeA = (string) DB::table('casos')->where('id', $a['casoId'])->value('public_id');

        $this->assertSame(
            [$publicIdCasoDeA],
            array_map(
                static fn (array $caso): string => (string) $caso['public_id'],
                (array) $response->json('casos')
            ),
            'Los casos devueltos deben ser los del proyecto consultado y sólo esos.'
        );
    }

    /**
     * La misma fuga con radio de explosión mayor. Si el correo compartido entró
     * en B como `admin_tenant`, su pivot vive en `usuario_mandante_rol` y
     * User.php:93 le abre TODOS los proyectos de B, incluso los que nunca tocó.
     * El PAT emitido por el wrapper de A hereda ese alcance completo.
     */
    public function test_el_pat_del_mandante_a_no_alcanza_los_proyectos_de_b_por_rol_de_mandante(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $otroProyectoDeB = $this->crearProyectoCx($b['mandante']);
        $personaAjena = $this->crearPersonaEn($otroProyectoDeB);

        $correoCompartido = 'admin.compartido@wrap.io';

        $patA = $this->emitirPat($a['mandante'], $a['proyecto'], $correoCompartido);

        $this->postJson('/api/integracion/sanctum-token', [
            'token' => $this->jwtWrapper($b['mandante'], [
                'sub' => $correoCompartido,
                'wrapper_role' => 'admin_tenant',
                'proyecto_id' => (int) $b['proyecto']->id,
            ]),
        ])->assertStatus(201);

        $response = $this->consultarPersona($patA, $otroProyectoDeB, $personaAjena);

        $this->assertSame(
            403,
            $response->status(),
            'El PAT del wrapper de A leyó un proyecto del mandante B al que su portador llegó por rol de mandante.'
        );

        $this->assertStringNotContainsString(
            (string) $personaAjena->public_id,
            (string) $response->getContent(),
            'La respuesta filtró la persona de un proyecto del otro mandante.'
        );
    }

    /**
     * FUGA: el bearer no recuerda de qué mandante viene, así que tampoco se
     * entera de que ese mandante dejó de ser cliente. Desactivar el mandante
     * cierra las dos puertas de entrada — VerificarFirmaHmacMandante y
     * AutenticadorPorJwt filtran por `activo` — pero no toca ni un solo PAT ya
     * emitido, que sigue leyendo fichas indefinidamente (no caduca nunca).
     */
    public function test_desactivar_el_mandante_debe_cerrar_los_pat_que_emitio(): void
    {
        ['a' => $a] = $this->montarDosMandantes();

        $patA = $this->emitirPat($a['mandante'], $a['proyecto'], 'baja.mandante@wrap.io');

        DB::table('mandantes')->where('id', $a['mandante']->id)->update(['activo' => false]);

        $response = $this->consultarPersona($patA, $a['proyecto'], $a['persona']);

        $this->assertNotSame(
            200,
            $response->status(),
            'El mandante quedó desactivado y su PAT siguió leyendo fichas: el token no está atado al mandante.'
        );

        $this->assertStringNotContainsString(
            (string) $a['persona']->public_id,
            (string) $response->getContent(),
            'Un PAT de un mandante desactivado siguió devolviendo datos.'
        );
    }

    /**
     * Firma válida de A, pero el JWT declara un proyecto de B: el
     * AutenticadorPorJwt corta con MandanteProyectoMismatch → 403 y no debe
     * quedar ni PAT ni usuario JIT. MultiTenancyJwtTest ya lo fija para el
     * handshake por browser; la puerta server-to-server no lo tenía cubierto.
     */
    public function test_sanctum_token_firmado_por_a_con_proyecto_de_b_no_emite_pat(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $response = $this->postJson('/api/integracion/sanctum-token', [
            'token' => $this->jwtWrapper($a['mandante'], [
                'sub' => 'cruzado@wrap.io',
                'wrapper_role' => 'agent',
                'proyecto_id' => (int) $b['proyecto']->id,
            ]),
        ]);

        $this->assertSame(
            403,
            $response->status(),
            'Un JWT de A pidiendo un proyecto de B debe rechazarse con 403.'
        );

        $this->assertSame(
            0,
            DB::table('personal_access_tokens')->count(),
            'Se emitió un PAT pese al cruce mandante/proyecto.'
        );

        $this->assertNull(
            User::query()->where('email', 'cruzado@wrap.io')->first(),
            'Se provisionó un usuario JIT a partir de un JWT rechazado.'
        );

        $this->assertNoSeFiltra(
            (string) $response->getContent(),
            $b,
            'POST /api/integracion/sanctum-token firmado por A pidiendo proyecto de B'
        );
    }


    // ------------------------------------------------- Anti-replay y logout --

    /**
     * FUGA cruzada: `sso_tokens_consumidos.jti` es PRIMARY KEY global
     * (2026_05_01_120001_integracion_create_sso_tokens_consumidos_table.php) y
     * RepositorioTokensConsumidosEloquent.php:22 consulta el jti sin mandante
     * (AutenticadorPorJwt.php:92). El espacio anti-replay está compartido: si el
     * wrapper del mandante B quema un jti — o lo elige a propósito —, el
     * handshake legítimo del mandante A con ese mismo jti muere con 410.
     *
     * Cerrarlo obliga a que la clave anti-replay sea (mandante_id, jti) y no el
     * jti a secas: la columna `mandante_id` ya existe desde F37, sólo que nadie
     * la usa para decidir. Por eso la segunda aserción exige una fila por
     * mandante — hoy la PK global ni siquiera lo permitiría.
     */
    public function test_un_jti_consumido_por_el_mandante_b_no_debe_bloquear_al_mandante_a(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $jtiCompartido = (string) Str::uuid();

        $this->postJson('/api/integracion/sanctum-token', [
            'token' => $this->jwtWrapper($b['mandante'], [
                'sub' => 'quema.jti@wrap.io',
                'wrapper_role' => 'agent',
                'proyecto_id' => (int) $b['proyecto']->id,
                'jti' => $jtiCompartido,
            ]),
        ])->assertStatus(201);

        $response = $this->postJson('/api/integracion/sanctum-token', [
            'token' => $this->jwtWrapper($a['mandante'], [
                'sub' => 'victima.jti@wrap.io',
                'wrapper_role' => 'agent',
                'proyecto_id' => (int) $a['proyecto']->id,
                'jti' => $jtiCompartido,
            ]),
        ]);

        $this->assertSame(
            201,
            $response->status(),
            'El mandante B quemó el jti y el handshake legítimo del mandante A quedó bloqueado: el anti-replay no está acotado por mandante.'
        );

        foreach (['A' => $a, 'B' => $b] as $etiqueta => $lado) {
            $this->assertSame(
                1,
                DB::table('sso_tokens_consumidos')
                    ->where('jti', $jtiCompartido)
                    ->where('mandante_id', (int) $lado['mandante']->id)
                    ->count(),
                "El mandante {$etiqueta} debe llevar su propio registro del jti consumido: la clave anti-replay tiene que ser (mandante_id, jti)."
            );
        }
    }

    /**
     * Cerrar sesión en el wrapper de un mandante no puede tumbar la sesión que
     * el mismo correo tiene abierta en el wrapper del otro — ni dejar vivo el
     * token que se acaba de cerrar. Fija el comportamiento actual.
     */
    public function test_el_logout_del_mandante_a_mata_su_pat_y_respeta_el_del_mandante_b(): void
    {
        ['a' => $a, 'b' => $b] = $this->montarDosMandantes();

        $correoCompartido = 'logout.compartido@wrap.io';

        $patA = $this->emitirPat($a['mandante'], $a['proyecto'], $correoCompartido);
        $patB = $this->emitirPat($b['mandante'], $b['proyecto'], $correoCompartido);

        $this->withHeader('Authorization', "Bearer {$patA}")
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        $conPatA = $this->consultarPersona($patA, $a['proyecto'], $a['persona']);
        $this->assertNotSame(
            200,
            $conPatA->status(),
            'El PAT del mandante A siguió leyendo fichas después del logout.'
        );
        $this->assertStringNotContainsString(
            (string) $a['persona']->public_id,
            (string) $conPatA->getContent(),
            'El PAT revocado siguió devolviendo datos.'
        );

        $conPatB = $this->consultarPersona($patB, $b['proyecto'], $b['persona']);
        $this->assertSame(
            200,
            $conPatB->status(),
            'El logout del wrapper de A tumbó también la sesión del wrapper de B para el mismo correo.'
        );
    }

    // ------------------------------------------------------------- Utilería --

    /**
     * @return array<string, string>
     */
    private function firmarHmac(int $mandanteId, string $secret, string $body = ''): array
    {
        $timestamp = time();

        return [
            'X-Mandante-Id' => (string) $mandanteId,
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => hash_hmac('sha256', $body.$timestamp, $secret),
        ];
    }

    /**
     * JWT del wrapper tal y como lo espera AutenticadorPorJwt: `mandante_id`
     * obligatorio, TTL corto (PayloadJwt rechaza más de 60s) y jti único.
     *
     * @param  array<string, mixed>  $claims
     */
    private function jwtWrapper(stdClass $mandante, array $claims, ?string $secret = null): string
    {
        $payload = array_merge([
            'mandante_id' => (int) $mandante->id,
            'name' => 'Wrapper SSO',
            'jti' => (string) Str::uuid(),
            'iat' => time(),
            'exp' => time() + 55,
        ], $claims);

        return JWT::encode($payload, $secret ?? (string) $mandante->sso_secret, 'HS256');
    }

    /**
     * Handshake server-to-server completo: devuelve el PAT que el wrapper del
     * mandante recibe para llamar a las APIs en nombre del usuario JIT.
     */
    private function emitirPat(stdClass $mandante, stdClass $proyecto, string $email): string
    {
        $jwt = $this->jwtWrapper($mandante, [
            'sub' => $email,
            'wrapper_role' => 'agent',
            'proyecto_id' => (int) $proyecto->id,
        ]);

        $response = $this->postJson('/api/integracion/sanctum-token', ['token' => $jwt]);
        $response->assertStatus(201);

        return (string) $response->json('access_token');
    }

    private function urlPersona(stdClass $proyecto, stdClass $persona): string
    {
        return '/api/integracion/persona?'.http_build_query([
            'identificacion' => (string) $persona->identificacion,
            'tipo_identificacion_codigo' => 'CED',
            'proyecto_id' => (int) $proyecto->id,
        ]);
    }

    private function consultarPersona(string $pat, stdClass $proyecto, stdClass $persona): TestResponse
    {
        return $this->withHeader('Authorization', "Bearer {$pat}")
            ->getJson($this->urlPersona($proyecto, $persona));
    }

    private function ultimoPat(): stdClass
    {
        $pat = DB::table('personal_access_tokens')->orderByDesc('id')->first();

        $this->assertNotNull($pat, 'No se emitió ningún personal access token.');

        /** @var stdClass $pat */
        return $pat;
    }

    /**
     * @return list<string>
     */
    private function abilitiesDelUltimoPat(): array
    {
        $crudas = $this->ultimoPat()->abilities;

        if ($crudas === null || $crudas === '') {
            return [];
        }

        $decodificadas = json_decode((string) $crudas, true);

        if (! is_array($decodificadas)) {
            return [(string) $crudas];
        }

        return array_values(array_map(static fn ($a): string => (string) $a, $decodificadas));
    }
}
