<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\Services;

use App\Modules\Tenancy\Domain\Contracts\RegionalConfiguration;
use App\Modules\Tenancy\Domain\ValueObjects\RegionalSettings;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El reloj y la moneda del cliente, para no cortar el día donde no toca.
 *
 * Todo se guarda en UTC y así se queda: los valores de `creada_en` son instantes
 * correctos y `app.timezone` sigue en UTC, porque cambiarlo por petición haría
 * que Eloquent reinterpretara lo ya escrito como hora local. La conversión va
 * sólo en los dos bordes: cuando se muestra una fecha y cuando se corta un rango.
 *
 * Por qué importa: con la operación en Panamá (UTC−5) y el corte en UTC, el día
 * termina a las 19:00 hora local. Todo lo gestionado a partir de esa hora —el
 * último tramo de cada turno de tarde— se contabilizaba al día siguiente, y el
 * informe diario del supervisor nunca cuadraba con lo que había visto pasar.
 *
 * RegionalConfiguration caches inherited settings within the request/job.
 * An explicit project ID also applies project overrides in console operations.
 */
final class RelojDelMandante
{
    public function zonaDe(?int $mandanteId): string
    {
        return app(RegionalConfiguration::class)->forMandante($mandanteId)->timezone;
    }

    public function monedaDe(?int $mandanteId): string
    {
        return app(RegionalConfiguration::class)->forMandante($mandanteId)->currency;
    }

    public function localeDe(?int $mandanteId): string
    {
        return (string) (DB::table('mandantes')->where('id', $mandanteId)->value('locale') ?? config('app.locale', 'es'));
    }

    public function inicioSemanaDe(?int $mandanteId): int
    {
        return app(RegionalConfiguration::class)->forMandante($mandanteId)->weekStartsOn;
    }

    /** La zona del mandante activo, o la de la plataforma si no hay ninguno. */
    public function zonaActiva(): string
    {
        return $this->settings()->timezone;
    }

    public function monedaActiva(): string
    {
        return $this->settings()->currency;
    }

    /** Un instante UTC, expresado en la hora del cliente. Para mostrar. */
    public function enZona(DateTimeInterface|string|null $instante, ?int $mandanteId = null, ?int $proyectoId = null): ?Carbon
    {
        if ($instante === null) {
            return null;
        }

        $carbon = $instante instanceof DateTimeInterface ? Carbon::instance($instante) : Carbon::parse($instante, 'UTC');

        return $carbon->setTimezone($this->settings($mandanteId, $proyectoId)->timezone);
    }

    /**
     * El inicio de un rango operativo, ya convertido a UTC para la consulta.
     *
     * Se construye en la zona del cliente y se devuelve en UTC porque es lo que
     * hay en la base: «hoy» para una operación en Panamá empieza a las 00:00
     * locales, que son las 05:00 UTC, no a las 00:00 UTC.
     *
     * @param  'hoy'|'ayer'|'semana'|'mes'  $rango
     */
    public function inicioDe(string $rango, ?int $mandanteId = null, ?int $proyectoId = null): Carbon
    {
        $zona = $this->settings($mandanteId, $proyectoId)->timezone;
        $ahora = Carbon::now($zona);

        $inicio = match ($rango) {
            'ayer' => $ahora->copy()->subDay()->startOfDay(),
            'semana' => $ahora->copy()->startOfWeek($this->settings($mandanteId, $proyectoId)->weekStartsOn),
            'mes' => $ahora->copy()->startOfMonth(),
            default => $ahora->copy()->startOfDay(),
        };

        return $inicio->setTimezone('UTC');
    }

    /** El fin del rango, en UTC. Exclusivo por arriba lo decide quien consulta. */
    public function finDe(string $rango, ?int $mandanteId = null, ?int $proyectoId = null): Carbon
    {
        $zona = $this->settings($mandanteId, $proyectoId)->timezone;
        $ahora = Carbon::now($zona);

        $fin = match ($rango) {
            'ayer' => $ahora->copy()->subDay()->endOfDay(),
            default => $ahora->copy()->endOfDay(),
        };

        return $fin->setTimezone('UTC');
    }

