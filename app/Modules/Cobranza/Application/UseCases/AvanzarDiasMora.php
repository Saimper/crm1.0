<?php

declare(strict_types=1);

namespace App\Modules\Cobranza\Application\UseCases;

use App\Modules\Cobranza\Application\DTOs\AvanzarDiasMoraInput;
use App\Modules\Cobranza\Application\DTOs\AvanzarDiasMoraOutput;
use App\Modules\Cobranza\Domain\ValueObjects\DiasMora;
use App\Modules\Tenancy\Application\Services\RelojDelMandante;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use stdClass;

/**
 * Envejece `dias_mora` hasta el «hoy» de cada cliente.
 *
 * `casos_cobranza.dias_mora` era la foto del archivo importado y nadie la
 * movía: el cron de tramos reclasificaba la cartera sobre un número muerto
 * —4.729 cuentas con la mora de hace 109 días—. `fecha_vencimiento` está en
 * NULL en toda la cartera real, así que la mora no se puede derivar de una
 * fecha: hay que envejecer el número a partir del día al que corresponde, que
 * es `dias_mora_actualizado_en` (el ancla).
 *
 * Reglas, en el orden en que descartan filas:
 *
 *  - Sólo proyectos de cobranza, y cada uno con el «hoy» de SU mandante
 *    (`RelojDelMandante::hoy`): a las 03:50 UTC en Panamá todavía es ayer.
 *  - Sólo casos abiertos (`cerrado_en` y `eliminada_en` nulos): una cuenta
 *    cerrada no envejece.
 *  - Sólo `dias_mora > 0`. Una cuenta al día sigue al día: sin fecha de
 *    vencimiento no se sabe cuándo entraría en mora, y sólo el siguiente
 *    archivo del cliente puede decir otra cosa.
 *  - Sólo con ancla y con ancla anterior a hoy. Sin ancla no se sabe a qué
 *    día corresponde el valor, y se cuenta en `sinFecha`. Con ancla de hoy es
 *    no-op: por eso el comando puede correr cada hora.
 *  - Sólo si el resultado cabe en `DiasMora::MAXIMO_RAZONABLE`: las que se
 *    pasarían se dejan como están y se cuentan en `enTope`, porque el VO se
 *    negaría a hidratarlas y la Vista de Trabajo del caso reventaría.
 *
 * El avance mueve SÓLO el ancla. `dias_mora_confirmado_en` es de las fuentes,
 * y es lo que permite avisar de las cuentas que el cliente dejó de enviar
 * (`sinConfirmarHaceTiempo`): no hay importación «foto» que cierre lo que ya
 * no viene en el archivo, así que el CRM las seguiría envejeciendo para siempre.
 *
 * Quién escribe `dias_mora` y qué hace con las dos fechas (lista completa;
 * si aparece un escritor nuevo, entra aquí):
 *
 *  - `EloquentCasoCobranzaRepository::save()` (alta y edición por dominio):
 *    cuando `dias_mora` cambia, las dos fechas = hoy del mandante.
 *  - `ProcesarFilaDinamica` (Importaciones, camino asíncrono): cuando
 *    `dias_mora` entra en el UPDATE del caso, valida con el VO y fija las dos
 *    fechas = hoy del mandante.
 *  - `RescatarCamposNativosCommand::rescatarACobranza`: las dos fechas = la
 *    fecha (en la zona del mandante) del `creada_en` del valor copiado, que es
 *    cuando aquel archivo lo afirmó; nunca hoy.
 *  - La migración de relleno histórico: fecha de la última importación
 *    completada que escribió la fila, o el alta.
 *  - Este UseCase: sólo `dias_mora_actualizado_en`.
 *
 * La lista es cerrada y hay que mantenerla así: una fuente nueva que escriba
 * `dias_mora` sin ancla deja la cuenta congelada en el día de su carga, y no
 * se nota hasta que el gestor llama con la cifra de hace tres semanas.
 */
final class AvanzarDiasMora
{
    /** El índice por el que se busca lo que hay que avanzar. */
    public const INDICE_AVANCE = 'casos_cobranza_proyecto_mora_actualizada_idx';

    /** @var bool|null Si el índice existe. Se pregunta una vez por pasada, no por proyecto. */
    private ?bool $hayIndice = null;

    /**
     * Filas por transacción. Rangos de PK cortos para no retener bloqueos
     * sobre `casos_cobranza` mientras un job de importación escribe en ella.
     */
    private const FILAS_POR_LOTE = 2000;

