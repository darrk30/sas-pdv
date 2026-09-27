<?php

namespace App\Filament\Pdv\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class KardexStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|array|null $columns = ['default' => 2, 'sm' => 3];

    #[Reactive]
    public array $statsData = [];

    protected function getStats(): array
    {
        $total    = $this->statsData['total']    ?? 0;
        $entradas = $this->statsData['entradas'] ?? 0;
        $salidas  = $this->statsData['salidas']  ?? 0;

        return [
            Stat::make('Total', number_format($total))
                ->description('movimientos registrados')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('gray'),

            Stat::make('Entradas', number_format($entradas))
                ->description('ingresos de stock')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),

            Stat::make('Salidas', number_format($salidas))
                ->description('egresos de stock')
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('danger'),
        ];
    }
}
