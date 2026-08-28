<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios cada 5 minutos (requiere que el cron ejecute schedule:run cada minuto)
Schedule::command('citas:enviar-recordatorios')->everyFiveMinutes();
