<?php

namespace App\Filament\Widgets;

use App\Models\Empresa;
use App\Models\Suscripcion;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AdminAlertasWidget extends BaseWidget
{
    protected static bool $isDiscovered = true;

    protected static ?int $sort = 2;

    protected int|array|null $columns = [
        'default' => 1,
        'sm'      => 2,
        'lg'      => 3,
    ];

    protected function getStats(): array
    {
        // Trials que vencen en los próximos 7 días
        $trialsProximos = Suscripcion::where('es_prueba_gratuita', true)
            ->whereHas('empresa', fn ($q) => $q->where('estado', 'activo'))
            ->where('fecha_fin', '>=', now()->startOfDay())
            ->where('fecha_fin', '<=', now()->addDays(7)->endOfDay())
            ->count();

        // Suscripciones vencidas (pago, fecha_fin < hoy)
        $vencidas = Suscripcion::where('es_prueba_gratuita', false)
            ->whereHas('empresa', fn ($q) => $q->where('estado', 'activo'))
            ->where('fecha_fin', '<', now()->startOfDay())
            ->count();

        // Empresas activas sin ventas completadas este mes
        $mes  = now()->month;
        $anio = now()->year;

        $conVentasMes = DB::table('ventas')
            ->where('estado', 'completada')
            ->whereMonth('created_at', $mes)
            ->whereYear('created_at', $anio)
            ->distinct()
            ->pluck('empresa_id');

        $sinActividad = Empresa::where('estado', 'activo')
            ->whereNotIn('id', $conVentasMes)
            ->count();

        return [
            Stat::make('Trials por vencer', number_format($trialsProximos))
                ->description('Vencen en los próximos 7 días')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($trialsProximos > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),

            Stat::make('Suscripciones vencidas', number_format($vencidas))
                ->description('Empresa activa sin suscripción vigente')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color($vencidas > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-x-circle'),

            Stat::make('Sin ventas este mes', number_format($sinActividad))
                ->description('Activas sin actividad en ' . now()->translatedFormat('F'))
                ->descriptionIcon('heroicon-o-arrow-trending-down')
                ->color($sinActividad > 0 ? 'gray' : 'success')
                ->icon('heroicon-o-arrow-trending-down'),
        ];
    }
}
