<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Importaciones;

use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\Exceptions\EsquemaInvalidoException;
use App\Modules\Importaciones\Domain\ValueObjects\EsquemaImportacion;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ImportSchemaContextTest extends TestCase
{
    public function test_schema_is_bound_to_import_owner_project(): void
    {
        $schema = new EsquemaImportacion(TargetImportacion::CASO_COBRANZA, 8, 1, ModoImportacion::UPSERT, []);
        $this->expectException(EsquemaInvalidoException::class);
        $schema->validarContexto(9, 'caso_cobranza', 'cobranza');
    }

    public function test_schema_cannot_insert_another_operation_type_in_the_project(): void
    {
        $schema = new EsquemaImportacion(TargetImportacion::CASO_COBRANZA, 8, 1, ModoImportacion::UPSERT, []);
        $this->expectException(EsquemaInvalidoException::class);
        $schema->validarContexto(8, 'caso_cobranza', 'cx');
    }

    public function test_automatic_return_requires_an_authorized_actor(): void
    {
        $schema = new EsquemaImportacion(TargetImportacion::CASO_COBRANZA, 8, 1, ModoImportacion::INSERT, [], true);
        $this->expectException(EsquemaInvalidoException::class);
        $schema->validar();
    }

    public function test_returning_an_archived_debt_is_supported_in_every_import_mode(): void
    {
        foreach (ModoImportacion::cases() as $mode) {
            $schema = new EsquemaImportacion(TargetImportacion::CASO_COBRANZA, 8, 1, $mode, [], true, 20);
            $schema->validarContexto(8, 'caso_cobranza', 'cobranza');
            $restored = EsquemaImportacion::deserializar($schema->serializar());
            self::assertTrue($restored->reincorporarArchivadas);
            self::assertSame(20, $restored->autorizadoPorId);
            self::assertSame($mode, $restored->modo);
        }
    }

    public function test_string_false_cannot_enable_transfer(): void
    {
        $schema = new EsquemaImportacion(TargetImportacion::CASO_COBRANZA, 8, 1, ModoImportacion::UPSERT, []);
        $json = json_decode($schema->serializar(), true);
        $json['reincorporar_archivadas'] = 'false';
        $this->expectException(InvalidArgumentException::class);
        EsquemaImportacion::deserializar(json_encode($json, JSON_THROW_ON_ERROR));
    }
}
