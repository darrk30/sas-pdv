<?php

namespace App\Filament\Pdv\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class ReporteClientesStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|array|null $columns = ['default' => 2, 'sm' => 2, 'lg' => 4];

    #[Reactive]
    public array $statsData = [];

    protected function getStats(): array
    {
        $r              = $this->statsData;
        $totalClientes  = $r['totalClientes'] ?? 0;
        $totalCompras   = $r['totalCompras'] ?? 0;
        $totalGastado   = $r['totalGastado'] ?? 0;
        $comprasPorC    = $totalClientes > 0 ? round($totalCompras / $totalClientes, 1) : 0;
        $promedioPorC   = $totalClientes > 0 ? round($totalGastado / $totalClientes, 2) : 0;

        $stats = [
            Stat::make('Clientes', number_format($totalClientes))
                ->description('identificados en el período')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('primary'),

            Stat::make('Compras', number_format($totalCompras))
                ->description(number_format($comprasPorC, 1) . ' compras / cliente')
                ->descriptionIcon('heroicon-o-shopping-bag')
                ->color('gray'),

            Stat::make('Total facturado', 'S/ ' . number_format($totalGastado, 2))
                ->description('S/ ' . number_format($promedioPorC, 2) . ' prom. / cliente')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),
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
