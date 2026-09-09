<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\Services;

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
 * Cachea por mandante dentro de la petición: se llama una vez por fila pintada.
 */
final class RelojDelMandante
{
    /** @var array<int, object{zona_horaria: string, moneda: string, locale: string, inicio_semana: int}> */
    private array $cache = [];

    public function zonaDe(?int $mandanteId): string
    {
        return $this->config($mandanteId)->zona_horaria;
    }

    public function monedaDe(?int $mandanteId): string
    {
        return $this->config($mandanteId)->moneda;
    }

    public function localeDe(?int $mandanteId): string
    {
        return $this->config($mandanteId)->locale;
    }

    public function inicioSemanaDe(?int $mandanteId): int
    {
        return $this->config($mandanteId)->inicio_semana;
    }

    /** La zona del mandante activo, o la de la plataforma si no hay ninguno. */
    public function zonaActiva(): string
    {
        return $this->zonaDe($this->mandanteActivoId());
    }

    public function monedaActiva(): string
    {
        return $this->monedaDe($this->mandanteActivoId());
    }

    /** Un instante UTC, expresado en la hora del cliente. Para mostrar. */
    public function enZona(Carbon|string|null $instante, ?int $mandanteId = null): ?Carbon
    {
        if ($instante === null) {
            return null;
        }

        $carbon = $instante instanceof Carbon ? $instante->copy() : Carbon::parse($instante, 'UTC');

        return $carbon->setTimezone($this->zonaDe($mandanteId ?? $this->mandanteActivoId()));
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
    public function inicioDe(string $rango, ?int $mandanteId = null): Carbon
    {
        $zona = $this->zonaDe($mandanteId ?? $this->mandanteActivoId());
        $ahora = Carbon::now($zona);

        $inicio = match ($rango) {
            'ayer' => $ahora->copy()->subDay()->startOfDay(),
            'semana' => $ahora->copy()->startOfWeek($this->inicioSemanaDe($mandanteId ?? $this->mandanteActivoId())),
            'mes' => $ahora->copy()->startOfMonth(),
            default => $ahora->copy()->startOfDay(),
        };

        return $inicio->setTimezone('UTC');
    }

    /** El fin del rango, en UTC. Exclusivo por arriba lo decide quien consulta. */
    public function finDe(string $rango, ?int $mandanteId = null): Carbon
    {
        $zona = $this->zonaDe($mandanteId ?? $this->mandanteActivoId());
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
    public function rangoPreestablecido(string $clave, ?int $mandanteId = null): array
    {
        $ahora = Carbon::now($this->zonaDe($mandanteId ?? $this->mandanteActivoId()));

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
    public function rangoDeFechas(string $desde, string $hasta, ?int $mandanteId = null): array
    {
        $zona = $this->zonaDe($mandanteId ?? $this->mandanteActivoId());

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
    public function hoy(?int $mandanteId = null): string
    {
        return Carbon::now($this->zonaDe($mandanteId ?? $this->mandanteActivoId()))->toDateString();
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
    private function mandanteActivoId(): ?int
    {
        if (app()->bound('tenancy.mandante_activo')) {
            $id = app('tenancy.mandante_activo')->id ?? null;

            return $id !== null ? (int) $id : null;
        }

        if (app()->bound('tenancy.proyecto_activo')) {
            $id = app('tenancy.proyecto_activo')->mandante_id ?? null;

            return $id !== null ? (int) $id : null;
        }

        return null;
    }

    private function config(?int $mandanteId): object
    {
        if ($mandanteId === null) {
            return $this->porDefecto();
        }

        return $this->cache[$mandanteId] ??= $this->leer($mandanteId);
    }

    private function leer(int $mandanteId): object
    {
        $fila = DB::table('mandantes')
            ->where('id', $mandanteId)
            ->first(['zona_horaria', 'moneda', 'locale', 'inicio_semana']);

        if ($fila === null) {
            return $this->porDefecto();
        }

        return (object) [
            'zona_horaria' => (string) ($fila->zona_horaria ?: config('tenancy.zona_horaria_por_defecto')),
            'moneda' => (string) ($fila->moneda ?: config('tenancy.moneda_por_defecto')),
            'locale' => (string) ($fila->locale ?: config('app.locale')),
            'inicio_semana' => (int) ($fila->inicio_semana ?: 1),
        ];
    }

    private function porDefecto(): object
    {
        return (object) [
            'zona_horaria' => (string) config('tenancy.zona_horaria_por_defecto', 'UTC'),
            'moneda' => (string) config('tenancy.moneda_por_defecto', 'USD'),
            'locale' => (string) config('app.locale', 'es'),
            'inicio_semana' => 1,
        ];
    }
}
