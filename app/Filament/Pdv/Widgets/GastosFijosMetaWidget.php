<?php

namespace App\Filament\Pdv\Widgets;

use App\Enums\FrecuenciaGasto;
use App\Models\GastoFijo;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GastosFijosMetaWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $description = 'Es la base del cálculo de cuánto necesitas vender para cubrir gastos.';

    protected function getStats(): array
    {
        $empresaId = Filament::getTenant()->id;

        $gastosFijos = GastoFijo::where('empresa_id', $empresaId)
            ->where('estado', 'activo')
            ->get();

        $mensual  = $gastosFijos->sum(fn ($g) => $g->frecuencia->montoEnPeriodo((float) $g->monto, FrecuenciaGasto::Mensual));
        $semanal  = $gastosFijos->sum(fn ($g) => $g->frecuencia->montoEnPeriodo((float) $g->monto, FrecuenciaGasto::Semanal));

        return [
            Stat::make('Total gastos fijos mensual', 'S/ ' . number_format($mensual, 2))
                ->description('Suma de todos los gastos fijos activos por mes')
                ->descriptionIcon('heroicon-o-calendar')
                ->color('indigo')
                ->icon('heroicon-o-clipboard-document-list'),

            Stat::make('Equivalente semanal', 'S/ ' . number_format($semanal, 2))
                ->description('Mismo total distribuido por semana')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('warning')
                ->icon('heroicon-o-calendar-days'),
        ];
    }
}
