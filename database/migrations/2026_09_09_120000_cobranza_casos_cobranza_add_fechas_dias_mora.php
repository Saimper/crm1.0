<?php

declare(strict_types=1);

use Carbon\CarbonTimeZone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `dias_mora` era una foto del día de la carga, y nada la movía.
 *
 * El número que el mandante manda en el archivo es cierto el día del archivo.
 * Cada día que pasa sin otro archivo, la cuenta tiene un día más de mora, pero
 * la columna seguía diciendo lo de hace tres meses y el cron de tramos
 * reclasificaba la cartera sobre ese dato muerto. Para envejecer la mora hace
 * falta saber A QUÉ DÍA corresponde el valor, y eso es lo que faltaba.
 *
 * Dos fechas, con dos significados que no conviene mezclar:
 *
 *  - `dias_mora_actualizado_en`: el día (en el calendario del mandante) al que
 *    corresponde el valor de `dias_mora`. La mueven las importaciones al
 *    escribir el valor y el comando `cobranza:avanzar-dias-mora` cada día.
 *  - `dias_mora_confirmado_en`: la última vez que una FUENTE (un archivo del
 *    cliente, un alta manual) afirmó el valor. Sólo la escriben las fuentes,
 *    nunca el envejecimiento. Es lo que permite decir en pantalla «mora
 *    informada por el cliente el 2 de septiembre» y detectar cuentas que el
 *    cliente dejó de enviar y que el CRM sigue envejeciendo.
 *
 * Relleno del histórico: la fecha de la última importación completada que
 * escribió la fila (`importacion_filas.entidad_id` la enlaza), convertida al
 * calendario del mandante. No se usa `actualizada_en` porque es
 * ON UPDATE CURRENT_TIMESTAMP y la pisa cualquier UPDATE —el cron de tramos, sin
 * ir más lejos—; en la base local decía «7 de septiembre» para 8.372 cuentas
 * cargadas el día 2. Las filas sin importación enlazada (altas a mano) caen a
 * `creada_en`, que es cuando se afirmó su mora por primera vez.
 *
 * Las filas con `dias_mora` NULL se quedan sin fecha: no hay valor al que
 * ponerle fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casos_cobranza', function (Blueprint $table): void {
            $table->date('dias_mora_actualizado_en')->nullable()->after('dias_mora');
            $table->date('dias_mora_confirmado_en')->nullable()->after('dias_mora_actualizado_en');
            $table->index(['proyecto_id', 'dias_mora_actualizado_en'], 'casos_cobranza_proyecto_mora_actualizada_idx');
        });

        $this->rellenar();
    }

    /**
     * Pública y separada de `up()` a propósito: el test la ejecuta sobre las
     * columnas ya creadas. Correr `down()`/`up()` dentro de un test sería DDL en
     * mitad de la transacción de RefreshDatabase, y en MySQL eso hace COMMIT
     * implícito: los datos del test se quedaban en la base y contaminaban a los
     * que venían detrás.
     */
    public function rellenar(): void
    {
        foreach (DB::table('mandantes')->get(['id', 'zona_horaria']) as $mandante) {
            $proyectos = DB::table('proyectos')
                ->where('mandante_id', $mandante->id)
                ->pluck('id')
                ->map(fn (mixed $v): int => (int) $v)
                ->all();

            if ($proyectos === []) {
                continue;
            }

            $minutos = $this->desfaseEnMinutos((string) ($mandante->zona_horaria ?: 'UTC'));
            $in = implode(',', $proyectos);

            // 1 · La última importación completada que tocó el caso.
            DB::statement(<<<SQL
                UPDATE casos_cobranza cc
                JOIN (
                    SELECT f.entidad_id AS caso_id,
                           MAX(COALESCE(i.terminado_en, i.actualizada_en, i.creada_en)) AS ultima
                    FROM importacion_filas f
                    JOIN importaciones i ON i.id = f.importacion_id
                    WHERE i.proyecto_id IN ({$in})
                      AND i.estado = 'completada'
                      AND i.tipo_entidad LIKE 'caso%'
                      AND f.estado = 'procesada'
                      AND f.entidad_id IS NOT NULL
                    GROUP BY f.entidad_id
                ) u ON u.caso_id = cc.caso_id
                SET cc.dias_mora_actualizado_en = DATE(DATE_ADD(u.ultima, INTERVAL ? MINUTE)),
                    cc.dias_mora_confirmado_en  = DATE(DATE_ADD(u.ultima, INTERVAL ? MINUTE)),
                    cc.actualizada_en = cc.actualizada_en
                WHERE cc.proyecto_id IN ({$in})
                  AND cc.dias_mora IS NOT NULL
                  AND cc.dias_mora_actualizado_en IS NULL
            SQL, [$minutos, $minutos]);

            // 2 · Lo que no vino por importación: el alta es la primera vez que
            //     alguien afirmó su mora.
            DB::statement(<<<SQL
                UPDATE casos_cobranza cc
                SET cc.dias_mora_actualizado_en = DATE(DATE_ADD(cc.creada_en, INTERVAL ? MINUTE)),
                    cc.dias_mora_confirmado_en  = DATE(DATE_ADD(cc.creada_en, INTERVAL ? MINUTE)),
                    cc.actualizada_en = cc.actualizada_en
                WHERE cc.proyecto_id IN ({$in})
                  AND cc.dias_mora IS NOT NULL
                  AND cc.dias_mora_actualizado_en IS NULL
            SQL, [$minutos, $minutos]);
        }
    }

    public function down(): void
    {
        Schema::table('casos_cobranza', function (Blueprint $table): void {
            $table->dropIndex('casos_cobranza_proyecto_mora_actualizada_idx');
            $table->dropColumn(['dias_mora_actualizado_en', 'dias_mora_confirmado_en']);
        });
    }

    /**
     * El desfase actual de la zona, en minutos, para convertir un instante UTC
     * a fecha de calendario del cliente en SQL. Es el desfase de HOY: en zonas
     * con horario de verano un instante cerca de la medianoche de otra estación
     * podría caer un día al lado, y para un relleno histórico es asumible.
     */
    private function desfaseEnMinutos(string $zona): int
    {
        try {
            $tz = new CarbonTimeZone($zona);
        } catch (Throwable) {
            return 0;
        }

        return intdiv($tz->getOffset(Carbon::now('UTC')), 60);
    }
};
