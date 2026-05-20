<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\UseCases;

use App\Modules\Importaciones\Application\Services\InferidorTiposColumnas;
use App\Modules\Importaciones\Domain\Catalogo\CampoSistema;
use App\Modules\Importaciones\Domain\Catalogo\CatalogoCamposSistema;
use App\Modules\Importaciones\Domain\Enums\AccionColumna;
use App\Modules\Importaciones\Domain\ValueObjects\ColumnaExcel;

/**
 * Analiza los headers de un archivo importado e infiere un esquema inicial:
 * tipo de cada columna, auto-mapeo a campos del sistema y sugerencia
 * de identificador de persona.
 */
final readonly class InferirEsquemaDesdeHeaders
{
    /** @var list<string> Patrones de nombres que sugieren identidad de persona */
    private const PATRONES_IDENTIDAD = [
        'id', 'ced', 'cedula', 'cédula', 'doc', 'documento',
        'identificacion', 'identificación', 'ruc', 'nit', 'curp',
        'dni', 'pasaporte', 'num_documento', 'nro_documento',
    ];

    public function __construct(
        private InferidorTiposColumnas $inferidor,
    ) {}

    public function execute(InferirEsquemaInput $input): InferirEsquemaOutput
    {
        $camposSistema = CatalogoCamposSistema::paraTarget($input->target);
        $mapaSistema = $this->construirMapaSistema($camposSistema);

        $advertencias = [];
        $columnas = [];

        foreach ($input->headers as $header) {
            $valoresColumna = $this->extraerValoresColumna($header, $input->filasMuestra);
            $tipoInferido = $this->inferidor->inferir($valoresColumna);

            $campoMapeado = $this->buscarMatchSistema($header, $mapaSistema);

            if ($campoMapeado !== null) {
                $accion = AccionColumna::MAPEAR_SISTEMA;
            } else {
                $accion = AccionColumna::CREAR_CP;
            }

            $columnas[] = new ColumnaExcel(
                nombreOriginal: $header,
                tipoInferido: $tipoInferido,
                campoSistemaMapeado: $campoMapeado,
                esIdentificadorPersona: false,
                accion: $accion,
            );
        }

        $sugerenciaIdentificador = $this->sugerirIdentificador($columnas, $input->filasMuestra);

        if ($sugerenciaIdentificador === null) {
            $advertencias[] = 'No se detectó automáticamente una columna de identidad de persona. Deberás seleccionarla manualmente.';
        }

        return new InferirEsquemaOutput(
            columnas: $columnas,
            sugerenciaIdentificador: $sugerenciaIdentificador,
            advertencias: $advertencias,
        );
    }

    /**
     * @param list<CampoSistema> $campos
     * @return array<string, CampoSistema>
     */
    private function construirMapaSistema(array $campos): array
    {
        $mapa = [];

        foreach ($campos as $campo) {
            $mapa[$this->normalizar($campo->codigo)] = $campo;
        }

        return $mapa;
    }

    /**
     * @param array<string, CampoSistema> $mapaSistema
     */
    private function buscarMatchSistema(string $header, array $mapaSistema): ?string
    {
        $normalizado = $this->normalizar($header);
        $campo = $mapaSistema[$normalizado] ?? null;

        return $campo?->codigo;
    }

    /**
     * @param list<ColumnaExcel> $columnas
     * @param list<array<string, string>> $filasMuestra
     */
    private function sugerirIdentificador(array $columnas, array $filasMuestra): ?string
    {
        $candidatas = [];

        foreach ($columnas as $columna) {
            $headerLower = mb_strtolower($columna->nombreOriginal);

            foreach (self::PATRONES_IDENTIDAD as $patron) {
                if (str_contains($headerLower, $patron)) {
                    $valoresUnicos = $this->cardinalidadEnMuestra($columna->nombreOriginal, $filasMuestra);
                    $candidatas[$columna->nombreOriginal] = $valoresUnicos;

                    break;
                }
            }
        }

        if ($candidatas === []) {
            return null;
        }

        if (count($candidatas) === 1) {
            return array_key_first($candidatas);
        }

        arsort($candidatas);

        return array_key_first($candidatas);
    }

    /**
     * @param list<array<string, string>> $filasMuestra
     */
    private function cardinalidadEnMuestra(string $header, array $filasMuestra): int
    {
        $valores = [];

        foreach ($filasMuestra as $fila) {
            $valor = $fila[$header] ?? null;
            if ($valor !== null && trim($valor) !== '') {
                $valores[] = trim($valor);
            }
        }

        return count(array_unique($valores));
    }

    /**
     * @param list<array<string, string>> $filasMuestra
     * @return list<string>
     */
    private function extraerValoresColumna(string $header, array $filasMuestra): array
    {
        $valores = [];

        foreach ($filasMuestra as $fila) {
            $valores[] = $fila[$header] ?? '';
        }

        return $valores;
    }

    private function normalizar(string $s): string
    {
        $s = mb_strtolower($s);
        $s = (string) preg_replace('/[\s\-_]+/u', '', $s);

        return $s;
    }
}
