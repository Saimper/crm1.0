<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\RolContacto;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\Exceptions\ColisionCodigosCampoException;
use App\Modules\Importaciones\Domain\Exceptions\ColumnaIdentificadorAmbiguaException;
use App\Modules\Importaciones\Domain\Exceptions\EsquemaInvalidoException;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use PHPUnit\Framework\TestCase;

final class EsquemaImportacionTest extends TestCase
{
    public function test_two_headers_cannot_overwrite_the_same_native_destination(): void
    {
        $schema = new EsquemaImportacion(TargetImportacion::PERSONA, 1, null, ModoImportacion::UPSERT, [
            $this->crearColumna('Nombre A', campoSistema: 'nombres', accion: AccionColumna::MAPEAR_SISTEMA),
            $this->crearColumna('Nombre B', campoSistema: 'nombres', accion: AccionColumna::MAPEAR_SISTEMA),
        ]);
        $this->expectException(ColisionCodigosCampoException::class);
        $schema->validar();
    }

    public function test_native_destination_must_belong_to_the_import_target(): void
    {
        $schema = new EsquemaImportacion(TargetImportacion::PERSONA, 1, null, ModoImportacion::UPSERT, [
            $this->crearColumna('Saldo', campoSistema: 'saldo_total', accion: AccionColumna::MAPEAR_SISTEMA),
        ]);
        $this->expectException(EsquemaInvalidoException::class);
        $schema->validar();
    }

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

    public function test_validar_lanza_excepcion_si_target_caso_sin_cartera(): void
    {
        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: null,
            modo: ModoImportacion::UPSERT,
            columnas: [],
        );

        $this->expectException(EsquemaInvalidoException::class);

