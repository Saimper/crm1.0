<?php

declare(strict_types=1);

namespace App\Modules\Importaciones\Application\Console\Commands;

use App\Modules\Importaciones\Domain\Catalogo\SinonimosCampoSistema;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Rescata a columnas nativas los datos que importaciones anteriores dejaron
 * únicamente como Campos Personalizados.
 *
 * Hasta F41 el catálogo de campos del sistema solo reconocía la identificación,
 * así que nombre, saldos y demás terminaban como CP aunque tuvieran columna
 * propia: la persona quedaba sin `nombres` y el caso sin saldos. Este comando
 * relee esos valores y llena las columnas vacías. No borra los CP ni pisa
 * valores ya presentes, de modo que es seguro correrlo más de una vez.
 */
final class RescatarCamposNativosCommand extends Command
{
    protected $signature = 'importaciones:rescatar-campos-nativos
                            {--proyecto= : ID del proyecto a reparar (por defecto, todos)}
                            {--dry-run : Solo informa cuántos registros se actualizarían}';

    protected $description = 'Copia a columnas nativas los datos que quedaron solo como campos personalizados';

    /** Campos del sistema que viven en la tabla personas */
    private const CAMPOS_PERSONA = ['nombres', 'apellidos', 'razon_social'];

    /** campo del sistema => columna en casos_cobranza */
    private const CAMPOS_COBRANZA = [
        'saldo_total' => 'saldo_total',
        'saldo_capital' => 'saldo_capital',
        'saldo_interes' => 'saldo_interes',
        'monto_original' => 'monto_original',
        'cuota_mensual' => 'cuota_mensual',
        'dias_mora' => 'dias_mora',
    ];

    public function handle(): int
    {
        $seco = (bool) $this->option('dry-run');
        $total = 0;

        foreach ($this->camposCandidatos() as $campo) {
            $destino = SinonimosCampoSistema::buscar((string) $campo->codigo);

            if ($destino === null) {
                continue;
            }

            $afectados = $this->rescatarCampo((int) $campo->id, $destino, $seco);

            if ($afectados > 0) {
                $this->line(sprintf(
                    '  %-26s → %-16s %s%d',
                    $campo->codigo, $destino, $seco ? 'pendientes: ' : 'actualizados: ', $afectados,
                ));
                $total += $afectados;
            }
        }

        $this->info($seco
            ? "Simulación: {$total} registros se actualizarían."
            : "Listo: {$total} registros actualizados.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, stdClass>
     */
    private function camposCandidatos(): Collection
    {
        $q = DB::table('campos_personalizados')
            ->where('ambito', 'caso')
            ->where('activo', true);

        $proyecto = $this->option('proyecto');
        if (is_string($proyecto) && ctype_digit($proyecto)) {
            $q->where('proyecto_id', (int) $proyecto);
        }

        return $q->select(['id', 'codigo', 'proyecto_id'])->get();
    }

    private function rescatarCampo(int $campoId, string $destino, bool $seco): int
    {
        if (in_array($destino, self::CAMPOS_PERSONA, true)) {
            return $this->rescatarAPersona($campoId, $destino, $seco);
        }

        $columna = self::CAMPOS_COBRANZA[$destino] ?? null;

        return $columna !== null ? $this->rescatarACobranza($campoId, $columna, $seco) : 0;
    }

    /**
     * El valor del CP cuelga del caso; la columna destino vive en la persona dueña del caso.
     */
    private function rescatarAPersona(int $campoId, string $columna, bool $seco): int
    {
        $filas = DB::table('valores_campo_personalizado as v')
            ->join('casos as c', 'c.id', '=', 'v.entidad_id')
            ->join('personas as p', 'p.id', '=', 'c.persona_id')
            ->where('v.campo_personalizado_id', $campoId)
            ->whereNull('c.eliminada_en')
            ->where(fn ($w) => $w->whereNull('p.'.$columna)->orWhere('p.'.$columna, ''))
            ->select(['p.id as destino_id', DB::raw($this->expresionValor())])
            ->get();

        if ($seco) {
            return $filas->count();
        }

        return $this->aplicar($filas, fn (stdClass $fila): int => DB::table('personas')
            ->where('id', $fila->destino_id)
            ->where(fn ($w) => $w->whereNull($columna)->orWhere($columna, ''))
            ->update([$columna => mb_substr(trim((string) $fila->valor), 0, 150)]));
    }

    private function rescatarACobranza(int $campoId, string $columna, bool $seco): int
    {
        $filas = DB::table('valores_campo_personalizado as v')
            ->join('casos as c', 'c.id', '=', 'v.entidad_id')
            ->join('casos_cobranza as cc', 'cc.caso_id', '=', 'c.id')
            ->where('v.campo_personalizado_id', $campoId)
            ->whereNull('c.eliminada_en')
            ->whereNull('cc.'.$columna)
            ->select(['cc.caso_id as destino_id', DB::raw($this->expresionValor())])
            ->get();

        if ($seco) {
            return $filas->count();
        }

        return $this->aplicar($filas, function (stdClass $fila) use ($columna): int {
            $numero = $this->aNumero((string) $fila->valor);

            return $numero === null ? 0 : DB::table('casos_cobranza')
                ->where('caso_id', $fila->destino_id)
                ->whereNull($columna)
                ->update([$columna => $numero]);
        });
    }

    /**
     * @param  Collection<int, stdClass>  $filas
     * @param  callable(stdClass): int  $actualizar
     */
    private function aplicar(Collection $filas, callable $actualizar): int
    {
        $actualizados = 0;

        foreach ($filas->chunk(500) as $lote) {
            DB::transaction(function () use ($lote, $actualizar, &$actualizados): void {
                foreach ($lote as $fila) {
                    $actualizados += $actualizar($fila);
                }
            });
        }

        return $actualizados;
    }

    /**
     * El valor del CP está en la columna del tipo correspondiente; solo una está llena.
     */
    private function expresionValor(): string
    {
        return 'COALESCE(v.valor_texto_corto, v.valor_texto_largo, v.valor_moneda, '
            .'v.valor_numero_decimal, v.valor_numero_entero) as valor';
    }

    /**
     * Normaliza "1,250.40", "$1.250,40" o "(120)" a un decimal utilizable.
     */
    private function aNumero(string $bruto): ?string
    {
        $negativo = str_starts_with($bruto, '(') && str_ends_with($bruto, ')');
        $limpio = (string) preg_replace('/[^0-9,.\-]/', '', $bruto);
        $limpio = $this->unificarSeparadorDecimal($limpio);

        if ($limpio === '' || ! is_numeric($limpio)) {
            return null;
        }

        return $negativo ? '-'.ltrim($limpio, '-') : $limpio;
    }

    private function unificarSeparadorDecimal(string $numero): string
    {
        $ultimaComa = strrpos($numero, ',');
        $ultimoPunto = strrpos($numero, '.');

        if ($ultimaComa !== false && $ultimoPunto !== false) {
            return $ultimaComa > $ultimoPunto
                ? str_replace(',', '.', str_replace('.', '', $numero))
                : str_replace(',', '', $numero);
        }

        if ($ultimaComa !== false) {
            return substr_count($numero, ',') === 1 && strlen($numero) - $ultimaComa <= 3
                ? str_replace(',', '.', $numero)
                : str_replace(',', '', $numero);
        }

        return $numero;
    }
}
