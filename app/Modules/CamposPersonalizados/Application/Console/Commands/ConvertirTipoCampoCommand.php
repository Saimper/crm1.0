<?php

declare(strict_types=1);

namespace App\Modules\CamposPersonalizados\Application\Console\Commands;

use App\Modules\CamposPersonalizados\Domain\ValueObjects\TipoCampo;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Convierte un campo personalizado de texto a su tipo real, moviendo los valores
 * ya cargados a la columna que les corresponde.
 *
 * Las importaciones anteriores a F41 crearon todo como `texto_corto`: en
 * producción los 123 campos del proyecto de cobranza son texto, incluidos
 * saldos y fechas. Eso rompe cualquier orden y cualquier filtro por rango — los
 * reportes ordenan "100" antes que "99" — y no se arregla editando el tipo en
 * la UI, porque los valores seguirían en `valor_texto_corto`.
 *
 * Dos defensas que el comando NO deja saltarse a la ligera:
 *
 *  1. Ceros a la izquierda. Un campo cuyos valores empiezan por cero es un
 *     identificador (número de préstamo, cédula, teléfono), no una cantidad.
 *     Convertirlo destruye el dato: `0012345` se vuelve `12345` y ya no
 *     identifica a nadie. Se aborta salvo --forzar explícito.
 *  2. Valores que no parsean. Si alguno no encaja en el tipo destino, se aborta
 *     y se listan, en vez de perderlos por el camino.
 */
final class ConvertirTipoCampoCommand extends Command
{
    protected $signature = 'campos:convertir-tipo
                            {campo : ID del campo personalizado}
                            {tipo : Tipo destino (numero_entero, numero_decimal, fecha, fecha_hora, moneda)}
                            {--moneda=USD : Código de divisa a escribir cuando el destino es moneda}
                            {--dry-run : Solo informa qué pasaría}
                            {--forzar : Convierte aunque haya ceros a la izquierda (destruye identificadores)}';

    protected $description = 'Convierte un campo personalizado de texto a su tipo real, migrando los valores';

    /**
     * Tipo destino => columna donde vive el valor.
     *
     * `moneda` entró después: su valor son DOS columnas (`valor_moneda_monto` +
     * `valor_moneda_codigo`) y el texto de origen no trae divisa, así que la
     * divisa la dice quien ejecuta con `--moneda` y por defecto es USD. Sin esa
     * opción explícita seguiría estando fuera.
     */
    private const COLUMNA = [
        'numero_entero' => 'valor_numero_entero',
        'numero_decimal' => 'valor_numero_decimal',
        'fecha' => 'valor_fecha',
        'fecha_hora' => 'valor_fecha_hora',
        'moneda' => 'valor_moneda_monto',
    ];

    public function handle(): int
    {
        $campoId = (int) $this->argument('campo');
        $tipoDestino = (string) $this->argument('tipo');
        $seco = (bool) $this->option('dry-run');

        if (! isset(self::COLUMNA[$tipoDestino])) {
            $this->error("Tipo destino no soportado: {$tipoDestino}. Admitidos: ".implode(', ', array_keys(self::COLUMNA)));

            return self::FAILURE;
        }

        $campo = DB::table('campos_personalizados')->where('id', $campoId)->first(['id', 'codigo', 'tipo', 'proyecto_id']);

        if ($campo === null) {
            $this->error("No existe el campo {$campoId}.");

            return self::FAILURE;
        }

        // La condición no es que el campo esté declarado como texto, sino que sus
        // valores sigan viviendo en una columna de texto. Son cosas distintas
        // desde que se descubrió que cambiar el tipo desde la UI mueve la
        // definición y deja los valores atrás: hay campos declarados `moneda`
        // cuyo contenido está en `valor_texto_corto`, y son justo los que hay
        // que poder arreglar.
        $origen = $this->columnaDeTextoConValores($campoId);

        if ($origen === null) {
            $this->error("El campo {$campo->codigo} no tiene valores en ninguna columna de texto; no hay nada que convertir desde texto.");
            $this->line('Si el tipo ya es el bueno y los valores están descolocados, usa `campos:recolocar-valores`.');

            return self::FAILURE;
        }
        $valores = DB::table('valores_campo_personalizado')
            ->where('campo_personalizado_id', $campoId)
            ->whereNotNull($origen)
            ->where($origen, '!=', '')
            ->get(['id', $origen]);

        $this->line("Campo {$campo->codigo} (proyecto {$campo->proyecto_id}): {$valores->count()} valores con contenido.");

        if ($valores->isEmpty()) {
            $this->convertirDefinicion($campoId, $tipoDestino, $seco);

            return self::SUCCESS;
        }

        if (! $this->option('forzar') && $this->tieneCerosALaIzquierda($valores, $origen)) {
            $this->error('Hay valores que empiezan por cero: esto parece un identificador, no una cantidad.');
            $this->line('Convertirlo perdería los ceros y el dato dejaría de identificar. Usa --forzar solo si estás seguro.');

            return self::FAILURE;
        }

        [$convertidos, $fallidos] = $this->traducir($valores, $origen, $tipoDestino);

        if ($fallidos !== []) {
            $this->error(count($fallidos).' valores no encajan en '.$tipoDestino.'. No se convierte nada.');
            foreach (array_slice($fallidos, 0, 10) as $v) {
                $this->line('  · '.var_export($v, true));
            }

            return self::FAILURE;
        }

        if ($seco) {
            $this->info("Simulación: {$valores->count()} valores pasarían a {$tipoDestino}.");

            return self::SUCCESS;
        }

        $columna = self::COLUMNA[$tipoDestino];

        $moneda = (string) $this->option('moneda');

        DB::transaction(function () use ($convertidos, $columna, $origen, $campoId, $tipoDestino, $moneda): void {
            foreach (array_chunk($convertidos, 500, true) as $lote) {
                foreach ($lote as $id => $valor) {
                    $update = [$columna => $valor, $origen => null];

                    if ($tipoDestino === TipoCampo::MONEDA->value) {
                        $update['valor_moneda_codigo'] = $moneda;
                    }

                    DB::table('valores_campo_personalizado')->where('id', $id)->update($update);
                }
            }

            DB::table('campos_personalizados')->where('id', $campoId)->update(['tipo' => $tipoDestino]);
        });

        $this->info('Listo: '.count($convertidos)." valores movidos a {$columna} y el campo es ahora {$tipoDestino}.");

        return self::SUCCESS;
    }

