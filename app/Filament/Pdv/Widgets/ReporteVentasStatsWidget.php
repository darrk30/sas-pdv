<?php

namespace App\Filament\Pdv\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class ReporteVentasStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

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

            Stat::make('Total cobrado', 'S/ ' . fmt_precio($r['total'] ?? 0))
                ->description(($r['descuentoTotal'] ?? 0) > 0
                    ? 'desc: − S/ ' . fmt_precio($r['descuentoTotal'])
                    : 'últimos 7 días en gráfica')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success')
                ->chart($s['total'] ?? []),
        ];

        if (($r['creditoPendiente'] ?? 0) > 0) {
            $stats[] = Stat::make('Crédito pendiente', 'S/ ' . fmt_precio($r['creditoPendiente']))
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
