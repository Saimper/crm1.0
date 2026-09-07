<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Application\Services;

use App\Modules\CamposPersonalizados\Domain\Services\EvaluadorReglas;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\AmbitoCampo;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\ContextoUsuarioProyecto;
use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use App\Modules\CamposPersonalizados\Infrastructure\Persistence\Models\CampoPersonalizadoModel;
use App\Modules\CamposPersonalizados\Infrastructure\Persistence\Models\ValorCampoPersonalizadoModel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/**
 * Servicio de alto nivel para leer y guardar valores de campos personalizados.
 * Encapsula el mapeo valor ↔ columna por tipo (§7.3 CLAUDE.md v2).
 */
final readonly class ServicioCamposPersonalizados
{
    public function __construct(
        private EvaluadorReglas $evaluador,
        private ConnectionInterface $db,
    ) {}

    /**
     * Devuelve los campos personalizados aplicables al ámbito dado (activos, ordenados).
     *
     * @return Collection<int, CampoPersonalizadoModel>
     */
    public function campos(int $proyectoId, AmbitoCampo $ambito, int $ambitoId): Collection
    {
        return CampoPersonalizadoModel::query()
            ->sinScopeProyecto()
            ->where('proyecto_id', $proyectoId)
            ->where('ambito', $ambito->value)
            ->where('ambito_id', $ambitoId)
            ->where('activo', true)
            ->orderBy('orden')
            ->get();
    }

    /**
     * Valida y persiste los valores para una entidad (caso/gestión/compromiso).
     *
     * `$permitirVaciar` decide qué significa un `null` entrante, y por defecto
     * significa «no lo sé», no «bórralo».
     *
     * El motivo es un incidente real: la Vista de Trabajo enviaba los 34 campos
     * del caso en cada gestión, incluidos los que no sabía leer —los de tipo
     * `moneda`, cuyos valores estaban en `valor_texto_corto` tras un cambio de
     * tipo hecho desde la UI—. Llegaban como `null`, `mapearValorAColumna`
     * devolvía las once columnas a null y el `updateOrCreate` las escribía. Tres
     * filas de producción quedaron en blanco así, y había 22.623 valores a una
     * gestión de distancia.
     *
     * Sólo debe pasar `true` quien es dueño del formulario completo y por tanto
     * puede distinguir «el usuario vació este campo» de «este campo no estaba en
     * la pantalla».
     *
     * @param  array<string, mixed>  $valoresPorCodigo  [codigo_campo => valor]
     */
    public function guardarValores(
        int $proyectoId,
        AmbitoCampo $ambito,
        int $ambitoId,
        int $entidadId,
        array $valoresPorCodigo,
        bool $permitirVaciar = false,
    ): void {
        $campos = $this->campos($proyectoId, $ambito, $ambitoId);

        // 1) Validar todos antes de persistir nada.
        foreach ($campos as $campo) {
            $valor = $valoresPorCodigo[$campo->codigo] ?? null;
            $this->evaluador->validar(
                TipoCampo::from((string) $campo->tipo),
                $valor,
                is_array($campo->reglas) ? $campo->reglas : [],
                (bool) $campo->obligatorio,
                (string) $campo->etiqueta,
            );
        }

        $conValor = $permitirVaciar ? [] : $this->camposConValor($campos, $entidadId);

        // 2) Persistir en transacción.
        $this->db->transaction(function () use ($campos, $valoresPorCodigo, $entidadId, $permitirVaciar, $conValor): void {
            foreach ($campos as $campo) {
                if (! array_key_exists($campo->codigo, $valoresPorCodigo)) {
                    continue;
                }
                $valor = $valoresPorCodigo[$campo->codigo];

                if ($valor === null && ! $permitirVaciar && isset($conValor[(int) $campo->id])) {
                    continue;
                }

                $payload = $this->mapearValorAColumna(TipoCampo::from((string) $campo->tipo), $valor);

                ValorCampoPersonalizadoModel::query()->updateOrCreate(
                    [
                        'campo_personalizado_id' => $campo->id,
                        'entidad_id' => $entidadId,
                    ],
                    $payload,
                );
            }
        });
    }

    /**
     * Los campos del ámbito que YA tienen algo guardado para esta entidad.
     *
     * Se resuelve en una sola consulta y sólo cuando hace falta: es la lista que
     * un `null` entrante no puede pisar.
     *
     * @param  Collection<int, CampoPersonalizadoModel>  $campos
     * @return array<int, true>
     */
    private function camposConValor(Collection $campos, int $entidadId): array
    {
        $ids = $campos->map(fn (CampoPersonalizadoModel $c): int => (int) $c->id)->all();

        if ($ids === []) {
            return [];
        }

        $filas = $this->db->table('valores_campo_personalizado')
            ->whereIn('campo_personalizado_id', $ids)
            ->where('entidad_id', $entidadId)
            ->get([
                'campo_personalizado_id',
                'valor_texto_corto', 'valor_texto_largo',
                'valor_numero_entero', 'valor_numero_decimal',
                'valor_fecha', 'valor_fecha_hora',
                'valor_booleano', 'valor_opcion_id',
                'valor_opciones_ids', 'valor_moneda_monto', 'valor_moneda_codigo',
            ]);

        $conValor = [];
        foreach ($filas as $fila) {
            foreach ((array) $fila as $columna => $valor) {
                if ($columna !== 'campo_personalizado_id' && $valor !== null) {
                    $conValor[(int) $fila->campo_personalizado_id] = true;
                    break;
                }
            }
        }

        return $conValor;
    }

    /**
     * Resuelve los valores iniciales auto-rellenados para los campos del ámbito (§7.4).
     * Solo aplica a campos con `reglas.auto_fill` declarada y tipo compatible.
     *
     * @return array<string, mixed> [codigo => valor]
     */
    public function valoresAutoRelleno(
        int $proyectoId,
        AmbitoCampo $ambito,
        int $ambitoId,
        ContextoUsuarioProyecto $ctx,
    ): array {
        $resultado = [];
        foreach ($this->campos($proyectoId, $ambito, $ambitoId) as $campo) {
            $valor = $this->evaluador->valorAutoFill(
                TipoCampo::from((string) $campo->tipo),
                is_array($campo->reglas) ? $campo->reglas : [],
                $ctx,
            );
            if ($valor !== null) {
                $resultado[(string) $campo->codigo] = $valor;
            }
        }

        return $resultado;
    }

    /**
     * Serializa a strings planos los valores YA persistidos de una entidad, aptos
     * para writeback externo (CRM→ViciDial). A diferencia de leer el binding del
     * formulario (que guarda IDs de opción), resuelve selección→etiqueta y
     * moneda→monto, y descarta nulos/vacíos. Las claves son el `codigo` del campo.
     *
     * @return array<string, string>
     */
    public function valoresSerializadosParaWriteback(
        int $proyectoId,
        AmbitoCampo $ambito,
        int $ambitoId,
        int $entidadId,
    ): array {
        $filas = $this->db->table('valores_campo_personalizado as v')
            ->join('campos_personalizados as c', 'c.id', '=', 'v.campo_personalizado_id')
            ->where('c.proyecto_id', $proyectoId)
            ->where('c.ambito', $ambito->value)
            ->where('c.ambito_id', $ambitoId)
            ->where('c.activo', true)
            ->where('v.entidad_id', $entidadId)
            ->get([
                'c.codigo', 'c.tipo',
                'v.valor_texto_corto', 'v.valor_texto_largo',
                'v.valor_numero_entero', 'v.valor_numero_decimal',
                'v.valor_fecha', 'v.valor_fecha_hora', 'v.valor_booleano',
                'v.valor_opcion_id', 'v.valor_opciones_ids', 'v.valor_moneda_monto',
            ]);

        $out = [];
        foreach ($filas as $fila) {
            $cadena = $this->serializarValor($fila, (string) $fila->tipo);
            if ($cadena === null || $cadena === '') {
                continue;
            }
            $out[(string) $fila->codigo] = $cadena;
        }

        return $out;
    }

    /**
     * Etiquetas (label humano) de los campos indicados por `codigo`, para que el
     * wrapper pueda emparejar por etiqueta cuando el `codigo` no calza con el
     * nombre del campo en ViciDial.
     *
     * @param  list<string>  $codigos
     * @return array<string, string> [codigo => etiqueta]
     */
    public function etiquetasDeCampos(int $proyectoId, AmbitoCampo $ambito, int $ambitoId, array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }

        /** @var array<string, string> $pares */
        $pares = $this->db->table('campos_personalizados')
            ->where('proyecto_id', $proyectoId)
            ->where('ambito', $ambito->value)
            ->where('ambito_id', $ambitoId)
            ->whereIn('codigo', $codigos)
            ->pluck('etiqueta', 'codigo')
            ->all();

        return $pares;
    }

    private function serializarValor(object $fila, string $tipo): ?string
    {
        return match ($tipo) {
            'texto_corto' => $this->aCadena($fila->valor_texto_corto),
            'texto_largo' => $this->aCadena($fila->valor_texto_largo),
            'numero_entero' => $fila->valor_numero_entero === null ? null : (string) (int) $fila->valor_numero_entero,
            'numero_decimal' => $this->aCadena($fila->valor_numero_decimal),
            'fecha' => $this->aCadena($fila->valor_fecha),
            'fecha_hora' => $this->aCadena($fila->valor_fecha_hora),
            'booleano' => $fila->valor_booleano === null ? null : ((bool) $fila->valor_booleano ? '1' : '0'),
            'moneda' => $this->aCadena($fila->valor_moneda_monto),
            'seleccion_unica' => $this->etiquetaOpcion($fila->valor_opcion_id),
            'seleccion_multiple' => $this->etiquetasOpciones($fila->valor_opciones_ids),
            default => null,
        };
    }

    private function aCadena(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $cadena = trim((string) $valor);

        return $cadena === '' ? null : $cadena;
    }

    private function etiquetaOpcion(mixed $opcionId): ?string
    {
        if ($opcionId === null) {
            return null;
        }

        $etiqueta = $this->db->table('opciones_campo_personalizado')
            ->where('id', (int) $opcionId)
            ->value('etiqueta');

        return is_string($etiqueta) && $etiqueta !== '' ? $etiqueta : null;
    }

    private function etiquetasOpciones(mixed $opcionesIds): ?string
    {
        $ids = is_array($opcionesIds) ? $opcionesIds : json_decode((string) $opcionesIds, true);
        if (! is_array($ids)) {
            return null;
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return null;
        }

        $etiquetas = $this->db->table('opciones_campo_personalizado')
            ->whereIn('id', $ids)
            ->orderBy('orden')
            ->pluck('etiqueta')
            ->all();

        return $etiquetas === [] ? null : implode(', ', $etiquetas);
    }

    /** @return array<string, mixed> */
    private function mapearValorAColumna(TipoCampo $tipo, mixed $valor): array
    {
        $columnas = array_fill_keys([
            'valor_texto_corto', 'valor_texto_largo',
            'valor_numero_entero', 'valor_numero_decimal',
            'valor_fecha', 'valor_fecha_hora',
            'valor_booleano', 'valor_opcion_id',
            'valor_opciones_ids', 'valor_moneda_monto', 'valor_moneda_codigo',
        ], null);

        if ($valor === null) {
            return $columnas;
        }

        match ($tipo) {
            TipoCampo::TEXTO_CORTO => $columnas['valor_texto_corto'] = (string) $valor,
            TipoCampo::TEXTO_LARGO => $columnas['valor_texto_largo'] = (string) $valor,
            TipoCampo::NUMERO_ENTERO => $columnas['valor_numero_entero'] = (int) $valor,
            TipoCampo::NUMERO_DECIMAL => $columnas['valor_numero_decimal'] = (string) $valor,
            TipoCampo::FECHA => $columnas['valor_fecha'] = (string) $valor,
            TipoCampo::FECHA_HORA => $columnas['valor_fecha_hora'] = (string) $valor,
            TipoCampo::BOOLEANO => $columnas['valor_booleano'] = (bool) $valor,
            TipoCampo::SELECCION_UNICA => $columnas['valor_opcion_id'] = (int) $valor,
            TipoCampo::SELECCION_MULTIPLE => $columnas['valor_opciones_ids'] = is_array($valor) ? $valor : [$valor],
            TipoCampo::MONEDA => [
                $columnas['valor_moneda_monto'] = is_array($valor) ? (string) ($valor['monto'] ?? '0') : (string) $valor,
                $columnas['valor_moneda_codigo'] = is_array($valor) ? (string) ($valor['moneda'] ?? 'USD') : 'USD',
            ],
        };

        return $columnas;
    }
}