    /** La columna de texto donde este campo aún guarda valores, si es que hay alguna. */
    private function columnaDeTextoConValores(int $campoId): ?string
    {
        foreach (['valor_texto_corto', 'valor_texto_largo'] as $columna) {
            $hay = DB::table('valores_campo_personalizado')
                ->where('campo_personalizado_id', $campoId)
                ->whereNotNull($columna)
                ->where($columna, '!=', '')
                ->exists();

            if ($hay) {
                return $columna;
            }
        }

        return null;
    }

    /** @param Collection<int, \stdClass> $valores */
    private function tieneCerosALaIzquierda($valores, string $origen): bool
    {
        foreach ($valores as $v) {
            $bruto = trim((string) $v->{$origen});

            if (preg_match('/^0[0-9]/', $bruto) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, \stdClass>  $valores
     * @return array{0: array<int, string>, 1: list<string>}
     */
    private function traducir($valores, string $origen, string $tipoDestino): array
    {
        $convertidos = [];
        $fallidos = [];

        foreach ($valores as $v) {
            $bruto = trim((string) $v->{$origen});
            $traducido = $this->traducirUno($bruto, $tipoDestino);

            if ($traducido === null) {
                $fallidos[] = $bruto;

                continue;
            }

            $convertidos[(int) $v->id] = $traducido;
        }

        return [$convertidos, $fallidos];
    }

    private function traducirUno(string $bruto, string $tipoDestino): ?string
    {
        if ($tipoDestino === 'fecha' || $tipoDestino === 'fecha_hora') {
            // Solo ISO. Un dd/mm/yyyy es indistinguible de un mm/dd/yyyy y
            // adivinar el orden es exactamente como se corrompen las fechas.
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})([ T](\d{2}):(\d{2})(:(\d{2}))?)?$/', $bruto, $m) !== 1) {
                return null;
            }

            if (! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return null;
            }

            return $tipoDestino === 'fecha'
                ? "{$m[1]}-{$m[2]}-{$m[3]}"
                : "{$m[1]}-{$m[2]}-{$m[3]} ".($m[5] ?? '00').':'.($m[6] ?? '00').':'.($m[8] ?? '00');
        }

        $limpio = str_replace(',', '.', $bruto);

        if (! is_numeric($limpio)) {
            return null;
        }

        if ($tipoDestino === 'numero_entero') {
            return ((float) $limpio) == (int) (float) $limpio ? (string) (int) (float) $limpio : null;
        }

        return $limpio;
    }

    private function convertirDefinicion(int $campoId, string $tipoDestino, bool $seco): void
    {
        if ($seco) {
            $this->info("Simulación: el campo pasaría a {$tipoDestino} (no tiene valores que mover).");

            return;
        }

        try {
            DB::table('campos_personalizados')->where('id', $campoId)->update(['tipo' => $tipoDestino]);
            $this->info("Campo convertido a {$tipoDestino} (no tenía valores).");
        } catch (Throwable $e) {
            $this->error('No se pudo actualizar la definición: '.$e->getMessage());
        }
    }
}
