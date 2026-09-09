<?php

declare(strict_types=1);

use App\Modules\Importaciones\Application\Services\DescriptorDeFalloImportacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Borra de las importaciones ya guardadas el SQL que se escribió en ellas.
 *
 * Antes de esta ola, un fallo de base de datos guardaba `getMessage()` tal
 * cual en `importaciones.error_global` y en `importacion_filas.mensaje_error`.
 * Ese texto trae el INSERT completo con los valores de la fila: cédula,
 * nombre, teléfono. El motivo ya no se guarda así, pero lo guardado sigue en
 * la base, y se enseña en el historial del asistente y viaja en el CSV de
 * filas rechazadas, que puede descargar cualquiera con `importaciones.crear`.
 *
 * Se redacta con el mismo criterio que aplica el descriptor en caliente
 * (fuera lo que va entre comillas simples y las tiras de seis o más dígitos) y
 * se recorta el texto: de un mensaje de MySQL sólo hace falta el SQLSTATE. No
 * se borra la columna entera porque el motivo es lo único que le dice al
 * supervisor por qué se cayó su archivo.
 *
 * Sólo toca las filas que contienen «SQLSTATE[» o «SQL:»; el resto son motivos
 * de dominio escritos por el propio CRM y son legibles y seguros.
 *
 * Irreversible a propósito: `down()` no puede reconstruir un texto que se
 * borró, y tampoco debería querer hacerlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->redactarColumna('importaciones', 'error_global');
        $this->redactarColumna('importacion_filas', 'mensaje_error');
    }

    public function down(): void
    {
        // Sin vuelta atrás: el SQL con los datos personales ya no existe.
    }

    private function redactarColumna(string $tabla, string $columna): void
    {
        DB::table($tabla)
            ->whereNotNull($columna)
            ->where(function (Builder $q) use ($columna): void {
                $q->where($columna, 'like', '%SQLSTATE[%')
                    ->orWhere($columna, 'like', '%SQL:%');
            })
            ->chunkById(500, function (Collection $filas) use ($tabla, $columna): void {
                /** @var Collection<int, stdClass> $filas */
                foreach ($filas as $fila) {
                    DB::table($tabla)
                        ->where('id', $fila->id)
                        ->update([$columna => $this->redactado((string) $fila->{$columna})]);
                }
            });
    }

    private function redactado(string $mensaje): string
    {
        // El SQL empieza donde MySQL lo anuncia; lo de antes es el diagnóstico.
        $corte = strpos($mensaje, '(Connection:');
        if ($corte === false) {
            $corte = strpos($mensaje, ' SQL:');
        }

        $texto = $corte === false ? $mensaje : substr($mensaje, 0, $corte);

        return mb_substr(DescriptorDeFalloImportacion::redactar(trim($texto)), 0, 500);
    }
};
