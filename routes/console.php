<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cierra el ciclo del compromiso (§6): lo que venció ayer y sigue pendiente pasa
// a roto. Sin periodo de gracia, por decisión de negocio. Va ANTES de las
// notificaciones del día a propósito: así el gestor abre la jornada con el aviso
// de «compromiso roto, hay que llamar» ya generado, en vez de con el de «vencido
// sin resolver», que describía un estado que ahora dura horas y no meses.
Schedule::command('compromisos:romper-vencidos')
    ->dailyAt('07:45')
    ->withoutOverlapping()
    ->name('compromisos-romper-vencidos');

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

Schedule::command('integracion:purgar-sso-consumidos')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->name('integracion-purgar-sso-consumidos');

// Importaciones subidas y nunca lanzadas (pendiente/preparada) más viejas de 7 días.
Schedule::command('importaciones:purgar-obsoletas --dias=7')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->name('importaciones-purgar-obsoletas');

// Envejece los días de mora hasta el «hoy» de cada cliente y, después,
// reclasifica la cartera en sus tramos. Cada hora y no a una hora fija: el
// scheduler corre en UTC y cada mandante tiene su propia medianoche —a las
// 03:50 UTC en Panamá todavía es ayer, y con una hora fija la mora habría ido
// un día atrasada durante toda la jornada—. El avance es no-op 23 de las 24
// veces, porque el ancla de cada cuenta ya es «hoy» del mandante y sólo escribe
// cuando hay días que sumar; la hora en la que un cliente cruza la medianoche
// es la única que hace trabajo.
//
// El avance va ANTES que los tramos, con quince minutos de margen: los tramos
// se calculan sobre `dias_mora`, y reclasificar primero dejaría la cartera
// clasificada con la mora de ayer hasta la hora siguiente. Los dos son
// idempotentes; si nada cambió no escriben nada.
Schedule::command('cobranza:avanzar-dias-mora')
    ->hourlyAt(10)
    ->withoutOverlapping()
    ->name('cobranza-avanzar-dias-mora');

Schedule::command('cobranza:asignar-tramos-mora')
    ->hourlyAt(25)
    ->withoutOverlapping()
    ->name('cobranza-asignar-tramos-mora');
