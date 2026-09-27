<?php

namespace App\Filament\Pdv\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class ReporteVendedoresStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|array|null $columns = ['default' => 2, 'sm' => 2, 'lg' => 4];

    #[Reactive]
    public array $statsData = [];

    #[Reactive]
    public array $sparksData = [];

    protected function getStats(): array
    {
        $r    = $this->statsData;
        $s    = $this->sparksData;
        $util = $r['utilidadBruta'] ?? 0;

        $stats = [
            Stat::make('Vendedores activos', number_format($r['totalVendedores'] ?? 0))
                ->description(number_format($r['cantidad'] ?? 0) . ' ventas completadas')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('primary')
                ->chart($s['cantidad'] ?? []),

            Stat::make('Ingresos brutos', 'S/ ' . number_format($r['ingresosBrutos'] ?? 0, 2))
                ->description('total facturado')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success')
                ->chart($s['ingresos'] ?? []),

            Stat::make('Utilidad bruta', 'S/ ' . number_format($util, 2))
                ->description('costo: S/ ' . number_format($r['costoTotal'] ?? 0, 2))
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
