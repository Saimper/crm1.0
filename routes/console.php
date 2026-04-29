<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Notificaciones: diario 08:00 (compromisos) y hourly en horario laboral (SLA CX).
Schedule::command('notificaciones:generar --umbral=3 --horas-sla=8')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->name('notificaciones-compromisos-diario');

Schedule::command('notificaciones:generar --umbral=0 --horas-sla=4')
    ->hourlyAt(5)
    ->between('07:00', '20:00')
    ->withoutOverlapping()
    ->name('notificaciones-sla-horario');
