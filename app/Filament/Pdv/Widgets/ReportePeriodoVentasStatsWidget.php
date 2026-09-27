<?php

namespace App\Filament\Pdv\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class ReportePeriodoVentasStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|array|null $columns = ['default' => 2, 'sm' => 2, 'lg' => 4];

    #[Reactive]
    public array $statsData = [];

    protected function getStats(): array
    {
        $r    = $this->statsData;
        $util = $r['utilidad'] ?? 0;

        $stats = [
            Stat::make('Ventas', number_format($r['cantidad'] ?? 0))
                ->description($r['periodoLabel'] ?? 'completadas')
                ->descriptionIcon('heroicon-o-shopping-cart')
                ->color('primary'),

            Stat::make('Cobrado', 'S/ ' . number_format($r['cobrado'] ?? 0, 2))
                ->description('monto recibido')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Utilidad', 'S/ ' . number_format($util, 2))
                ->description($util >= 0 ? 'beneficio neto' : 'pérdida neta')
                ->descriptionIcon($util >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($util >= 0 ? 'success' : 'danger'),
        ];

        if (($r['creditoPendiente'] ?? 0) > 0) {
            $stats[] = Stat::make('Crédito pendiente', 'S/ ' . number_format($r['creditoPendiente'], 2))
                ->description('por cobrar')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning');
        }

        return $stats;
    }
}
