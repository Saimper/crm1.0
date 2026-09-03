<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Infrastructure\Persistence\Repositories;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\Importaciones\Domain\Contracts\CampoPersonalizadoImportacionRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Implementación del repositorio de campos personalizados para importaciones.
 *
 * Usa DB::table() directo — nunca importa modelos Eloquent de CamposPersonalizados.
 */
final readonly class EloquentCampoPersonalizadoImportacionRepository implements CampoPersonalizadoImportacionRepository
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function existeCampo(int $proyectoId, int $carteraId, string $codigo): bool
    {
        return $this->db->table('campos_personalizados')
            ->where('proyecto_id', $proyectoId)
            ->where('ambito', 'caso')
            ->where('ambito_id', $carteraId)
            ->where('codigo', $codigo)
            ->where('activo', true)
            ->exists();
    }

    public function crearCampo(int $proyectoId, int $carteraId, string $codigo, string $etiqueta, TipoCampo $tipo): int
    {
        $ahora = CarbonImmutable::now();

        return (int) $this->db->table('campos_personalizados')->insertGetId([
            'proyecto_id' => $proyectoId,
            'ambito' => 'caso',
            'ambito_id' => $carteraId,
            'codigo' => $codigo,
            'etiqueta' => $etiqueta,
            'tipo' => $tipo->value,
            'obligatorio' => false,
            'activo' => true,
            'orden' => 0,
            'reglas' => '{}',
            'creada_en' => $ahora,
            'actualizada_en' => $ahora,
        ]);
    }

    public function obtenerMapaCampos(int $proyectoId, int $carteraId): array
    {
        $rows = $this->db->table('campos_personalizados')
            ->where('proyecto_id', $proyectoId)
            ->where('ambito', 'caso')
            ->where('ambito_id', $carteraId)
            ->where('activo', true)
            ->get(['id', 'codigo', 'tipo']);

        $mapa = [];

        foreach ($rows as $row) {
            $mapa[(string) $row->codigo] = [
                'id' => (int) $row->id,
                'tipo' => (string) $row->tipo,
            ];
        }

        return $mapa;
    }

    /**
     * Límite seguro de placeholders MySQL (menor a 65535) dividido por ~7 columnas
     * (campo_personalizado_id, entidad_id, creada_en, actualizada_en + 2-3 de valor).
     */
    private const CHUNK_UPSERT = 2000;

    /**
     * Columnas tipadas de `valores_campo_personalizado`. Cada fila del lote se
     * normaliza a este set completo (null donde no aplica): el upsert masivo toma
     * la lista de columnas de la primera fila, y un lote que mezcla tipos (p. ej.
     * texto + moneda) rompería con "Column count doesn't match value count".
     */
    private const COLUMNAS_VALOR = [
        'valor_texto_corto', 'valor_texto_largo',
        'valor_numero_entero', 'valor_numero_decimal',
        'valor_fecha', 'valor_fecha_hora', 'valor_booleano',
        'valor_opcion_id', 'valor_opciones_ids',
        'valor_moneda_monto', 'valor_moneda_codigo',
    ];

    public function guardarValoresEnLote(array $lote): void
    {
        if ($lote === []) {
            return;
        }

        $ahora = CarbonImmutable::now();
        $rows = array_map(fn (array $item): array => $this->filaNormalizada($item, $ahora), $lote);
        $actualizables = [...self::COLUMNAS_VALOR, 'actualizada_en'];

        foreach (array_chunk($rows, self::CHUNK_UPSERT) as $chunk) {
            $this->db->table('valores_campo_personalizado')
                ->upsert($chunk, ['campo_personalizado_id', 'entidad_id'], $actualizables);
        }
    }

    /**
     * @param  array{campo_id: int, entidad_id: int, valor: mixed, tipo?: string}  $item
     * @return array<string, mixed>
     */
    private function filaNormalizada(array $item, CarbonImmutable $ahora): array
    {
        return [
            'campo_personalizado_id' => (int) $item['campo_id'],
            'entidad_id' => (int) $item['entidad_id'],
            'creada_en' => $ahora,
            'actualizada_en' => $ahora,
            ...array_fill_keys(self::COLUMNAS_VALOR, null),
            ...$this->mapearValorAColumna($item['tipo'] ?? 'texto_corto', $item['valor']),
        ];
    }

    /**
     * Mapea un valor a la columna tipada correspondiente en valores_campo_personalizado.
     *
     * @return array<string, mixed>
     */
    private function mapearValorAColumna(string $tipo, mixed $valor): array
    {
        return match ($tipo) {
            'texto_corto' => ['valor_texto_corto' => $valor !== null ? mb_substr((string) $valor, 0, 255) : null],
            'texto_largo' => ['valor_texto_largo' => $valor !== null ? (string) $valor : null],
            'numero_entero' => ['valor_numero_entero' => $valor !== null ? (int) $valor : null],
            'numero_decimal' => ['valor_numero_decimal' => $valor !== null ? (float) $valor : null],
            'fecha' => ['valor_fecha' => $valor !== null ? (string) $valor : null],
            'fecha_hora' => ['valor_fecha_hora' => $valor !== null ? (string) $valor : null],
            'booleano' => ['valor_booleano' => $valor !== null ? (bool) $valor : null],
            'seleccion_unica' => ['valor_opcion_id' => $valor !== null ? (int) $valor : null],
            'seleccion_multiple' => ['valor_opciones_ids' => $valor !== null ? (string) $valor : null],
            'moneda' => [
                'valor_moneda_monto' => $valor !== null ? (float) $valor : null,
                'valor_moneda_codigo' => 'USD',
            ],
            default => ['valor_texto_corto' => $valor !== null ? (string) $valor : null],
        };
    }

    public function registrarAuditoriaCampos(int $importacionId, array $campos): void
    {
        if ($campos === []) {
            return;
        }

        $ahora = CarbonImmutable::now();
        $rows = [];

        foreach ($campos as $campo) {
            $rows[] = [
                'importacion_id' => $importacionId,
                'campo_personalizado_id' => (int) $campo['campo_id'],
                'columna_original' => (string) $campo['columna_original'],
                'creada_en' => $ahora,
            ];
        }

        $this->db->table('importacion_campos_personalizados')
            ->upsert($rows, ['importacion_id', 'campo_personalizado_id'], ['columna_original', 'creada_en']);
    }
}
