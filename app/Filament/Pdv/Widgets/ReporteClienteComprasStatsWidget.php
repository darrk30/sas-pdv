<?php

namespace App\Filament\Pdv\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class ReporteClienteComprasStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|array|null $columns = 3;

    #[Reactive]
    public array $statsData = [];

    protected function getStats(): array
    {
        $r           = $this->statsData;
        $cantidad    = $r['cantidad'] ?? 0;
        $totalGastado = $r['totalGastado'] ?? 0;
        $promedio    = $cantidad > 0 ? round($totalGastado / $cantidad, 2) : 0.0;

        $stats = [
            Stat::make('Compras', number_format($cantidad))
                ->description($r['clienteNombre'] ?? 'registradas')
                ->descriptionIcon('heroicon-o-shopping-bag')
                ->color('primary'),

            Stat::make('Total facturado', 'S/ ' . number_format($totalGastado, 2))
                ->description('S/ ' . number_format($promedio, 2) . ' prom. / compra')
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
