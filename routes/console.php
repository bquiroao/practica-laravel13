<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Módulo alertas críticas y notificaciones internas: escalamiento por tiempo (RN-04, RN-05).
Schedule::command('nido:alertas-escalar')->everyMinute()->withoutOverlapping();
