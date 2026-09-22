<?php

namespace App\Filament\Pdv\Widgets;

use App\Enums\EstadoGeneral;
use App\Models\Producto;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProductosStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int | array | null $columns = [
        'default' => 2,
        'sm'      => 2,
        'lg'      => 3,
    ];

    protected function getStats(): array
    {
        $empresaId = Filament::getTenant()->id;

        $total     = Producto::where('empresa_id', $empresaId)->count();
        $activos   = Producto::where('empresa_id', $empresaId)->where('estado', EstadoGeneral::Activo)->count();
        $inactivos = $total - $activos;

        return [
            Stat::make('Total productos', number_format($total))
                ->description('Productos registrados')
                ->descriptionIcon('heroicon-o-cube')
                ->color('primary')
                ->icon('heroicon-o-cube'),

            Stat::make('Activos', number_format($activos))
                ->description('Disponibles para venta')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Inactivos', number_format($inactivos))
                ->description('Fuera de catálogo')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color($inactivos > 0 ? 'danger' : 'gray')
                ->icon('heroicon-o-x-circle'),
        ];
    }
}
