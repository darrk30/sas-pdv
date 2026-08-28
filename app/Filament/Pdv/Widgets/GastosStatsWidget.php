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
        'lg'      => 4,
    ];

    protected function getStats(): array
    {
        $empresaId = Filament::getTenant()->id;
        $mesActual = now()->format('Y-m');

        $base = Gasto::where('empresa_id', $empresaId)
            ->whereRaw("DATE_FORMAT(fecha, '%Y-%m') = ?", [$mesActual])
            ->where('estado', '!=', EstadoGasto::Anulado->value);

        $totales = (clone $base)
            ->selectRaw("
                COUNT(*) AS total,
                SUM(monto) AS suma,
                SUM(CASE WHEN estado = ? THEN monto ELSE 0 END) AS aprobados,
                SUM(CASE WHEN estado = ? THEN monto ELSE 0 END) AS pendientes
            ", [EstadoGasto::Aprobado->value, EstadoGasto::Pendiente->value])
            ->first();

        $suma       = (float) ($totales->suma       ?? 0);
        $aprobados  = (float) ($totales->aprobados  ?? 0);
        $pendientes = (float) ($totales->pendientes ?? 0);
        $total      = (int)   ($totales->total      ?? 0);

        $mes = now()->translatedFormat('F');

        return [
            Stat::make('Total ' . $mes, 'S/ ' . number_format($suma, 2))
                ->description("{$total} gastos registrados")
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('gray')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Aprobados', 'S/ ' . number_format($aprobados, 2))
                ->description('Gastos confirmados')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Pendientes', 'S/ ' . number_format($pendientes, 2))
                ->description('Por confirmar')
                ->descriptionIcon('heroicon-o-clock')
                ->color($pendientes > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-clock'),

            Stat::make('Registros', $total)
                ->description('Este mes')
                ->descriptionIcon('heroicon-o-document-text')
                ->color('primary')
                ->icon('heroicon-o-document-text'),
        ];
    }
}
