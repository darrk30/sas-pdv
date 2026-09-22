<?php

namespace App\Filament\Pdv\Widgets;

use App\Models\Compra;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ComprasStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|array|null $columns = [
        'default' => 2,
        'sm'      => 2,
        'lg'      => 4,
    ];

    protected function getStats(): array
    {
        $row = Compra::where('empresa_id', Filament::getTenant()->id)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN estado_despacho = 'pendiente' AND estado != 'anulado' THEN 1 ELSE 0 END), 0) AS pendientes_despacho,
                COALESCE(SUM(CASE WHEN estado_despacho = 'pendiente' AND estado != 'anulado' THEN total ELSE 0 END), 0) AS monto_pendiente_despacho,
                COALESCE(SUM(CASE WHEN estado_pago IN ('pendiente','parcial') AND estado != 'anulado' THEN 1 ELSE 0 END), 0) AS pendientes_pago,
                COALESCE(SUM(CASE WHEN estado_pago IN ('pendiente','parcial') THEN total ELSE 0 END), 0) AS monto_total_pago,
                COALESCE(SUM(CASE WHEN estado_pago IN ('pendiente','parcial') THEN (
                    SELECT COALESCE(SUM(cp.monto), 0) FROM compra_pagos cp WHERE cp.compra_id = compras.id
                ) ELSE 0 END), 0) AS monto_ya_pagado
            ")
            ->first();

        $pendientesDespacho    = (int)   ($row->pendientes_despacho    ?? 0);
        $montoDespacho         = (float) ($row->monto_pendiente_despacho ?? 0);
        $pendientesPago        = (int)   ($row->pendientes_pago        ?? 0);
        $montoTotalPago        = (float) ($row->monto_total_pago        ?? 0);
        $montoYaPagado         = (float) ($row->monto_ya_pagado         ?? 0);
        $saldoPorPagar         = max(0, $montoTotalPago - $montoYaPagado);

        return [
            Stat::make('Pendientes de despacho', $pendientesDespacho)
                ->description('S/ ' . number_format($montoDespacho, 2) . ' en compras')
                ->descriptionIcon('heroicon-o-truck')
                ->color($pendientesDespacho > 0 ? 'warning' : 'gray'),

            Stat::make('Pendientes de pago', $pendientesPago)
                ->description('S/ ' . number_format($saldoPorPagar, 2) . ' por pagar')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color($pendientesPago > 0 ? 'danger' : 'gray'),
        ];
    }
}
