<?php

namespace App\Filament\Pdv\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class ReporteVentasStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|array|null $columns = ['default' => 2, 'sm' => 2, 'lg' => 4];

    #[Reactive]
    public array $statsData = [];

    #[Reactive]
    public array $sparksData = [];

    protected function getStats(): array
    {
        $r = $this->statsData;
        $s = $this->sparksData;

        $stats = [
            Stat::make('Completadas', number_format($r['count'] ?? 0))
                ->description('ventas en el período')
                ->descriptionIcon('heroicon-o-shopping-cart')
                ->color('primary')
                ->chart($s['cantidad'] ?? []),

            Stat::make('Total cobrado', 'S/ ' . number_format($r['total'] ?? 0, 2))
                ->description(($r['descuentoTotal'] ?? 0) > 0
                    ? 'desc: − S/ ' . number_format($r['descuentoTotal'], 2)
                    : 'últimos 7 días en gráfica')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success')
                ->chart($s['total'] ?? []),
        ];

        if (($r['creditoPendiente'] ?? 0) > 0) {
            $stats[] = Stat::make('Crédito pendiente', 'S/ ' . number_format($r['creditoPendiente'], 2))
                ->description('por cobrar')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning');
        }

        if (($r['anuladas'] ?? 0) > 0) {
            $stats[] = Stat::make('Anuladas', number_format($r['anuladas']))
                ->description(($r['cortesias'] ?? 0) > 0 ? 'cortesías: ' . $r['cortesias'] : 'ventas canceladas')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger');
        }

        return $stats;
    }
}
