<?php

namespace App\Filament\Pdv\Widgets;

use App\Enums\EstadoSesion;
use App\Enums\EstadoVenta;
use App\Models\SesionCaja;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class VentasSesionMetodosWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;
    protected ?string $pollingInterval  = null;
    protected int | string | array $columnSpan = 1;

    protected int | array | null $columns = [
        'default' => 2,
        'sm'      => 3,
        'lg'      => 5,
    ];

    protected function getStats(): array
    {
        $sesion = SesionCaja::where('empresa_id', Filament::getTenant()->id)
            ->where('user_id', auth()->id())
            ->where('estado', EstadoSesion::Abierta->value)
            ->latest()
            ->first();

        if (! $sesion) {
            return [];
        }

        $metodos = DB::table('venta_pagos')
            ->join('ventas',       'ventas.id',       '=', 'venta_pagos.venta_id')
            ->join('metodos_pago', 'metodos_pago.id', '=', 'venta_pagos.metodo_pago_id')
            ->where('ventas.sesion_caja_id', $sesion->id)
            ->where('ventas.estado', EstadoVenta::Completada->value)
            ->selectRaw('metodos_pago.nombre, SUM(venta_pagos.monto) AS total')
            ->groupBy('metodos_pago.id', 'metodos_pago.nombre')
            ->orderByDesc('total')
            ->get();

        if ($metodos->isEmpty()) {
            return [];
        }

        $stats = $metodos->map(fn ($m) => Stat::make(
                $m->nombre,
                'S/ ' . number_format((float) $m->total, 2)
            )
            ->description('Recaudado este turno')
            ->descriptionIcon('heroicon-o-banknotes')
            ->color('success')
            ->icon('heroicon-o-credit-card')
        )->values()->all();

        if (count($stats) > 1) {
            $sumaTotal = $metodos->sum('total');
            $stats[]   = Stat::make('Total cobrado', 'S/ ' . number_format((float) $sumaTotal, 2))
                ->description('Suma de todos los métodos')
                ->descriptionIcon('heroicon-o-calculator')
                ->color('primary')
                ->icon('heroicon-o-currency-dollar');
        }

        return $stats;
    }
}
