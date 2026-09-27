<?php

namespace App\Filament\Pdv\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class ReporteVentasPeriodoStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|array|null $columns = ['default' => 2, 'sm' => 3, 'lg' => 5];

    #[Reactive]
    public array $statsData = [];

    #[Reactive]
    public array $sparksData = [];

    protected function getStats(): array
    {
        $r    = $this->statsData;
        $s    = $this->sparksData;
        $lbl  = $r['labelAnterior'] ?? 'período anterior';
        $util = $r['utilidadBruta'] ?? 0;
        $tDir = ($r['tUtilidad'] ?? null) ? ($r['tUtilidad']['arriba'] ? '↑' : '↓') : null;

        return [
            Stat::make('Ventas', number_format($r['cantidad'] ?? 0))
                ->description(($r['tCantidad'] ?? null)
                    ? (($r['tCantidad']['arriba'] ? '↑' : '↓') . ' vs ' . $lbl)
                    : 'últimos 7 días')
                ->descriptionIcon('heroicon-o-shopping-cart')
                ->color('primary')
                ->chart($s['cantidad'] ?? []),

            Stat::make('Total facturado', 'S/ ' . number_format($r['ingresosBrutos'] ?? 0, 2))
                ->description(($r['tIngresos'] ?? null)
                    ? (($r['tIngresos']['arriba'] ? '↑' : '↓') . ' vs ' . $lbl)
                    : 'últimos 7 días')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success')
                ->chart($s['ingresos'] ?? []),

            Stat::make('Total sin IGV', 'S/ ' . number_format($r['ventasNetas'] ?? 0, 2))
                ->description('neto sin impuesto')
                ->descriptionIcon('heroicon-o-receipt-percent')
                ->color('gray')
                ->chart($s['ingresos'] ?? []),

            Stat::make('Costo de ventas', 'S/ ' . number_format($r['costoTotal'] ?? 0, 2))
                ->description('costo de productos')
                ->descriptionIcon('heroicon-o-cube')
                ->color('warning')
                ->chart($s['ingresos'] ?? []),

            Stat::make('Utilidad bruta', 'S/ ' . number_format($util, 2))
                ->description($tDir ? ($tDir . ' vs ' . $lbl) : 'últimos 7 días')
                ->descriptionIcon($util >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($util >= 0 ? 'success' : 'danger')
                ->chart($s['utilidad'] ?? []),
        ];
    }
}
