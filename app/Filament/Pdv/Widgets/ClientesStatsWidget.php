<?php

namespace App\Filament\Pdv\Widgets;

use App\Enums\TipoPago;
use App\Models\Cliente;
use App\Models\Venta;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClientesStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int | array | null $columns = [
        'default' => 2,
        'sm'      => 2,
        'lg'      => 3,
    ];

    protected function getStats(): array
    {
        $empresa   = Filament::getTenant();
        $empresaId = $empresa->id;
        $tieneCuentas = $empresa->tieneFeature('cuentas');

        $totalClientes = Cliente::where('empresa_id', $empresaId)->count();

        $stats = [
            Stat::make('Total clientes', number_format($totalClientes))
                ->description('Clientes registrados')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary')
                ->icon('heroicon-o-users'),
        ];

        if ($tieneCuentas) {
            $row = Venta::where('empresa_id', $empresaId)
                ->where('estado', 'completada')
                ->whereIn('estado_pago', ['pendiente', 'parcial'])
                ->selectRaw('COUNT(DISTINCT cliente_id) AS con_credito, COALESCE(SUM(saldo_pendiente), 0) AS total_pendiente')
                ->first();

            $conCredito     = (int)   ($row->con_credito     ?? 0);
            $totalPendiente = (float) ($row->total_pendiente ?? 0);

            $stats[] = Stat::make('Con saldo pendiente', number_format($conCredito))
                ->description('Clientes con crédito activo')
                ->descriptionIcon('heroicon-o-credit-card')
                ->color($conCredito > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-credit-card');

            $stats[] = Stat::make('Cuentas por cobrar', 'S/ ' . number_format($totalPendiente, 2))
                ->description('Saldo total pendiente')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color($totalPendiente > 0 ? 'danger' : 'gray')
                ->icon('heroicon-o-banknotes');
        }

        return $stats;
    }
}