        $esquema->validar();
    }

    public function test_validar_lanza_excepcion_si_target_ticket_cx_sin_cartera(): void
    {
        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_TICKET_CX,
            proyectoId: 1,
            carteraId: null,
            modo: ModoImportacion::UPSERT,
            columnas: [],
        );

        $this->expectException(EsquemaInvalidoException::class);

        $esquema->validar();
    }

    public function test_validar_no_lanza_excepcion_si_target_persona_sin_cartera(): void
    {
        $esquema = new EsquemaImportacion(
            target: TargetImportacion::PERSONA,
            proyectoId: 1,
            carteraId: null,
            modo: ModoImportacion::UPSERT,
            columnas: [],
        );

        $esquema->validar();

        self::assertTrue(true);
    }

    public function test_validar_no_lanza_excepcion_con_esquema_valido(): void
    {
        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 5,
            modo: ModoImportacion::UPSERT,
            columnas: [
                $this->crearColumna('identificacion', campoSistema: 'identificacion', accion: AccionColumna::MAPEAR_SISTEMA),
                $this->crearColumna('saldo_deuda', TipoCampo::NUMERO_DECIMAL),
            ],
        );

        $esquema->validar();

        self::assertTrue(true);
    }

    public function test_validar_lanza_excepcion_con_dos_identificadores(): void
    {
        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 5,
            modo: ModoImportacion::UPSERT,
            columnas: [
                $this->crearColumna('cedula', esId: true),
                $this->crearColumna('ruc', esId: true),
            ],
        );

        $this->expectException(ColumnaIdentificadorAmbiguaException::class);

        $esquema->validar();
    }

    public function test_validar_lanza_excepcion_con_colision_de_codigos(): void
    {
        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 5,
            modo: ModoImportacion::UPSERT,
            columnas: [
                $this->crearColumna('Saldo Actual'),
                $this->crearColumna('saldo_actual'),
            ],
        );

        $this->expectException(ColisionCodigosCampoException::class);

        $esquema->validar();
    }

    public function test_validar_ignora_columnas_ignoradas_en_colision(): void
    {
        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 5,
            modo: ModoImportacion::UPSERT,
            columnas: [
                $this->crearColumna('Saldo Actual', accion: AccionColumna::IGNORAR),
                $this->crearColumna('saldo_actual', accion: AccionColumna::IGNORAR),
            ],
        );

        $esquema->validar();

        self::assertTrue(true);
    }

    public function test_columna_identificador_retorna_correcta(): void
    {
        $colId = $this->crearColumna('cedula', esId: true);
        $colNormal = $this->crearColumna('nombre');

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 5,
            modo: ModoImportacion::UPSERT,
            columnas: [$colNormal, $colId],
        );

        $resultado = $esquema->columnaIdentificador();

        self::assertNotNull($resultado);
        self::assertSame('cedula', $resultado->nombreOriginal);
    }

    public function test_columna_identificador_retorna_null_sin_identificador(): void
    {
        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 5,
            modo: ModoImportacion::UPSERT,
            columnas: [
                $this->crearColumna('cedula'),
                $this->crearColumna('nombre'),
            ],
        );

        self::assertNull($esquema->columnaIdentificador());
    }

    public function test_columnas_para_sistema_filtra_por_accion_mapear(): void
    {
        $colSistema = $this->crearColumna('identificacion', campoSistema: 'identificacion', accion: AccionColumna::MAPEAR_SISTEMA);
        $colCP = $this->crearColumna('saldo');

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 5,
            modo: ModoImportacion::UPSERT,
            columnas: [$colSistema, $colCP],
        );

        $resultado = $esquema->columnasParaSistema();

        self::assertCount(1, $resultado);
        self::assertArrayHasKey('identificacion', $resultado);
        self::assertSame($colSistema, $resultado['identificacion']);
    }

    public function test_columnas_para_sistema_keyed_por_codigo(): void
    {
        $colIdent = $this->crearColumna('ced', campoSistema: 'identificacion', accion: AccionColumna::MAPEAR_SISTEMA);
        $colNombres = $this->crearColumna('nom', campoSistema: 'nombres', accion: AccionColumna::MAPEAR_SISTEMA);

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 5,
            modo: ModoImportacion::UPSERT,
            columnas: [$colIdent, $colNombres],
        );

        $resultado = $esquema->columnasParaSistema();

        self::assertCount(2, $resultado);
        self::assertArrayHasKey('identificacion', $resultado);
        self::assertArrayHasKey('nombres', $resultado);
    }

    public function test_columnas_para_campos_personalizados_filtra_por_crear_cp(): void
    {
        $colSistema = $this->crearColumna('identificacion', campoSistema: 'identificacion', accion: AccionColumna::MAPEAR_SISTEMA);
        $colCP1 = $this->crearColumna('saldo_deuda');
        $colIgnorada = $this->crearColumna('basura', accion: AccionColumna::IGNORAR);

        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 5,
            modo: ModoImportacion::UPSERT,
            columnas: [$colSistema, $colCP1, $colIgnorada],
        );

        $resultado = $esquema->columnasParaCamposPersonalizados();

        self::assertCount(1, $resultado);
        self::assertSame('saldo_deuda', $resultado[0]->nombreOriginal);
    }

    public function test_tiene_identificador_retorna_true_cuando_hay(): void
    {
        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 5,
            modo: ModoImportacion::UPSERT,
            columnas: [$this->crearColumna('cedula', esId: true)],
        );

        self::assertTrue($esquema->tieneIdentificador());
    }

    public function test_tiene_identificador_retorna_false_cuando_no_hay(): void
    {
        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 5,
            modo: ModoImportacion::UPSERT,
            columnas: [$this->crearColumna('cedula')],
        );

        self::assertFalse($esquema->tieneIdentificador());
    }

    public function test_serializar_y_deserializar_es_idempotente(): void
    {
        $columnas = [
            $this->crearColumna('identificacion', TipoCampo::TEXTO_CORTO, 'identificacion', false, AccionColumna::MAPEAR_SISTEMA),
            $this->crearColumna('saldo_deuda', TipoCampo::NUMERO_DECIMAL, null, true, AccionColumna::CREAR_CP),
            $this->crearColumna('basura', TipoCampo::TEXTO_LARGO, null, false, AccionColumna::IGNORAR),
            // El rol se perdía al persistir: el camino asíncrono nunca generó contactos.
            new ColumnaExcel(
                nombreOriginal: 'Telefonos',
                tipoInferido: TipoCampo::TEXTO_CORTO,
                accion: AccionColumna::CREAR_CP,
                rolContacto: RolContacto::TELEFONO,
            ),
        ];

        $original = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 42,
            carteraId: 7,
            modo: ModoImportacion::UPSERT,
            columnas: $columnas,
        );

        $json = $original->serializar();
        $reconstruido = EsquemaImportacion::deserializar($json);

        self::assertSame($original->target, $reconstruido->target);
        self::assertSame($original->proyectoId, $reconstruido->proyectoId);
        self::assertSame($original->carteraId, $reconstruido->carteraId);
        self::assertSame($original->modo, $reconstruido->modo);
        self::assertCount(count($original->columnas), $reconstruido->columnas);

        foreach ($original->columnas as $i => $colOriginal) {
            $colReconstruido = $reconstruido->columnas[$i];
            self::assertSame($colOriginal->nombreOriginal, $colReconstruido->nombreOriginal);
            self::assertSame($colOriginal->tipoInferido, $colReconstruido->tipoInferido);
            self::assertSame($colOriginal->campoSistemaMapeado, $colReconstruido->campoSistemaMapeado);
            self::assertSame($colOriginal->esIdentificadorPersona, $colReconstruido->esIdentificadorPersona);
            self::assertSame($colOriginal->accion, $colReconstruido->accion);
            self::assertSame($colOriginal->rolContacto, $colReconstruido->rolContacto);
        }

        self::assertSame(RolContacto::TELEFONO, $reconstruido->columnas[3]->rolContacto);
    }

    public function test_con_modo_devuelve_otra_instancia_con_el_mismo_esquema_y_el_modo_nuevo(): void
    {
        $original = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 42,
            carteraId: 7,
            modo: ModoImportacion::UPSERT,
            columnas: [$this->crearColumna('cedula', esId: true)],
        );

        $conMerge = $original->conModo(ModoImportacion::MERGE);

        self::assertNotSame($original, $conMerge);
        self::assertSame(ModoImportacion::UPSERT, $original->modo, 'El VO es inmutable: el original no cambia.');
        self::assertSame(ModoImportacion::MERGE, $conMerge->modo);
        self::assertSame($original->target, $conMerge->target);
        self::assertSame($original->proyectoId, $conMerge->proyectoId);
        self::assertSame($original->carteraId, $conMerge->carteraId);
        self::assertSame($original->columnas, $conMerge->columnas);
        self::assertSame('merge', json_decode($conMerge->serializar(), true)['modo']);
    }

    public function test_la_clave_de_payload_es_el_campo_del_sistema_o_el_codigo_sugerido(): void
    {
        $sistema = $this->crearColumna('CEDULA', campoSistema: 'identificacion', accion: AccionColumna::MAPEAR_SISTEMA);
        $cp = $this->crearColumna('Saldo Actual', accion: AccionColumna::CREAR_CP);

        self::assertSame('identificacion', $sistema->clavePayload());
        self::assertSame('saldo_actual', $cp->clavePayload());
    }

    public function test_columnas_del_archivo_excluye_las_ignoradas_y_conserva_el_orden(): void
    {
        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 5,
            modo: ModoImportacion::UPSERT,
            columnas: [
                $this->crearColumna('CEDULA', campoSistema: 'identificacion', accion: AccionColumna::MAPEAR_SISTEMA),
                $this->crearColumna('BASURA', accion: AccionColumna::IGNORAR),
                $this->crearColumna('CUENTA', accion: AccionColumna::CREAR_CP),
            ],
        );

        self::assertSame(
            ['CEDULA', 'CUENTA'],
            array_map(static fn (ColumnaExcel $c): string => $c->nombreOriginal, $esquema->columnasDelArchivo()),
        );
    }

    public function test_deserializar_reconstruye_enums_correctamente(): void
    {
        $columnas = [
            $this->crearColumna('fecha_pago', TipoCampo::FECHA, null, false, AccionColumna::CREAR_CP),
        ];

        $original = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 5,
            modo: ModoImportacion::INSERT,
            columnas: $columnas,
        );

        $reconstruido = EsquemaImportacion::deserializar($original->serializar());

        self::assertSame(TipoCampo::FECHA, $reconstruido->columnas[0]->tipoInferido);
        self::assertSame(AccionColumna::CREAR_CP, $reconstruido->columnas[0]->accion);
        self::assertSame(ModoImportacion::INSERT, $reconstruido->modo);
        self::assertSame(TargetImportacion::CASO_COBRANZA, $reconstruido->target);
    }

    public function test_deserializar_lanza_exception_con_json_malformado(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EsquemaImportacion::deserializar('json_invalido{');
    }

    public function test_deserializar_lanza_exception_con_clave_faltante(): void
    {
        $json = json_encode(['target' => 'caso_cobranza']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Falta la clave requerida');

        EsquemaImportacion::deserializar($json);
    }

    public function test_deserializar_reconstruye_cartera_null(): void
    {
        $original = new EsquemaImportacion(
            target: TargetImportacion::PERSONA,
            proyectoId: 1,
            carteraId: null,
            modo: ModoImportacion::MERGE,
            columnas: [],
        );

        $reconstruido = EsquemaImportacion::deserializar($original->serializar());

        self::assertNull($reconstruido->carteraId);
    }

    public function test_serializar_produce_json_valido(): void
    {
        $esquema = new EsquemaImportacion(
            target: TargetImportacion::CASO_COBRANZA,
            proyectoId: 1,
            carteraId: 5,
            modo: ModoImportacion::UPSERT,
            columnas: [$this->crearColumna('test')],
        );

        $json = $esquema->serializar();

        $decoded = json_decode($json, true);

        self::assertIsArray($decoded);
        self::assertArrayHasKey('target', $decoded);
        self::assertArrayHasKey('proyecto_id', $decoded);
        self::assertArrayHasKey('cartera_id', $decoded);
        self::assertArrayHasKey('modo', $decoded);
        self::assertArrayHasKey('columnas', $decoded);
    }
}
