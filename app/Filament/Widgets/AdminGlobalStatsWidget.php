<?php

namespace App\Filament\Widgets;

use App\Models\Empresa;
use App\Models\Suscripcion;
use App\Models\Venta;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AdminGlobalStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = true;

    protected static ?int $sort = 1;

    protected int|array|null $columns = [
        'default' => 2,
        'sm'      => 2,
        'lg'      => 4,
    ];

    protected function getStats(): array
    {
        $activas = Empresa::where('estado', 'activo')
            ->whereHas('suscripcion', fn ($q) => $q->where('es_prueba_gratuita', false))
            ->count();

        $trials = Empresa::where('estado', 'activo')
            ->whereHas('suscripcion', fn ($q) => $q->where('es_prueba_gratuita', true))
            ->count();

        $inactivas = Empresa::where('estado', 'inactivo')->count();

        $ventasMes = Venta::where('estado', 'completada')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->selectRaw('COUNT(*) as total_ventas, COALESCE(SUM(total), 0) as monto')
            ->first();

        return [
            Stat::make('Empresas activas', number_format($activas))
                ->description('Con suscripción paga')
                ->descriptionIcon('heroicon-o-building-office-2')
                ->color('success')
                ->icon('heroicon-o-building-office-2'),

            Stat::make('En prueba gratuita', number_format($trials))
                ->description('Trials activos')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('Ventas del mes', number_format($ventasMes->total_ventas ?? 0))
                ->description('En toda la plataforma — ' . now()->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-o-shopping-bag')
                ->color('primary')
                ->icon('heroicon-o-shopping-bag'),

            Stat::make('Monto del mes', 'S/ ' . number_format($ventasMes->monto ?? 0, 2))
                ->description('Facturado total — ' . now()->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('primary')
                ->icon('heroicon-o-banknotes'),
        ];
    }
}
