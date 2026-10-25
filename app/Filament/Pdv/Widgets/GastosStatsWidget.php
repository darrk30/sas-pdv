<?php

namespace App\Filament\Pdv\Widgets;

use App\Enums\EstadoGasto;
use App\Models\Gasto;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GastosStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int | array | null $columns = [
        'default' => 2,
        'sm'      => 2,
    ];

    protected function getStats(): array
    {
        $empresaId = Filament::getTenant()->id;
        $mesActual = now()->format('Y-m');

        $totales = Gasto::where('empresa_id', $empresaId)
            ->whereRaw("DATE_FORMAT(fecha, '%Y-%m') = ?", [$mesActual])
            ->selectRaw("
                SUM(CASE WHEN estado = ? THEN monto ELSE 0 END) AS aprobados,
                SUM(CASE WHEN estado = ? THEN monto ELSE 0 END) AS anulados
            ", [EstadoGasto::Aprobado->value, EstadoGasto::Anulado->value])
            ->first();

        $aprobados = (float) ($totales->aprobados ?? 0);
        $anulados  = (float) ($totales->anulados  ?? 0);

        $mes = now()->translatedFormat('F');

        return [
            Stat::make('Aprobados ' . $mes, 'S/ ' . fmt_precio($aprobados))
                ->description('Gastos confirmados')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Anulados ' . $mes, 'S/ ' . fmt_precio($anulados))
                ->description('Gastos anulados')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color($anulados > 0 ? 'danger' : 'gray')
                ->icon('heroicon-o-x-circle'),
        ];
    }
}
