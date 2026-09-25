<?php

namespace App\Filament\Pdv\Widgets;

use App\Models\Inventario;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class GestionInventarioStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;
    protected ?string $pollingInterval  = null;

    protected int | array | null $columns = [
        'default' => 2,
        'sm'      => 2,
        'lg'      => 4,
    ];

    protected function getStats(): array
    {
        $counts = Inventario::query()
            ->where('empresa_id', Filament::getTenant()->id)
            ->where('estado_almacen', 'activo')
            ->whereHas('producto', fn (Builder $q) => $q->where('estado', '!=', 'archivado'))
            ->selectRaw("
                SUM(CASE WHEN estado_inventario = 'disponible'   THEN 1 ELSE 0 END) AS disponible,
                SUM(CASE WHEN estado_inventario = 'por_agotarse' THEN 1 ELSE 0 END) AS por_agotarse,
                SUM(CASE WHEN estado_inventario = 'agotado'      THEN 1 ELSE 0 END) AS agotado,
                COUNT(*) AS total
            ")
            ->first();

        return [
            Stat::make('Total productos', (int) ($counts->total        ?? 0))
                ->description('En inventario activo')
                ->descriptionIcon('heroicon-o-archive-box')
                ->color('gray')
                ->icon('heroicon-o-archive-box'),

            Stat::make('Disponible', (int) ($counts->disponible   ?? 0))
                ->description('Con stock suficiente')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Por agotarse', (int) ($counts->por_agotarse ?? 0))
                ->description('Cerca del stock mínimo')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->icon('heroicon-o-exclamation-triangle'),

            Stat::make('Agotado', (int) ($counts->agotado      ?? 0))
                ->description('Sin stock disponible')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger')
                ->icon('heroicon-o-x-circle'),
        ];
    }
}
