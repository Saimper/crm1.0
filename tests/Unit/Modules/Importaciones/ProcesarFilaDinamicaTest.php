<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Cobranza\Application\DTOs\RegistrarCasoCobranzaOutput;
use App\Modules\Cobranza\Application\UseCases\RegistrarCasoCobranza;
use App\Modules\Cx\Application\DTOs\RegistrarCasoTicketCxOutput;
use App\Modules\Cx\Application\UseCases\RegistrarCasoTicketCx;
use App\Modules\Cx\Domain\Exceptions\CodigoTicketYaRegistrado;
use App\Modules\Importaciones\Application\Services\ResolverPersonaImportacion;
use App\Modules\Importaciones\Application\UseCases\ProcesarFilaDinamica;
use App\Modules\Importaciones\Application\UseCases\ProcesarFilaInput;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\EstadoFila;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use App\Modules\Personas\Application\UseCases\RegistrarPersona;
use App\Modules\Servicio\Application\UseCases\RegistrarCasoServicio;
use App\Modules\Venta\Application\UseCases\RegistrarCasoLeadVenta;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ProcesarFilaDinamicaTest extends TestCase
{
    private function crearColumna(
        string $nombre,
        TipoCampo $tipo = TipoCampo::TEXTO_CORTO,
        ?string $campoSistema = null,
        bool $esId = false,
        AccionColumna $accion = AccionColumna::CREAR_CP,
    ): ColumnaExcel {
        return new ColumnaExcel(
            nombreOriginal: $nombre,
            tipoInferido: $tipo,
            campoSistemaMapeado: $campoSistema,
            esIdentificadorPersona: $esId,
            accion: $accion,
        );
    }

    private function crearEsquema(ModoImportacion $modo, array $columnas = []): EsquemaImportacion
    {
        return new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 5,
            modo: $modo,
            columnas: $columnas,
        );
    }

    public function test_insert_persona_no_existe_retorna_duplicada(): void
    {
        $columnas = [
            $this->crearColumna('ced', campoSistema: 'identificacion', esId: true, accion: AccionColumna::MAPEAR_SISTEMA),
        ];

        $personaResolver = $this->createMock(ResolverPersonaImportacion::class);
        $personaResolver->method('lookup')->willReturn(42);

        $useCase = new ProcesarFilaDinamica(
            $personaResolver,
            $this->createMock(RegistrarCasoCobranza::class),
            $this->createMock(RegistrarCasoTicketCx::class),
            $this->createMock(RegistrarCasoLeadVenta::class),
            $this->createMock(RegistrarCasoServicio::class),
            $this->createMock(ConnectionInterface::class),
        );

        $resultado = $useCase->execute(new ProcesarFilaInput(
            fila: ['ced' => '12345'],
            esquema: $this->crearEsquema(ModoImportacion::INSERT, $columnas),
            importacionFilaId: 1,
            mapaCampos: [],
        ));

        self::assertSame(EstadoFila::DUPLICADA, $resultado->resultadoFila->estado);
        self::assertSame([], $resultado->valoresCp);
    }

    public function test_update_persona_no_existe_retorna_omitida(): void
    {
        $columnas = [
            $this->crearColumna('ced', campoSistema: 'identificacion', esId: true, accion: AccionColumna::MAPEAR_SISTEMA),
        ];

        $personaResolver = $this->createMock(ResolverPersonaImportacion::class);
        $personaResolver->method('lookup')->willReturn(null);

        $db = $this->createMock(ConnectionInterface::class);
        $db->method('table')->willReturn($this->createMock(Builder::class));

        $useCase = new ProcesarFilaDinamica(
            $personaResolver,
            $this->createMock(RegistrarCasoCobranza::class),
            $this->createMock(RegistrarCasoTicketCx::class),
            $this->createMock(RegistrarCasoLeadVenta::class),
            $this->createMock(RegistrarCasoServicio::class),
            $db,
        );

        $resultado = $useCase->execute(new ProcesarFilaInput(
            fila: ['ced' => '12345'],
            esquema: $this->crearEsquema(ModoImportacion::UPDATE, $columnas),
            importacionFilaId: 1,
            mapaCampos: [],
        ));

        self::assertSame(EstadoFila::OMITIDA, $resultado->resultadoFila->estado);
    }

    public function test_upsert_persona_no_existe_crea_caso(): void
    {
        $columnas = [
            $this->crearColumna('ced', campoSistema: 'identificacion', esId: true, accion: AccionColumna::MAPEAR_SISTEMA),
            $this->crearColumna('prestamo', campoSistema: 'numero_prestamo', accion: AccionColumna::MAPEAR_SISTEMA),
        ];

        $personaResolver = $this->createMock(ResolverPersonaImportacion::class);
        $personaResolver->method('lookup')->willReturn(null);
        $personaResolver->method('resolverOCrear')->willReturn(10);

        $registrarCobranza = $this->createMock(RegistrarCasoCobranza::class);
        $registrarCobranza->method('execute')->willReturn(new RegistrarCasoCobranzaOutput(casoId: 99, publicId: 'test'));

        $db = $this->createMock(ConnectionInterface::class);
        $db->method('table')->willReturn($this->createMock(Builder::class));

        $useCase = new ProcesarFilaDinamica(
            $personaResolver,
            $registrarCobranza,
            $this->createMock(RegistrarCasoTicketCx::class),
            $this->createMock(RegistrarCasoLeadVenta::class),
            $this->createMock(RegistrarCasoServicio::class),
            $db,
        );

        $resultado = $useCase->execute(new ProcesarFilaInput(
            fila: ['ced' => '12345', 'prestamo' => 'PR-001'],
            esquema: $this->crearEsquema(ModoImportacion::UPSERT, $columnas),
            importacionFilaId: 1,
            mapaCampos: ['saldo_deuda' => ['id' => 1, 'tipo' => 'numero_decimal']],
        ));

        self::assertSame(EstadoFila::PROCESADA, $resultado->resultadoFila->estado);
        self::assertSame(99, $resultado->resultadoFila->entidadId);
    }

    public function test_upsert_persona_existente_actualiza(): void
    {
        $columnas = [
            $this->crearColumna('ced', campoSistema: 'identificacion', esId: true, accion: AccionColumna::MAPEAR_SISTEMA),
            $this->crearColumna('prestamo', campoSistema: 'numero_prestamo', accion: AccionColumna::MAPEAR_SISTEMA),
        ];

        $personaResolver = $this->createMock(ResolverPersonaImportacion::class);
        $personaResolver->method('lookup')->willReturn(10);

        $builder = $this->createMock(Builder::class);
        $builder->method('where')->willReturnSelf();
        $builder->method('value')->willReturn(99);

        $db = $this->createMock(ConnectionInterface::class);
        $db->method('table')->willReturn($builder);

        $useCase = new ProcesarFilaDinamica(
            $personaResolver,
            $this->createMock(RegistrarCasoCobranza::class),
            $this->createMock(RegistrarCasoTicketCx::class),
            $this->createMock(RegistrarCasoLeadVenta::class),
            $this->createMock(RegistrarCasoServicio::class),
            $db,
        );

        $resultado = $useCase->execute(new ProcesarFilaInput(
            fila: ['ced' => '12345', 'prestamo' => 'PR-001'],
            esquema: $this->crearEsquema(ModoImportacion::UPSERT, $columnas),
            importacionFilaId: 1,
            mapaCampos: [],
        ));

        self::assertSame(EstadoFila::PROCESADA, $resultado->resultadoFila->estado);
    }

    public function test_merge_persona_existente_con_campo_null_lo_llena(): void
    {
        $columnas = [
            $this->crearColumna('ced', campoSistema: 'identificacion', esId: true, accion: AccionColumna::MAPEAR_SISTEMA),
            $this->crearColumna('prestamo', campoSistema: 'numero_prestamo', accion: AccionColumna::MAPEAR_SISTEMA),
        ];

        $personaResolver = $this->createMock(ResolverPersonaImportacion::class);
        $personaResolver->method('lookup')->willReturn(10);

        $builder = $this->createMock(Builder::class);
        $builder->method('where')->willReturnSelf();
        $builder->method('value')->willReturn(99);

        $db = $this->createMock(ConnectionInterface::class);
        $db->method('table')->willReturn($builder);

        $useCase = new ProcesarFilaDinamica(
            $personaResolver,
            $this->createMock(RegistrarCasoCobranza::class),
            $this->createMock(RegistrarCasoTicketCx::class),
            $this->createMock(RegistrarCasoLeadVenta::class),
            $this->createMock(RegistrarCasoServicio::class),
            $db,
        );

        $resultado = $useCase->execute(new ProcesarFilaInput(
            fila: ['ced' => '12345', 'prestamo' => 'PR-001'],
            esquema: $this->crearEsquema(ModoImportacion::MERGE, $columnas),
            importacionFilaId: 1,
            mapaCampos: [],
        ));

        self::assertSame(EstadoFila::PROCESADA, $resultado->resultadoFila->estado);
    }

    public function test_fila_sin_valor_en_columna_identificador_retorna_invalida(): void
    {
        $columnas = [
            $this->crearColumna('ced', campoSistema: 'identificacion', esId: true, accion: AccionColumna::MAPEAR_SISTEMA),
        ];

        $personaResolver = $this->createMock(ResolverPersonaImportacion::class);

        $db = $this->createMock(ConnectionInterface::class);

        $useCase = new ProcesarFilaDinamica(
            $personaResolver,
            $this->createMock(RegistrarCasoCobranza::class),
            $this->createMock(RegistrarCasoTicketCx::class),
            $this->createMock(RegistrarCasoLeadVenta::class),
            $this->createMock(RegistrarCasoServicio::class),
            $db,
        );

        $resultado = $useCase->execute(new ProcesarFilaInput(
            fila: ['ced' => ''],
            esquema: $this->crearEsquema(ModoImportacion::UPSERT, $columnas),
            importacionFilaId: 1,
            mapaCampos: [],
        ));

        self::assertSame(EstadoFila::INVALIDA, $resultado->resultadoFila->estado);
    }

    public function test_skip_duplicados_persona_existente_retorna_duplicada(): void
    {
        $columnas = [
            $this->crearColumna('ced', campoSistema: 'identificacion', esId: true, accion: AccionColumna::MAPEAR_SISTEMA),
        ];

        $personaResolver = $this->createMock(ResolverPersonaImportacion::class);
        $personaResolver->method('lookup')->willReturn(42);

        $db = $this->createMock(ConnectionInterface::class);

        $useCase = new ProcesarFilaDinamica(
            $personaResolver,
            $this->createMock(RegistrarCasoCobranza::class),
            $this->createMock(RegistrarCasoTicketCx::class),
            $this->createMock(RegistrarCasoLeadVenta::class),
            $this->createMock(RegistrarCasoServicio::class),
            $db,
        );

        $resultado = $useCase->execute(new ProcesarFilaInput(
            fila: ['ced' => '12345'],
            esquema: $this->crearEsquema(ModoImportacion::SKIP_DUPLICADOS, $columnas),
            importacionFilaId: 1,
            mapaCampos: [],
        ));

        self::assertSame(EstadoFila::DUPLICADA, $resultado->resultadoFila->estado);
    }

    public function test_skip_duplicados_persona_no_existe_retorna_invalida(): void
    {
        $columnas = [
            $this->crearColumna('ced', campoSistema: 'identificacion', esId: true, accion: AccionColumna::MAPEAR_SISTEMA),
        ];

        $personaResolver = $this->createMock(ResolverPersonaImportacion::class);
        $personaResolver->method('lookup')->willReturn(null);

        $db = $this->createMock(ConnectionInterface::class);

        $useCase = new ProcesarFilaDinamica(
            $personaResolver,
            $this->createMock(RegistrarCasoCobranza::class),
            $this->createMock(RegistrarCasoTicketCx::class),
            $this->createMock(RegistrarCasoLeadVenta::class),
            $this->createMock(RegistrarCasoServicio::class),
            $db,
        );

        $resultado = $useCase->execute(new ProcesarFilaInput(
            fila: ['ced' => '12345'],
            esquema: $this->crearEsquema(ModoImportacion::SKIP_DUPLICADOS, $columnas),
            importacionFilaId: 1,
            mapaCampos: [],
        ));

        self::assertSame(EstadoFila::INVALIDA, $resultado->resultadoFila->estado);
    }

    public function test_insert_con_valores_cp_acumula_valores(): void
    {
        $columnas = [
            $this->crearColumna('ced', campoSistema: 'identificacion', esId: true, accion: AccionColumna::MAPEAR_SISTEMA),
            $this->crearColumna('prestamo', campoSistema: 'numero_prestamo', accion: AccionColumna::MAPEAR_SISTEMA),
            $this->crearColumna('saldo', TipoCampo::NUMERO_DECIMAL, accion: AccionColumna::CREAR_CP),
        ];

        $personaResolver = $this->createMock(ResolverPersonaImportacion::class);
        $personaResolver->method('lookup')->willReturn(null);
        $personaResolver->method('resolverOCrear')->willReturn(10);

        $registrarCobranza = $this->createMock(RegistrarCasoCobranza::class);
        $registrarCobranza->method('execute')->willReturn(new RegistrarCasoCobranzaOutput(casoId: 99, publicId: 'test'));

        $db = $this->createMock(ConnectionInterface::class);
        $db->method('table')->willReturn($this->createMock(Builder::class));

        $useCase = new ProcesarFilaDinamica(
            $personaResolver,
            $registrarCobranza,
            $this->createMock(RegistrarCasoTicketCx::class),
            $this->createMock(RegistrarCasoLeadVenta::class),
            $this->createMock(RegistrarCasoServicio::class),
            $db,
        );

        $resultado = $useCase->execute(new ProcesarFilaInput(
            fila: ['ced' => '12345', 'prestamo' => 'PR-001', 'saldo' => '1500.50'],
            esquema: $this->crearEsquema(ModoImportacion::UPSERT, $columnas),
            importacionFilaId: 1,
            mapaCampos: ['saldo' => ['id' => 1, 'tipo' => 'numero_decimal']],
        ));

        self::assertSame(EstadoFila::PROCESADA, $resultado->resultadoFila->estado);
        self::assertNotEmpty($resultado->valoresCp);
        self::assertSame(1, $resultado->valoresCp[0]['campo_id']);
        self::assertSame(99, $resultado->valoresCp[0]['entidad_id']);
        self::assertSame(1500.50, $resultado->valoresCp[0]['valor']);
        self::assertSame('numero_decimal', $resultado->valoresCp[0]['tipo']);
    }

    public function test_update_con_caso_existente_actualiza_y_acumula_cp(): void
    {
        $columnas = [
            $this->crearColumna('ced', campoSistema: 'identificacion', esId: true, accion: AccionColumna::MAPEAR_SISTEMA),
            $this->crearColumna('prestamo', campoSistema: 'numero_prestamo', accion: AccionColumna::MAPEAR_SISTEMA),
            $this->crearColumna('saldo', TipoCampo::NUMERO_DECIMAL, accion: AccionColumna::CREAR_CP),
        ];

        $personaResolver = $this->createMock(ResolverPersonaImportacion::class);
        $personaResolver->method('lookup')->willReturn(10);

        $builder = $this->createMock(Builder::class);
        $builder->method('where')->willReturnSelf();
        $builder->method('value')->willReturn(99);

        $db = $this->createMock(ConnectionInterface::class);
        $db->method('table')->willReturn($builder);

        $useCase = new ProcesarFilaDinamica(
            $personaResolver,
            $this->createMock(RegistrarCasoCobranza::class),
            $this->createMock(RegistrarCasoTicketCx::class),
            $this->createMock(RegistrarCasoLeadVenta::class),
            $this->createMock(RegistrarCasoServicio::class),
            $db,
        );

        $resultado = $useCase->execute(new ProcesarFilaInput(
            fila: ['ced' => '12345', 'prestamo' => 'PR-001', 'saldo' => '2000.00'],
            esquema: $this->crearEsquema(ModoImportacion::UPDATE, $columnas),
            importacionFilaId: 1,
            mapaCampos: ['saldo' => ['id' => 1, 'tipo' => 'numero_decimal']],
        ));

        self::assertSame(EstadoFila::PROCESADA, $resultado->resultadoFila->estado);
        self::assertNotEmpty($resultado->valoresCp);
    }
}
