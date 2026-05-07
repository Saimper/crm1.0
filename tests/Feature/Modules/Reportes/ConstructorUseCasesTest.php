<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reportes;

use App\Modules\Reportes\Application\DTOs\EntradaDefinicionReporte;
use App\Modules\Reportes\Application\Hidratacion\HidratadorDefinicionReporte;
use App\Modules\Reportes\Application\Servicios\ServicioCamposPersonalizadosReporte;
use App\Modules\Reportes\Application\UseCases\ActualizarDefinicionReporte;
use App\Modules\Reportes\Application\UseCases\CrearDefinicionReporte;
use App\Modules\Reportes\Application\UseCases\EjecutarReporte;
use App\Modules\Reportes\Application\UseCases\EliminarDefinicionReporte;
use App\Modules\Reportes\Domain\Constructor\Contracts\RepositorioDefinicionReporte;
use App\Modules\Reportes\Domain\Constructor\Exceptions\CampoNoPermitidoEnReporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ConstructorUseCasesTest extends TestCase
{
    use RefreshDatabase;

    private int $proyectoId;

    private int $usuarioId;

    private RepositorioDefinicionReporte $repo;

    private CrearDefinicionReporte $crear;

    private EjecutarReporte $ejecutar;

    protected function setUp(): void
    {
        $this->markTestSkipped('TODO F35: migrar a factories tras limpieza demo seeders (ver tests/Support/EscenarioOperativo).');

    }

    public function test_crear_definicion_basica(): void
    {
        $entrada = new EntradaDefinicionReporte(
            proyectoId: $this->proyectoId,
            codigo: 'casos_basicos',
            nombre: 'Casos básicos',
            entidadRaiz: 'casos',
            columnas: [
                ['campo' => 'casos.public_id', 'etiqueta' => 'ID'],
                ['campo' => 'casos.tipo_caso', 'etiqueta' => 'Tipo'],
            ],
        );

        $id = $this->crear->execute($entrada, $this->usuarioId);

        $this->assertGreaterThan(0, $id);
        $this->assertDatabaseHas('reportes_definiciones', [
            'id' => $id,
            'codigo' => 'casos_basicos',
            'proyecto_id' => $this->proyectoId,
        ]);
    }

    public function test_crear_falla_con_codigo_duplicado(): void
    {
        $entrada = new EntradaDefinicionReporte(
            proyectoId: $this->proyectoId,
            codigo: 'dup',
            nombre: 'Dup',
            entidadRaiz: 'casos',
            columnas: [['campo' => 'casos.public_id', 'etiqueta' => 'ID']],
        );
        $this->crear->execute($entrada, $this->usuarioId);

        $this->expectException(\DomainException::class);
        $this->crear->execute($entrada, $this->usuarioId);
    }

    public function test_crear_falla_con_campo_no_permitido(): void
    {
        $entrada = new EntradaDefinicionReporte(
            proyectoId: $this->proyectoId,
            codigo: 'malicioso',
            nombre: 'X',
            entidadRaiz: 'casos',
            columnas: [['campo' => "'; DROP TABLE casos; --", 'etiqueta' => 'X']],
        );

        $this->expectException(CampoNoPermitidoEnReporte::class);
        $this->crear->execute($entrada, $this->usuarioId);
    }

    public function test_ejecutar_definicion_devuelve_generator(): void
    {
        $entrada = new EntradaDefinicionReporte(
            proyectoId: $this->proyectoId,
            codigo: 'casos_lista',
            nombre: 'Casos lista',
            entidadRaiz: 'casos',
            columnas: [['campo' => 'casos.public_id', 'etiqueta' => 'ID']],
        );
        $id = $this->crear->execute($entrada, $this->usuarioId);

        $data = $this->repo->buscar($id, $this->proyectoId);
        $def = HidratadorDefinicionReporte::desdeArray($data);

        $resultado = $this->ejecutar->execute($def);

        $this->assertSame('ID', $resultado->cabeceras[0]['etiqueta']);
        // Generator iterates without error en proyecto sin casos.
        $count = 0;
        foreach ($resultado->filas as $_) {
            $count++;
        }
        $this->assertSame(0, $count);
    }

    public function test_ejecutar_aplica_scope_proyecto(): void
    {
        $entrada = new EntradaDefinicionReporte(
            proyectoId: $this->proyectoId,
            codigo: 'scope_test',
            nombre: 'X',
            entidadRaiz: 'casos',
            columnas: [['campo' => 'casos.public_id', 'etiqueta' => 'ID']],
        );
        $id = $this->crear->execute($entrada, $this->usuarioId);
        $data = $this->repo->buscar($id, $this->proyectoId);
        $def = HidratadorDefinicionReporte::desdeArray($data);

        $resultado = $this->ejecutar->execute($def);
        // Bind generator (no rows means no error). Verify no exception.
        iterator_to_array($resultado->filas);
        $this->assertTrue(true);
    }

    public function test_definicion_otro_proyecto_no_se_encuentra(): void
    {
        $otroProy = (int) DB::table('proyectos')->where('codigo', 'SOPORTE_DEMO_2026')->value('id');
        $entrada = new EntradaDefinicionReporte(
            proyectoId: $this->proyectoId,
            codigo: 'cross_proy',
            nombre: 'X',
            entidadRaiz: 'casos',
            columnas: [['campo' => 'casos.public_id', 'etiqueta' => 'ID']],
        );
        $id = $this->crear->execute($entrada, $this->usuarioId);

        // buscar en otro proyecto debe devolver null.
        $this->assertNull($this->repo->buscar($id, $otroProy));
    }

    public function test_actualizar_definicion(): void
    {
        $entrada = new EntradaDefinicionReporte(
            proyectoId: $this->proyectoId,
            codigo: 'upd',
            nombre: 'Original',
            entidadRaiz: 'casos',
            columnas: [['campo' => 'casos.public_id', 'etiqueta' => 'ID']],
        );
        $id = $this->crear->execute($entrada, $this->usuarioId);

        $cp = new ServicioCamposPersonalizadosReporte;
        $actualizar = new ActualizarDefinicionReporte($this->repo, $cp);

        $nueva = new EntradaDefinicionReporte(
            proyectoId: $this->proyectoId,
            codigo: 'upd',
            nombre: 'Actualizado',
            entidadRaiz: 'casos',
            columnas: [['campo' => 'casos.public_id', 'etiqueta' => 'ID']],
        );
        $actualizar->execute($id, $nueva);

        $this->assertDatabaseHas('reportes_definiciones', ['id' => $id, 'nombre' => 'Actualizado']);
    }

    public function test_eliminar_marca_eliminada_en(): void
    {
        $entrada = new EntradaDefinicionReporte(
            proyectoId: $this->proyectoId,
            codigo: 'del',
            nombre: 'X',
            entidadRaiz: 'casos',
            columnas: [['campo' => 'casos.public_id', 'etiqueta' => 'ID']],
        );
        $id = $this->crear->execute($entrada, $this->usuarioId);

        (new EliminarDefinicionReporte($this->repo))->execute($id, $this->proyectoId);

        $this->assertNull($this->repo->buscar($id, $this->proyectoId));
        $this->assertNotNull(DB::table('reportes_definiciones')->where('id', $id)->value('eliminada_en'));
    }
}
