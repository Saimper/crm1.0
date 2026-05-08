<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy\ConfiguradorPasos;

use App\Modules\Tenancy\Domain\ConfiguracionProyecto\CalculadorAvanceConfiguracion;
use App\Modules\Tenancy\Domain\ConfiguracionProyecto\EstadoConfiguracionProyecto;
use App\Modules\Tenancy\Infrastructure\Http\Livewire\ConfiguradorPasos\PasoCamposPersonalizados;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\ProyectoModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use stdClass;
use Tests\Support\EscenarioOperativo;
use Tests\TestCase;

final class PasoCamposPersonalizadosTest extends TestCase
{
    use EscenarioOperativo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_global_crea_campo_personalizado_de_tipo_caso(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(PasoCamposPersonalizados::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.ambito', 'caso')
            ->set('form.ambito_id', $cartera->id)
            ->set('form.codigo', 'dias_antiguedad')
            ->set('form.etiqueta', 'Días de antigüedad')
            ->set('form.tipo', 'numero_entero')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('campos_personalizados', [
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $cartera->id,
            'codigo' => 'dias_antiguedad',
            'etiqueta' => 'Días de antigüedad',
            'tipo' => 'numero_entero',
        ]);
    }

    public function test_admin_global_crea_campo_de_tipo_gestion_filtrado_por_proyecto(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $tipoGestionId = (int) DB::table('tipos_gestion')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'codigo' => 'TG',
            'nombre' => 'Tipo gestión',
            'orden' => 100,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        // Tipo de gestión perteneciente a OTRO proyecto: el form debe rechazarlo.
        $otroProyecto = $this->crearProyectoCx();
        $tipoOtro = (int) DB::table('tipos_gestion')->insertGetId([
            'proyecto_id' => $otroProyecto->id,
            'codigo' => 'TG_OTRO',
            'nombre' => 'Tipo otro',
            'orden' => 100,
            'activo' => true,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        // Aceptado: tipo del propio proyecto.
        Livewire::test(PasoCamposPersonalizados::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.ambito', 'gestion')
            ->set('form.ambito_id', $tipoGestionId)
            ->set('form.codigo', 'duracion_min')
            ->set('form.etiqueta', 'Duración')
            ->set('form.tipo', 'numero_entero')
            ->call('guardar')
            ->assertHasNoErrors();

        // Rechazado: tipo de otro proyecto.
        Livewire::test(PasoCamposPersonalizados::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.ambito', 'gestion')
            ->set('form.ambito_id', $tipoOtro)
            ->set('form.codigo', 'leak_test')
            ->set('form.etiqueta', 'Leak')
            ->set('form.tipo', 'texto_corto')
            ->call('guardar')
            ->assertHasErrors(['form.ambito_id']);
    }

    public function test_bloquea_eliminacion_si_hay_valores_asociados(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);

        $campoId = (int) DB::table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyecto->id,
            'ambito' => 'caso',
            'ambito_id' => $cartera->id,
            'tipo' => 'texto_corto',
            'codigo' => 'en_uso',
            'etiqueta' => 'En uso',
            'activo' => true,
            'orden' => 100,
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        DB::table('valores_campo_personalizado')->insert([
            'campo_personalizado_id' => $campoId,
            'entidad_id' => 999,
            'valor_texto_corto' => 'algo',
            'creada_en' => Carbon::now(),
            'actualizada_en' => Carbon::now(),
        ]);

        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(PasoCamposPersonalizados::class, ['proyecto' => $modelo])
            ->call('eliminar', $campoId);

        $this->assertDatabaseHas('campos_personalizados', ['id' => $campoId]);
    }

    public function test_emite_evento_paso_completado_al_crear_primer_campo(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $cartera = $this->crearCarteraEn($proyecto);
        $modelo = ProyectoModel::query()->findOrFail($proyecto->id);
        $this->actingAs($this->crearAdminGlobal());

        Livewire::test(PasoCamposPersonalizados::class, ['proyecto' => $modelo])
            ->call('abrirFormCrear')
            ->set('form.ambito', 'caso')
            ->set('form.ambito_id', $cartera->id)
            ->set('form.codigo', 'primero')
            ->set('form.etiqueta', 'Primero')
            ->set('form.tipo', 'texto_corto')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('configuracion-paso-completado');
    }

    public function test_paso_opcional_no_bloquea_avance(): void
    {
        $proyecto = $this->crearProyectoCobranza();
        $this->completarTodosLosObligatorios($proyecto);

        /** @var CalculadorAvanceConfiguracion $calculador */
        $calculador = app(CalculadorAvanceConfiguracion::class);
        $avance = $calculador->calcular((int) $proyecto->id);

        $this->assertTrue($avance->estaCompleto());
        $this->assertSame(EstadoConfiguracionProyecto::COMPLETADA, $avance->estado());
        // Sin campos personalizados creados.
        $this->assertSame(
            0,
            (int) DB::table('campos_personalizados')->where('proyecto_id', $proyecto->id)->count(),
        );
    }

    private function completarTodosLosObligatorios(stdClass $proyecto): void
    {
        $proyectoId = (int) $proyecto->id;
        $now = Carbon::now();

        $this->crearCarteraEn($proyecto);
        $this->crearEstadoCasoEn($proyecto);

        DB::table('tipos_gestion')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TG', 'nombre' => 'Tipo', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('resultados')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'R', 'nombre' => 'Resultado', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('motivos_no_contacto')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'M', 'nombre' => 'Motivo', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('tramos_mora')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TM', 'nombre' => 'Tramo', 'dias_desde' => 0,
            'orden' => 100, 'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
        DB::table('tipos_pago')->insert([
            'proyecto_id' => $proyectoId, 'codigo' => 'TP', 'nombre' => 'Tipo pago', 'orden' => 100,
            'activo' => true, 'creada_en' => $now, 'actualizada_en' => $now,
        ]);
    }
}
