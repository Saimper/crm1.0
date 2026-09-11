<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Domain\ValueObjects;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Domain\Catalogo\CatalogoCamposSistema;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\Enums\ModoImportacion;
use App\Modules\Importaciones\Domain\Enums\RolContacto;
use App\Modules\Importaciones\Domain\Enums\TargetImportacion;
use App\Modules\Importaciones\Domain\Exceptions\ColisionCodigosCampoException;
use App\Modules\Importaciones\Domain\Exceptions\ColumnaIdentificadorAmbiguaException;
use App\Modules\Importaciones\Domain\Exceptions\EsquemaInvalidoException;

/**
 * Esquema completo de una importación dinámica: target, modo, cartera
 * y la decisión de procesamiento para cada columna del archivo.
 */
final readonly class EsquemaImportacion
{
    /**
     * @param  list<ColumnaExcel>  $columnas
     */
    public function __construct(
        public TargetImportacion $target,
        public int $proyectoId,
        public ?int $carteraId,
        public ModoImportacion $modo,
        public array $columnas,
        public bool $reincorporarArchivadas = false,
        public ?int $autorizadoPorId = null,
        public string $formatoEntrada = 'proyecto',
    ) {}

    /**
     * Retorna la columna marcada como identificador de persona, o null si no hay.
     */
    public function columnaIdentificador(): ?ColumnaExcel
    {
        $candidatas = array_values(array_filter(
            $this->columnas,
            static fn (ColumnaExcel $c): bool => $c->esIdentificadorPersona,
        ));

        return $candidatas[0] ?? null;
    }

    /**
     * Retorna la columna marcada como identificador único del caso, o null si no hay.
     */
    public function columnaIdentificadorCaso(): ?ColumnaExcel
    {
        $candidatas = array_values(array_filter(
            $this->columnas,
            static fn (ColumnaExcel $c): bool => $c->esIdentificadorCaso,
        ));

        return $candidatas[0] ?? null;
    }

    /**
     * Columnas mapeadas a campos del sistema, keyadas por código del campo.
     *
     * @return array<string, ColumnaExcel>
     */
    public function columnasParaSistema(): array
    {
        $resultado = [];

        foreach ($this->columnas as $columna) {
            if ($columna->accion === AccionColumna::MAPEAR_SISTEMA && $columna->campoSistemaMapeado !== null) {
                $resultado[$columna->campoSistemaMapeado] = $columna;
            }
        }

        return $resultado;
    }

    /**
     * Columnas que se crearán como campos personalizados.
     *
     * @return list<ColumnaExcel>
     */
    public function columnasParaCamposPersonalizados(): array
    {
        return array_values(array_filter(
            $this->columnas,
            static fn (ColumnaExcel $c): bool => $c->accion === AccionColumna::CREAR_CP,
        ));
    }

    public function tieneIdentificador(): bool
    {
        return $this->columnaIdentificador() !== null;
    }

    /**
     * Las columnas que llegan al `payload`, en el orden del archivo. Es lo que
     * se devuelve como cabecera al descargar las filas rechazadas.
     *
     * @return list<ColumnaExcel>
     */
    public function columnasDelArchivo(): array
    {
        return array_values(array_filter(
            $this->columnas,
            static fn (ColumnaExcel $c): bool => $c->debePersistirse(),
        ));
    }

    /**
     * El mismo esquema con otro modo.
     *
     * El wizard persiste el esquema al terminar el paso 2 con el modo que había
     * entonces —`upsert` por defecto—, y el selector de modo está en el paso 3.
     * El modo que el supervisor elige de verdad se escribe en la columna
     * `importaciones.modo`, y el motor lo lee del JSON: así es como un «completar
     * vacíos» corría siempre como «insertar y actualizar». Esta operación es lo
     * que permite que columna y JSON digan lo mismo antes de ejecutar.
     */
    public function conModo(ModoImportacion $modo): self
    {
        return new self(
            target: $this->target,
            proyectoId: $this->proyectoId,
            carteraId: $this->carteraId,
            modo: $modo,
            columnas: $this->columnas,
            reincorporarArchivadas: $this->reincorporarArchivadas,
            autorizadoPorId: $this->autorizadoPorId,
            formatoEntrada: $this->formatoEntrada,
        );
    }

    /**
     * Valida la integridad del esquema. Lanza excepciones si hay inconsistencias.
     *
     * @throws EsquemaInvalidoException
     * @throws ColumnaIdentificadorAmbiguaException
     * @throws ColisionCodigosCampoException
     */
    public function validar(): void
    {
        if ($this->proyectoId <= 0 || ($this->carteraId !== null && $this->carteraId <= 0)) {
            throw new EsquemaInvalidoException('El proyecto y la cartera deben ser válidos.');
        }
        if (! in_array($this->formatoEntrada, ['proyecto', 'estandar'], true)) {
            throw new EsquemaInvalidoException('El formato de entrada no es válido.');
        }
        if ($this->reincorporarArchivadas && ($this->target === TargetImportacion::PERSONA
            || $this->carteraId === null || $this->autorizadoPorId === null || $this->autorizadoPorId <= 0)) {
            throw new EsquemaInvalidoException('Reincorporar cuentas requiere un actor autorizado y una cartera de destino.');
        }
        if ($this->target !== TargetImportacion::PERSONA && $this->carteraId === null) {
            throw new EsquemaInvalidoException(
                sprintf('El target "%s" requiere una cartera asignada.', $this->target->value),
            );
        }

        $identificadores = array_filter(
            $this->columnas,
            static fn (ColumnaExcel $c): bool => $c->esIdentificadorPersona,
        );

        if (count($identificadores) > 1) {
            throw new ColumnaIdentificadorAmbiguaException;
        }
        if (count(array_filter($this->columnas, static fn (ColumnaExcel $c): bool => $c->esIdentificadorCaso)) > 1) {
            throw new EsquemaInvalidoException('Solo una columna puede identificar la cuenta.');
        }

        $codigos = [];

        foreach ($this->columnas as $columna) {
            if (! $columna->debePersistirse()) {
                continue;
            }

            if ($columna->accion === AccionColumna::MAPEAR_SISTEMA
                && ! in_array($columna->campoSistemaMapeado, array_column(CatalogoCamposSistema::paraTarget($this->target), 'codigo'), true)) {
                throw new EsquemaInvalidoException('El destino de la columna no pertenece a esta operación.');
            }
            $codigo = $columna->clavePayload();

            if (in_array($codigo, $codigos, true)) {
                throw new ColisionCodigosCampoException($codigo, $codigo);
            }

            $codigos[] = $codigo;
        }
    }

    /** Bind persisted metadata to the project that owns the import. */
    public function validarContexto(int $proyectoId, string $tipoEntidad, string $tipoOperacion): void
    {
        $this->validar();
        $targetProyecto = match ($tipoOperacion) {
            'cobranza' => TargetImportacion::CASO_COBRANZA,
            'cx' => TargetImportacion::CASO_TICKET_CX,
            'venta' => TargetImportacion::CASO_LEAD_VENTA,
            'servicio' => TargetImportacion::CASO_SERVICIO,
            default => null,
        };
        if ($this->proyectoId !== $proyectoId || $this->target->value !== $tipoEntidad
            || ($this->target !== TargetImportacion::PERSONA && $this->target !== $targetProyecto)) {
            throw new EsquemaInvalidoException('El esquema no corresponde al proyecto y operación de esta importación.');
        }
    }

    /**
     * Serializa el esquema a JSON para almacenamiento en base de datos.
     */
    public function serializar(): string
    {
        $datos = [
            'target' => $this->target->value,
            'proyecto_id' => $this->proyectoId,
            'cartera_id' => $this->carteraId,
            'modo' => $this->modo->value,
            'reincorporar_archivadas' => $this->reincorporarArchivadas,
            'autorizado_por_id' => $this->autorizadoPorId,
            'formato_entrada' => $this->formatoEntrada,
            'columnas' => array_map(
                static fn (ColumnaExcel $c): array => [
                    'nombre_original' => $c->nombreOriginal,
                    'tipo_inferido' => $c->tipoInferido->value,
                    'campo_sistema_mapeado' => $c->campoSistemaMapeado,
                    'es_identificador_persona' => $c->esIdentificadorPersona,
                    'es_identificador_caso' => $c->esIdentificadorCaso,
                    'accion' => $c->accion->value,
                    'etiqueta_personalizada' => $c->etiquetaPersonalizada,
                    // Sin esta clave el rol se perdía al persistir y `deserializar()`
                    // lo rellenaba con NINGUNO: el camino asíncrono no generó
                    // contactos ni una sola vez desde que existe la opción.
                    'rol_contacto' => $c->rolContacto->value,
                ],
                $this->columnas,
            ),
        ];

        return json_encode($datos, JSON_THROW_ON_ERROR);
    }

    /**
     * Reconstruye el esquema desde su representación JSON.
     *
     * @throws \InvalidArgumentException
     */
    public static function deserializar(string $json): self
    {
        try {
            $datos = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(
                'El JSON del esquema de importación está malformado: '.$e->getMessage(),
                0,
                $e,
            );
        }

        $clavesRequeridas = ['target', 'proyecto_id', 'cartera_id', 'modo', 'columnas'];

        if (! is_array($datos)) {
            throw new \InvalidArgumentException('El esquema de importación tiene una estructura inválida.');
        }

        foreach ($clavesRequeridas as $clave) {
            if (! array_key_exists($clave, $datos)) {
                throw new \InvalidArgumentException(
                    sprintf('Falta la clave requerida "%s" en el esquema de importación.', $clave),
                );
            }
        }

        if (! is_array($datos['columnas'])
            || (isset($datos['reincorporar_archivadas']) && ! is_bool($datos['reincorporar_archivadas']))) {
            throw new \InvalidArgumentException('El esquema de importación tiene una estructura inválida.');
        }

        $columnas = [];

        foreach ($datos['columnas'] as $col) {
            $columnas[] = new ColumnaExcel(
                nombreOriginal: $col['nombre_original'],
                tipoInferido: TipoCampo::from($col['tipo_inferido']),
                campoSistemaMapeado: $col['campo_sistema_mapeado'] ?? null,
                esIdentificadorPersona: (bool) ($col['es_identificador_persona'] ?? false),
                esIdentificadorCaso: (bool) ($col['es_identificador_caso'] ?? false),
                accion: AccionColumna::from($col['accion']),
                etiquetaPersonalizada: $col['etiqueta_personalizada'] ?? null,
                rolContacto: RolContacto::from($col['rol_contacto'] ?? RolContacto::NINGUNO->value),
            );
        }

        return new self(
            target: TargetImportacion::from($datos['target']),
            proyectoId: (int) $datos['proyecto_id'],
            carteraId: $datos['cartera_id'] !== null ? (int) $datos['cartera_id'] : null,
            modo: ModoImportacion::from($datos['modo']),
            columnas: $columnas,
            reincorporarArchivadas: (bool) ($datos['reincorporar_archivadas'] ?? false),
            autorizadoPorId: isset($datos['autorizado_por_id']) ? (int) $datos['autorizado_por_id'] : null,
            formatoEntrada: (string) ($datos['formato_entrada'] ?? 'proyecto'),
        );
    }
}
