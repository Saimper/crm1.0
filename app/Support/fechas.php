<?php

declare(strict_types=1);

use App\Modules\Tenancy\Application\Services\RelojDelMandante;
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
    function hora_local(mixed $instante, string $formato = 'd/m/Y H:i'): string
    {
        if ($instante === null || $instante === '') {
            return '—';
        }

        $local = app(RelojDelMandante::class)->enZona(
            $instante instanceof Carbon ? $instante : Carbon::parse((string) $instante, 'UTC')
        );

        return $local?->format($formato) ?? '—';
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
