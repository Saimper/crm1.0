<?php

declare(strict_types=1);

use App\Modules\Tenancy\Application\Services\RelojDelMandante;
use App\Modules\Tenancy\Domain\Contracts\RegionalConfiguration;
use Illuminate\Support\Carbon;

if (! function_exists('hora_local')) {
    /**
     * Un instante guardado en UTC, mostrado en la hora del cliente.
     *
     * Sólo para columnas con hora —`creada_en`, `actualizada_en`—. Las columnas
     * `date` (fecha_vencimiento, fecha_nacimiento, fecha_resolucion) NO pasan por
     * aquí: son fechas de calendario sin hora, y convertirlas de huso las
     * desplazaría un día a poco que el desfase apunte en la dirección mala.
     *
     * Con la operación en Panamá y todo guardado en UTC, cada hora que veía el
     * gestor iba cinco adelantada: una gestión de las 18:07 se leía «23:07», y
     * una de las 20:30 aparecía fechada al día siguiente.
     */
    function hora_local(mixed $instante, ?string $formato = null, ?int $proyectoId = null): string
    {
        if ($instante === null || $instante === '') {
            return '—';
        }

        $settings = app(RegionalConfiguration::class)->forProject($proyectoId);
        $local = app(RelojDelMandante::class)->enZona(
            $instante instanceof DateTimeInterface ? $instante : (string) $instante,
            proyectoId: $proyectoId,
        );

        return $local?->format($formato ?? $settings->dateFormat.' H:i') ?? '—';
    }
}

if (! function_exists('hace_cuanto')) {
    /** «hace 3 horas», calculado contra el mismo instante en la zona del cliente. */
    function hace_cuanto(mixed $instante): string
    {
        if ($instante === null || $instante === '') {
            return '—';
        }

        $local = app(RelojDelMandante::class)->enZona(
            $instante instanceof Carbon ? $instante : Carbon::parse((string) $instante, 'UTC')
        );

        return $local?->diffForHumans() ?? '—';
    }
}

if (! function_exists('fecha_local')) {
    function fecha_local(mixed $date, ?int $projectId = null): string
    {
        return app(RegionalConfiguration::class)->forProject($projectId)->formatDate($date);
    }
}

if (! function_exists('numero_local')) {
    function numero_local(mixed $value, ?int $decimals = null, ?int $projectId = null): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return app(RegionalConfiguration::class)->forProject($projectId)->formatNumber((string) $value, $decimals);
    }
}

if (! function_exists('moneda_local')) {
    function moneda_local(?int $projectId = null): string
    {
        return app(RegionalConfiguration::class)->forProject($projectId)->currency;
    }
}
