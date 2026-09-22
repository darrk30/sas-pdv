<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Alertar créditos vencidos cada mañana
Schedule::command('creditos:alertar-vencidos')->dailyAt('08:00');

// Cargar tareas programadas desde la base de datos
if (Schema::hasTable('tareas_programadas')) {
    \App\Models\TareaProgramada::where('activo', true)->get()->each(function ($tarea) {
        Schedule::call(fn () => $tarea->ejecutar())->dailyAt($tarea->hora);
    });
}
