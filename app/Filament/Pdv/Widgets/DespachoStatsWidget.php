<?php

namespace App\Filament\Pdv\Widgets;

use App\Enums\EstadoVenta;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class DespachoStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|array|null $columns = 3;

    protected function getStats(): array
    {
        $empresaId = Filament::getTenant()->id;

        $row = DB::table('ventas')
            ->where('empresa_id', $empresaId)
            ->where('estado', EstadoVenta::Completada->value)
            ->whereNotNull('estado_despacho')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN DATE(fecha_emision) = CURDATE() THEN 1 ELSE 0 END) as hoy,
                SUM(CASE WHEN fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as semana
            ')
            ->first();

        $total  = (int) ($row->total  ?? 0);
        $hoy    = (int) ($row->hoy    ?? 0);
        $semana = (int) ($row->semana ?? 0);

        return [
            Stat::make('Total pendientes', $total)
                ->description('en toda la empresa')
                ->descriptionIcon('heroicon-o-paper-airplane')
                ->color($total > 0 ? 'warning' : 'gray'),

            Stat::make('Pendientes hoy', $hoy)
                ->description('registradas hoy')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color($hoy > 0 ? 'info' : 'gray'),

            Stat::make('Últimos 7 días', $semana)
                ->description('acumulados esta semana')
                ->descriptionIcon('heroicon-o-clock')
                ->color($semana > 0 ? 'primary' : 'gray'),
        ];
    }
}
