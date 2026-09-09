<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Integracion;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

/**
 * El barrido de identificadores de token ya consumidos.
 *
 * Es el único mecanismo que impide que `sso_tokens_consumidos` crezca sin fin,
 * y no tenía ni un test. Lo gana ahora porque la clave primaria de esa tabla
 * acaba de pasar de `jti` a `(mandante_id, jti)`: si el barrido dejara de
 * funcionar por ese cambio, nadie se enteraría hasta que la tabla molestara.
 */
final class PurgarSsoTokensConsumidosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_borra_los_vencidos_y_respeta_los_que_siguen_vivos(): void
    {
        $mandante = $this->crearMandante();

        $this->consumido($mandante->id, 'jti-vencido', Carbon::now()->subHour());
        $this->consumido($mandante->id, 'jti-vivo', Carbon::now()->addHour());

        $this->artisan('integracion:purgar-sso-consumidos')->assertSuccessful();

        $quedan = DB::table('sso_tokens_consumidos')->pluck('jti')->all();

        $this->assertSame(['jti-vivo'], $quedan);
    }

    /**
     * Dos clientes pueden usar el mismo identificador sin pisarse: el espacio
     * es de cada uno desde que la clave lleva el mandante delante.
     */
    public function test_dos_mandantes_pueden_consumir_el_mismo_jti(): void
    {
        $unoId = (int) $this->crearMandante()->id;
        $otroId = (int) $this->crearMandante()->id;

        $this->consumido($unoId, 'el-mismo-jti', Carbon::now()->addHour());
        $this->consumido($otroId, 'el-mismo-jti', Carbon::now()->addHour());

        $this->assertSame(2, DB::table('sso_tokens_consumidos')->where('jti', 'el-mismo-jti')->count());
    }

    private function consumido(int $mandanteId, string $jti, Carbon $expiraEn): void
    {
        DB::table('sso_tokens_consumidos')->insert([
            'jti' => $jti,
            'mandante_id' => $mandanteId,
            'proyecto_id' => null,
            'consumido_en' => Carbon::now(),
            'expira_en' => $expiraEn,
        ]);
    }
}
