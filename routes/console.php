<?php

use App\Console\Commands\ResetUsosPromocionesCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Resetear usos_actuales de promociones cada día a medianoche
Schedule::command(ResetUsosPromocionesCommand::class)->dailyAt('00:00');