    /**
     * UNA sola tabla, sin JOIN: en un UPDATE multi-tabla MySQL no garantiza el
     * orden de las asignaciones, y si el ancla se asignara antes que la mora
     * DATEDIFF daría 0 en silencio. En uno de una sola tabla se evalúan de
     * izquierda a derecha, así que la mora se calcula con el ancla vieja y
     * después se mueve el ancla. `actualizada_en = actualizada_en` evita que el
     * ON UPDATE CURRENT_TIMESTAMP la pise: esa columna ya no significaba nada
     * porque la pisaba cualquier UPDATE, y dejarla quieta es lo menos malo.
     *
     * `CAST(... AS SIGNED)` en el WHERE porque `dias_mora` es unsigned y MySQL
     * no garantiza en qué orden evalúa las condiciones: si una fila tuviera el
     * ancla por delante de hoy —un mandante que cambió de huso hacia el
     * oeste— la suma daría negativo y la sentencia entera fallaría por rango.
     */
    private const SQL_AVANCE = <<<'SQL'
        UPDATE casos_cobranza
        SET dias_mora = dias_mora + DATEDIFF(?, dias_mora_actualizado_en),
            dias_mora_actualizado_en = ?,
            actualizada_en = actualizada_en
        WHERE proyecto_id = ?
          AND caso_id BETWEEN ? AND ?
          AND dias_mora > 0
          AND dias_mora_actualizado_en IS NOT NULL
          AND dias_mora_actualizado_en < ?
          AND CAST(dias_mora AS SIGNED) + DATEDIFF(?, dias_mora_actualizado_en) <= ?
          AND caso_id IN (
              SELECT id FROM casos
              WHERE proyecto_id = ? AND cerrado_en IS NULL AND eliminada_en IS NULL
          )
        SQL;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly RelojDelMandante $reloj,
    ) {}

    public function execute(AvanzarDiasMoraInput $input): AvanzarDiasMoraOutput
    {
        $avanzados = [];
        $enTope = [];
        $sinFecha = [];
        $sinConfirmar = [];

        // El «hoy» de cada mandante se fija una vez por pasada: si la pasada
        // cruza la medianoche de un cliente, todos sus proyectos ven el mismo día.
        $hoyPorMandante = [];

        foreach ($this->proyectosDeCobranza($input->proyectoId) as $proyecto) {
            $proyectoId = (int) $proyecto->id;
            $mandanteId = (int) $proyecto->mandante_id;
            $hoy = $hoyPorMandante[$mandanteId] ??= $this->reloj->hoy($mandanteId);

            $sinFecha[$proyectoId] = $this->conMoraAbierta($proyectoId)
                ->whereNull('dias_mora_actualizado_en')
                ->count();

            $enTope[$proyectoId] = $this->atrasadas($proyectoId, $hoy)
                ->whereRaw('CAST(dias_mora AS SIGNED) + DATEDIFF(?, dias_mora_actualizado_en) > ?', [$hoy, DiasMora::MAXIMO_RAZONABLE])
                ->count();

            $sinConfirmar[$proyectoId] = $this->conMoraAbierta($proyectoId)
                ->where('dias_mora_confirmado_en', '<', $this->limiteSinConfirmar($hoy))
                ->count();

            $avanzados[$proyectoId] = $input->simulacro
                ? $this->soloDeCasosAbiertos($this->avanzables($proyectoId, $hoy), $proyectoId)->count()
                : $this->avanzar($proyectoId, $hoy);
        }

        return new AvanzarDiasMoraOutput(
            avanzadosPorProyecto: $avanzados,
            enTope: array_sum($enTope),
            sinFecha: array_sum($sinFecha),
            sinConfirmarHaceTiempo: array_sum($sinConfirmar),
            enTopePorProyecto: $enTope,
            sinFechaPorProyecto: $sinFecha,
            sinConfirmarPorProyecto: $sinConfirmar,
        );
    }

    private function avanzar(int $proyectoId, string $hoy): int
    {
        // Los ids se toman de una vez y ordenados: cada lote es un rango de PK
        // cerrado, y las condiciones se repiten en el UPDATE para que una fila
        // del rango que no cumpla (cerrada, al día, sin ancla) no se toque.
        $ids = $this->avanzables($proyectoId, $hoy)->orderBy('caso_id')->pluck('caso_id');
        $total = 0;

        foreach ($ids->chunk(self::FILAS_POR_LOTE) as $lote) {
            $desde = (int) $lote->first();
            $hasta = (int) $lote->last();

            $total += (int) $this->db->transaction(fn (): int => $this->db->update(self::SQL_AVANCE, [
                $hoy, $hoy,
                $proyectoId, $desde, $hasta,
                $hoy,
                $hoy, DiasMora::MAXIMO_RAZONABLE,
                $proyectoId,
            ]));
        }

        return $total;
    }

    /**
     * Cuentas en mora de casos abiertos: la población de la que hablan todos
     * los contadores. Recortada por proyecto antes que nada (§10).
     *
     * La usan los tres contadores del informe, no el avance: ésos sí quieren
     * decir «de los casos abiertos», y son tres COUNT por proyecto y pasada.
     */
    private function conMoraAbierta(int $proyectoId): Builder
    {
        return $this->db->table('casos_cobranza')
            ->where('proyecto_id', $proyectoId)
            ->where('dias_mora', '>', 0)
            ->whereIn('caso_id', fn (Builder $q) => $q
                ->select('id')
                ->from('casos')
                ->where('proyecto_id', $proyectoId)
                ->whereNull('cerrado_en')
                ->whereNull('eliminada_en'));
    }

    /** Las que tienen ancla y el ancla se quedó atrás respecto al hoy del cliente. */
    private function atrasadas(int $proyectoId, string $hoy): Builder
    {
        return $this->conMoraAbierta($proyectoId)
            ->whereNotNull('dias_mora_actualizado_en')
            ->where('dias_mora_actualizado_en', '<', $hoy);
    }

    /**
     * Lo que hay que avanzar: una sola tabla, sin el join contra `casos`.
     *
     * Es la consulta de la ruta caliente —corre cada hora, por proyecto— y por
     * eso no lleva el filtro de «caso abierto» aunque le importe: con el join,
     * el optimizador arranca por `casos` y el coste pasa a ser el tamaño de la
     * cartera entera, con tabla temporal y filesort, incluso en las 23 horas en
     * las que no hay una sola cuenta que avanzar. Sin él es un rango del índice
     * `(proyecto_id, dias_mora_actualizado_en)`: si nada venció, el rango está
     * vacío y la pasada no lee nada.
     *
     * Quedarse corto no es un riesgo: el UPDATE repite las condiciones y añade
     * la de caso abierto, así que un caso cerrado que entre en el rango de PK
     * no se toca. Lo único que se paga es un puñado de ids de más en el lote.
     * El simulacro, que sí informa números al usuario, compone encima
     * `soloDeCasosAbiertos`.
     *
     * Pública para que el test que hace EXPLAIN mire exactamente la consulta
     * que corre cada hora, y no una escrita a mano que se parezca.
     */
    public function avanzables(int $proyectoId, string $hoy): Builder
    {
        $tabla = $this->db->table('casos_cobranza');

        if ($this->hayIndiceDeAvance()) {
            $tabla->forceIndex(self::INDICE_AVANCE);
        }

        return $tabla
            ->where('proyecto_id', $proyectoId)
            ->where('dias_mora', '>', 0)
            ->whereNotNull('dias_mora_actualizado_en')
            ->where('dias_mora_actualizado_en', '<', $hoy)
            ->whereRaw('CAST(dias_mora AS SIGNED) + DATEDIFF(?, dias_mora_actualizado_en) <= ?', [$hoy, DiasMora::MAXIMO_RAZONABLE]);
    }

    /** El filtro de caso abierto, para cuando el número se le enseña a alguien. */
    private function soloDeCasosAbiertos(Builder $q, int $proyectoId): Builder
    {
        return $q->whereIn('caso_id', fn (Builder $sub) => $sub
            ->select('id')
            ->from('casos')
            ->where('proyecto_id', $proyectoId)
            ->whereNull('cerrado_en')
            ->whereNull('eliminada_en'));
    }

    /**
     * Una base restaurada de un dump anterior a la migración del índice no lo
     * tiene, y forzar uno que no existe es un error de MySQL: la tarea moriría
     * en vez de ir más lenta.
     */
    private function hayIndiceDeAvance(): bool
    {
        return $this->hayIndice ??= Schema::hasIndex('casos_cobranza', self::INDICE_AVANCE);
    }

    private function limiteSinConfirmar(string $hoy): string
    {
        return (new DateTimeImmutable($hoy))
            ->modify('-'.DiasMora::DIAS_SIN_CONFIRMAR_AVISO.' days')
            ->format('Y-m-d');
    }

    /**
     * Es una tarea de plataforma: recorrer todos los proyectos de cobranza es
     * la intención, y se escribe con `DB::table` acotando por proyecto en cada
     * consulta en vez de apoyarse en que fuera de HTTP el scope no filtra.
     *
     * @return Collection<int, stdClass>
     */
    private function proyectosDeCobranza(?int $proyectoId): Collection
    {
        return $this->db->table('proyectos')
            ->where('tipo_operacion', 'cobranza')
            ->when($proyectoId !== null, fn (Builder $q) => $q->where('id', $proyectoId))
            ->orderBy('mandante_id')
            ->orderBy('id')
            ->get(['id', 'mandante_id']);
    }
}
