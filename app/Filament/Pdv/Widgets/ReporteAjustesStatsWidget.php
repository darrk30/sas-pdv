<?php

namespace App\Filament\Pdv\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class ReporteAjustesStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|array|null $columns = ['default' => 2, 'sm' => 2, 'lg' => 4];

    #[Reactive]
    public array $statsData = [];

    protected function getStats(): array
    {
        $r = $this->statsData;

        return [
            Stat::make('Total ajustes', number_format($r['cantidad'] ?? 0))
                ->description('en el período')
                ->descriptionIcon('heroicon-o-wrench-screwdriver')
                ->color('primary'),

            Stat::make('Entradas', number_format($r['entradas'] ?? 0))
                ->description('incrementos de stock')
                ->descriptionIcon('heroicon-o-arrow-down-tray')
                ->color('success'),

            Stat::make('Salidas', number_format($r['salidas'] ?? 0))
                ->description('reducciones de stock')
                ->descriptionIcon('heroicon-o-arrow-up-tray')
                ->color('danger'),

            Stat::make('Valor total', 'S/ ' . number_format($r['valorTotal'] ?? 0, 2))
                ->description('costo ajustado')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('warning'),
        ];
    }
}
