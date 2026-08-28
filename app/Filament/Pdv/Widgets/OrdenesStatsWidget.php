<?php

namespace App\Filament\Pdv\Widgets;

use App\Enums\EstadoOrden;
use App\Models\Orden;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrdenesStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int | array | null $columns = [
        'default' => 2,
        'sm'      => 2,
        'lg'      => 4,
    ];

    protected function getStats(): array
    {
        $empresaId = Filament::getTenant()->id;

        $counts = Orden::where('empresa_id', $empresaId)
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) AS pagadas,
                SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) AS anuladas
            ", [
                EstadoOrden::PagoConfirmado->value,
                EstadoOrden::PendientePago->value,
                EstadoOrden::Cancelada->value,
            ])
            ->first();

        $total      = (int) ($counts->total      ?? 0);
        $pagadas    = (int) ($counts->pagadas     ?? 0);
        $pendientes = (int) ($counts->pendientes  ?? 0);
        $anuladas   = (int) ($counts->anuladas    ?? 0);

        return [
            Stat::make('Total órdenes', $total)
                ->description('Todas las órdenes')
                ->descriptionIcon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->icon('heroicon-o-clipboard-document-list'),

            Stat::make('Pagadas', $pagadas)
                ->description('Pago confirmado')
                ->descriptionIcon('heroicon-o-check-badge')
                ->color('success')
                ->icon('heroicon-o-check-badge'),

            Stat::make('Pendientes', $pendientes)
                ->description('Por confirmar pago')
                ->descriptionIcon('heroicon-o-clock')
                ->color($pendientes > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Anuladas', $anuladas)
                ->description('Canceladas')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color($anuladas > 0 ? 'danger' : 'gray')
                ->icon('heroicon-o-x-circle'),
        ];
    }
}
