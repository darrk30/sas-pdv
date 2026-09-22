<?php

namespace App\Filament\Pdv\Widgets;

use App\Enums\TipoPago;
use App\Models\Venta;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CreditosClienteStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        $empresa = Filament::getTenant();
        return $empresa && $empresa->tieneFeature('cuentas');
    }

    public ?int $filtroClienteId = null;

    protected int | array | null $columns = [
        'default' => 2,
        'sm'      => 2,
        'lg'      => 4,
    ];

    protected function getStats(): array
    {
        $query = Venta::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', 'completada')
            ->where(function ($q) {
                $q->where('tipo_pago', TipoPago::Credito)
                  ->orWhere('estado_pago', 'parcial');
            });

        if ($this->filtroClienteId) {
            $query->where('cliente_id', $this->filtroClienteId);
        }

        $row = (clone $query)->selectRaw('
            COUNT(*) as total_creditos,
            COALESCE(SUM(total), 0) as total_facturado,
            COALESCE(SUM(monto_pagado), 0) as total_cobrado,
            COALESCE(SUM(saldo_pendiente), 0) as total_pendiente,
            COALESCE(SUM(CASE WHEN estado_pago = "pendiente" AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURDATE() THEN 1 ELSE 0 END), 0) as cuentas_vencidas,
            COALESCE(SUM(CASE WHEN estado_pago = "pendiente" AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURDATE() THEN saldo_pendiente ELSE 0 END), 0) as monto_vencido
        ')->first();

        $totalCreditos   = (int)   ($row->total_creditos   ?? 0);
        $totalCobrado    = (float) ($row->total_cobrado    ?? 0);
        $totalPendiente  = (float) ($row->total_pendiente  ?? 0);
        $cuentasVencidas = (int)   ($row->cuentas_vencidas ?? 0);
        $montoVencido    = (float) ($row->monto_vencido    ?? 0);

        return [
            Stat::make('Total créditos', $totalCreditos)
                ->description('S/ ' . number_format((float) ($row->total_facturado ?? 0), 2) . ' facturado')
                ->descriptionIcon('heroicon-o-document-text')
                ->color('primary'),

            Stat::make('Cobrado', 'S/ ' . number_format($totalCobrado, 2))
                ->description('Pagos recibidos')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Por cobrar', 'S/ ' . number_format($totalPendiente, 2))
                ->description('Saldo pendiente total')
                ->descriptionIcon('heroicon-o-clock')
                ->color($totalPendiente > 0 ? 'warning' : 'gray'),

            Stat::make('Vencidas', $cuentasVencidas . ' cta' . ($cuentasVencidas !== 1 ? 's' : ''))
                ->description('S/ ' . number_format($montoVencido, 2) . ' en riesgo')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($cuentasVencidas > 0 ? 'danger' : 'gray'),
        ];
    }
}
