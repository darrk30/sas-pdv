<?php

use App\Enums\EstadoSunat;
use App\Enums\TipoComprobante;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Alertar créditos vencidos cada mañana
Schedule::command('creditos:alertar-vencidos')->dailyAt('08:00');

// Notificar gastos fijos que vencen pronto
Schedule::command('gastos-fijos:notificar')->dailyAt('08:05');

// Backup automático diario a las 2am + limpieza de backups viejos
Schedule::command('backup:run')->dailyAt('02:00')->onFailure(fn () => null);
Schedule::command('backup:clean')->dailyAt('01:00')->onFailure(fn () => null);

// Cargar tareas programadas desde la base de datos
if (Schema::hasTable('tareas_programadas')) {
    \App\Models\TareaProgramada::where('activo', true)->get()->each(function ($tarea) {
        Schedule::call(fn () => $tarea->ejecutar())->dailyAt($tarea->hora);
    });
}

// Reenvío automático de facturas PorEnviar — cada 30 min
// Detecta empresas con facturas pendientes y encola un job por empresa
// La cola 'pdv-fe-retry' debe tener numprocs=1 en supervisor para procesar de a una empresa a la vez
if (Schema::hasTable('ventas')) {
    Schedule::call(function () {
        $empresaIds = \App\Models\Venta::where('estado_sunat', \App\Enums\EstadoSunat::PorEnviar)
            ->whereHas('serie', fn ($q) => $q->where('tipo', \App\Enums\TipoComprobante::Factura))
            ->where('fecha_emision', '>=', now()->subDays(3))
            ->distinct()
            ->pluck('empresa_id');

        foreach ($empresaIds as $empresaId) {
            \App\Jobs\ReenviarFacturasEmpresaJob::dispatch($empresaId)->onQueue('pdv-fe-retry');
        }
    })->everyThirtyMinutes()->name('pdv-reenviar-facturas')->withoutOverlapping();
}

// Tareas programadas PDV: despacha al queue (no ejecuta directo)
// → múltiples empresas a la misma hora se encolan y procesan de a una, sin saturar el servidor
if (Schema::hasTable('tareas_programadas_pdv')) {
    \App\Models\TareaProgramadaPdv::where('activo', true)->get()->each(function ($tarea) {
        $cron = $tarea->getCronExpression();
        Schedule::call(function () use ($tarea) {
            \App\Jobs\ProcesarRcTareaJob::dispatch($tarea->id);
        })->cron($cron)->name("pdv-rc-empresa-{$tarea->empresa_id}")->withoutOverlapping();
    });
}
