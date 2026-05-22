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

    public function guardarValoresEnLote(array $lote): void
    {
        if ($lote === []) {
            return;
        }

        $ahora = CarbonImmutable::now();

        $rows = [];

        foreach ($lote as $item) {
            $campoId = (int) $item['campo_id'];
            $entidadId = (int) $item['entidad_id'];
            $valor = $item['valor'];
            $tipo = $item['tipo'] ?? 'texto_corto';

            $row = [
                'campo_personalizado_id' => $campoId,
                'entidad_id' => $entidadId,
                'creada_en' => $ahora,
                'actualizada_en' => $ahora,
            ];

            $row = array_merge($row, $this->mapearValorAColumna($tipo, $valor));

            $rows[] = $row;
        }

        if ($rows === []) {
            return;
        }

        $columnas = array_keys($rows[0]);

        foreach (array_chunk($rows, self::CHUNK_UPSERT) as $chunk) {
            $this->db->table('valores_campo_personalizado')
                ->upsert($chunk, ['campo_personalizado_id', 'entidad_id'], array_slice($columnas, 2));
        }
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