    /**
     * Un rango operativo completo —inicio y fin— por su nombre, en UTC.
     *
     * Es la semántica que tenía `DashboardOperativo::rangoActual`, sacada de
     * ahí para que la exportación de gestiones use EXACTAMENTE el mismo corte
     * que la pantalla desde la que se pide: «semana» son los últimos siete días
     * incluido hoy (no la semana natural de `inicioDe`, que arranca el lunes o
     * el domingo según el cliente) y «mes» es el mes en curso hasta hoy. Si la
     * pantalla y el CSV calcularan cada uno lo suyo, el supervisor vería 40
     * gestiones y descargaría 38.
     *
     * @param  'hoy'|'ayer'|'semana'|'mes'|string  $clave  Cualquier otra cosa cuenta como «hoy».
     * @return array{desde: Carbon, hasta: Carbon}
     */
    public function rangoPreestablecido(string $clave, ?int $mandanteId = null, ?int $proyectoId = null): array
    {
        $ahora = Carbon::now($this->settings($mandanteId, $proyectoId)->timezone);

        [$desde, $hasta] = match ($clave) {
            'ayer' => [$ahora->copy()->subDay()->startOfDay(), $ahora->copy()->subDay()->endOfDay()],
            'semana' => [$ahora->copy()->subDays(6)->startOfDay(), $ahora->copy()->endOfDay()],
            'mes' => [$ahora->copy()->startOfMonth(), $ahora->copy()->endOfDay()],
            default => [$ahora->copy()->startOfDay(), $ahora->copy()->endOfDay()],
        };

        return ['desde' => $desde->setTimezone('UTC'), 'hasta' => $hasta->setTimezone('UTC')];
    }

    /**
     * Dos fechas de calendario del cliente, como rango de instantes UTC: desde
     * las 00:00 del primer día hasta las 23:59:59 del último, en su zona.
     *
     * Las fechas llegan sin hora («2026-09-07») y son del calendario de quien
     * opera: para una operación en Panamá el 7 empieza a las 05:00 UTC y
     * termina a las 04:59:59 UTC del 8. Cortar en UTC dejaría fuera las
     * gestiones de la tarde del último día y metería las de la madrugada
     * siguiente.
     *
     * @param  string  $desde  'Y-m-d'
     * @param  string  $hasta  'Y-m-d'
     * @return array{desde: Carbon, hasta: Carbon}
     */
    public function rangoDeFechas(string $desde, string $hasta, ?int $mandanteId = null, ?int $proyectoId = null): array
    {
        $zona = $this->settings($mandanteId, $proyectoId)->timezone;

        return [
            'desde' => Carbon::parse($desde, $zona)->startOfDay()->setTimezone('UTC'),
            'hasta' => Carbon::parse($hasta, $zona)->endOfDay()->setTimezone('UTC'),
        ];
    }

    /**
     * La fecha de hoy en el calendario del cliente, como 'Y-m-d'.
     *
     * Es lo que hay que comparar contra las columnas `date` —vencimientos, fecha
     * de resolución—, que no llevan hora y por tanto están en el calendario de
     * quien opera, no en UTC.
     */
    public function hoy(?int $mandanteId = null, ?int $proyectoId = null): string
    {
        return Carbon::now($this->settings($mandanteId, $proyectoId)->timezone)->toDateString();
    }

    /**
     * El mandante en cuyo huso se está trabajando, si hay alguno.
     *
     * Se lee con `??` y no con acceso directo a propósito: los bindings de
     * tenancy los publica el middleware con la fila completa, pero también los
     * monta código de prueba y de consola con objetos parciales. Un reloj que
     * revienta porque le falta una propiedad deja sin pintar la pantalla entera
     * por un dato que tiene un valor por defecto perfectamente válido.
     */
    private function settings(?int $mandanteId = null, ?int $proyectoId = null): RegionalSettings
    {
        $configuration = app(RegionalConfiguration::class);

        return $proyectoId !== null || $mandanteId === null
            ? $configuration->forProject($proyectoId)
            : $configuration->forMandante($mandanteId);
    }
}
