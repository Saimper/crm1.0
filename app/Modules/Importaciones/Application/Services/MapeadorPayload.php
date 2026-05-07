<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Services;

/**
 * Aplica un mapeo {campo_sistema_codigo => columna_csv} a una fila cruda y
 * devuelve un payload canónico (mismas keys que esperan los UseCases procesadores).
 * Campos sin mapeo no aparecen en el payload — el consumidor (Livewire Importar)
 * añade defaults antes de persistir cuando aplican.
 */
final readonly class MapeadorPayload
{
    /**
     * @param  list<string>  $headersCsv
     * @param  list<string>  $filaCruda  valores indexados por posición
     * @param  array<string, string>  $mapeo  campo_sistema_codigo → columna_csv (vacío = no mapeado)
     * @return array<string, string>
     */
    public function aPayloadCanonico(array $headersCsv, array $filaCruda, array $mapeo): array
    {
        $indicePorHeader = array_flip($headersCsv);

        $payload = [];
        foreach ($mapeo as $campoSistema => $columnaCsv) {
            if ($columnaCsv === '' || ! isset($indicePorHeader[$columnaCsv])) {
                continue;
            }
            $idx = $indicePorHeader[$columnaCsv];
            $valor = $filaCruda[$idx] ?? '';
            $payload[$campoSistema] = trim($valor);
        }

        return $payload;
    }

    /**
     * Heurística de auto-mapeo: intenta hacer match por nombre normalizado
     * (lowercase, sin espacios/guiones/underscores) entre campos sistema y headers CSV.
     *
     * @param  list<string>  $codigosCampoSistema
     * @param  list<string>  $headersCsv
     * @return array<string, string> campo_sistema → columna_csv ('' si no hubo match)
     */
    public function autoMapear(array $codigosCampoSistema, array $headersCsv): array
    {
        $normalizado = [];
        foreach ($headersCsv as $h) {
            $normalizado[$this->normalizar($h)] = $h;
        }

        $out = [];
        foreach ($codigosCampoSistema as $codigo) {
            $key = $this->normalizar($codigo);
            $out[$codigo] = $normalizado[$key] ?? '';
        }

        return $out;
    }

    private function normalizar(string $s): string
    {
        $s = mb_strtolower($s);
        $s = (string) preg_replace('/[\s\-_]+/u', '', $s);

        return $s;
    }
}
