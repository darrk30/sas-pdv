<?php

namespace App\Filament\Pdv\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class ReporteComprasStatsWidget extends BaseWidget
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

        return [
            Stat::make('Compras', number_format($r['cantidad'] ?? 0))
                ->description('en el período')
                ->descriptionIcon('heroicon-o-archive-box')
                ->color('primary')
                ->chart($s['cantidad'] ?? []),

            Stat::make('Total comprado', 'S/ ' . number_format($r['total'] ?? 0, 2))
                ->description('importe total')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success')
                ->chart($s['total'] ?? []),

            Stat::make('Total pagado', 'S/ ' . number_format($r['pagado'] ?? 0, 2))
                ->description('pagos registrados')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Saldo pendiente', 'S/ ' . number_format($r['saldo'] ?? 0, 2))
                ->description(($r['pendiente'] ?? 0) . ' compras por pagar')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),
        ];
    }
}
