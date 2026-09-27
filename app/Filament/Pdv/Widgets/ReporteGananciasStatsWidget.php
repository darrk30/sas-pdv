<?php

namespace App\Filament\Pdv\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class ReporteGananciasStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|array|null $columns = ['default' => 2, 'sm' => 2, 'lg' => 4];

    #[Reactive]
    public array $statsData = [];

    #[Reactive]
    public array $sparksData = [];

    protected function getStats(): array
    {
        $r      = $this->statsData;
        $s      = $this->sparksData;
        $util   = $r['utilidadRealizada'] ?? 0;
        $margen = $r['margenPct'] ?? 0;

        $margenColor = match(true) {
            $margen >= 30 => 'success',
            $margen >= 10 => 'primary',
            $margen > 0   => 'warning',
            default       => 'danger',
        };

        $stats = [
            Stat::make('Ventas completadas', number_format($r['cantidad'] ?? 0))
                ->description('últimos 7 días en gráfica')
                ->descriptionIcon('heroicon-o-shopping-cart')
                ->color('primary')
                ->chart($s['ingresos'] ?? []),

            Stat::make('Utilidad cobrada', 'S/ ' . number_format($util, 2))
                ->description('costo: S/ ' . number_format($r['costoTotal'] ?? 0, 2))
                ->descriptionIcon($util >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($util >= 0 ? 'success' : 'danger')
                ->chart($s['utilidad'] ?? []),

            Stat::make('Margen bruto', number_format($margen, 1) . '%')
                ->description('neto: S/ ' . number_format($r['ventasNetas'] ?? 0, 2))
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color($margenColor)
                ->chart($s['ingresos'] ?? []),
        ];

        if (($r['creditoPendiente'] ?? 0) > 0) {
            $stats[] = Stat::make('Crédito pendiente', 'S/ ' . number_format($r['creditoPendiente'], 2))
                ->description('utilidad en riesgo: S/ ' . number_format($r['utilidadEnRiesgo'] ?? 0, 2))
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning');
        }

        return $stats;
    }
}
