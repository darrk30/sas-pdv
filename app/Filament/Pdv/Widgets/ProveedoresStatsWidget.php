<?php

namespace App\Filament\Pdv\Widgets;

use App\Models\Proveedor;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ProveedoresStatsWidget extends BaseWidget
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

        $totalProveedores = Proveedor::where('empresa_id', $empresaId)->count();

        $pagadoSub = DB::table('compra_pagos')
            ->selectRaw('compra_id, SUM(monto) as total_pagado')
            ->groupBy('compra_id');

        $deuda = DB::table('compras as c')
            ->leftJoinSub($pagadoSub, 'p', 'p.compra_id', '=', 'c.id')
            ->where('c.empresa_id', $empresaId)
            ->whereIn('c.estado_pago', ['pendiente', 'parcial'])
            ->selectRaw('COUNT(DISTINCT c.proveedor_id) AS con_deuda, COALESCE(SUM(c.total - COALESCE(p.total_pagado, 0)), 0) AS saldo_total')
            ->first();

        $conDeuda   = (int)   ($deuda->con_deuda   ?? 0);
        $saldoTotal = (float) ($deuda->saldo_total ?? 0);

        return [
            Stat::make('Total proveedores', number_format($totalProveedores))
                ->description('Proveedores registrados')
                ->descriptionIcon('heroicon-o-truck')
                ->color('primary')
                ->icon('heroicon-o-truck'),

            Stat::make('Con deuda pendiente', number_format($conDeuda))
                ->description('Proveedores con saldo por pagar')
                ->descriptionIcon('heroicon-o-credit-card')
                ->color($conDeuda > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-credit-card'),

            Stat::make('Cuentas por pagar', 'S/ ' . number_format($saldoTotal, 2))
                ->description('Saldo total con proveedores')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color($saldoTotal > 0 ? 'danger' : 'gray')
                ->icon('heroicon-o-banknotes'),
        ];
    }
}
