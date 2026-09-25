<?php

namespace App\Filament\Pdv\Widgets;

use App\Enums\EstadoSesion;
use App\Enums\EstadoVenta;
use App\Models\SesionCaja;
use App\Models\Venta;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class VentasSesionResumenWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;
    protected ?string $pollingInterval  = null;

    protected int | array | null $columns = [
        'default' => 2,
        'sm'      => 3,
        'lg'      => 6,
    ];

    protected function getStats(): array
    {
        $sesion = SesionCaja::where('empresa_id', Filament::getTenant()->id)
            ->where('user_id', auth()->id())
            ->where('estado', EstadoSesion::Abierta->value)
            ->with('caja')
            ->latest()
            ->first();

        if (! $sesion) {
            return [
                Stat::make('Estado', 'Sin turno activo')
                    ->description('Abre una caja para comenzar a vender')
                    ->icon('heroicon-o-lock-closed')
                    ->color('gray'),
            ];
        }

        $completada = EstadoVenta::Completada->value;
        $anulada    = EstadoVenta::Anulada->value;
        $pendEnvio  = EstadoVenta::PendienteEnvio->value;
        $empresaId  = Filament::getTenant()->id;

        $row = DB::table('ventas')
            ->where('empresa_id', $empresaId)
            ->where('sesion_caja_id', $sesion->id)
            ->selectRaw("
                SUM(CASE WHEN estado = ? THEN 1    ELSE 0 END) AS cnt,
                SUM(CASE WHEN estado = ? THEN descuento_total ELSE 0 END) AS descuento_total,
                SUM(CASE WHEN estado = ? THEN 1    ELSE 0 END) AS anuladas,
                SUM(CASE WHEN estado_despacho = ? THEN 1 ELSE 0 END) AS despacho
            ", [$completada, $completada, $anulada, $pendEnvio])
            ->first();

        $cortesias = Venta::where('empresa_id', $empresaId)
            ->where('sesion_caja_id', $sesion->id)
            ->where('estado', $completada)
            ->whereHas('detalles', fn ($q) => $q->where('precio_unitario', 0))
            ->count();

        $desde = $sesion->caja?->nombre
            ? $sesion->caja->nombre . ' · desde ' . $sesion->fecha_apertura->format('H:i')
            : 'Desde ' . $sesion->fecha_apertura->format('H:i');

        $stats = [
            Stat::make('Ventas', (int) ($row->cnt ?? 0))
                ->description($desde)
                ->descriptionIcon('heroicon-o-building-storefront')
                ->color('success')
                ->icon('heroicon-o-receipt-percent'),
        ];

        if ((float) ($row->descuento_total ?? 0) > 0) {
            $stats[] = Stat::make('Descuentos', '- S/ ' . number_format((float) $row->descuento_total, 2))
                ->description('Total en descuentos')
                ->descriptionIcon('heroicon-o-tag')
                ->color('danger')
                ->icon('heroicon-o-tag');
        }

        if ($cortesias > 0) {
            $stats[] = Stat::make('Cortesías', $cortesias . ' ventas')
                ->description('Con al menos un ítem sin costo')
                ->descriptionIcon('heroicon-o-gift')
                ->color('warning')
                ->icon('heroicon-o-gift');
        }

        if ((int) ($row->anuladas ?? 0) > 0) {
            $stats[] = Stat::make('Anuladas', (int) $row->anuladas)
                ->description('Ventas anuladas en el turno')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger')
                ->icon('heroicon-o-x-circle');
        }

        if ((int) ($row->despacho ?? 0) > 0) {
            $stats[] = Stat::make('Despacho pendiente', (int) $row->despacho)
                ->description('Pedidos pendientes de envío')
                ->descriptionIcon('heroicon-o-truck')
                ->color('warning')
                ->icon('heroicon-o-truck');
        }

        // ── Métodos de pago ───────────────────────────────────────────────────
        $metodos = DB::table('venta_pagos')
            ->join('ventas',       'ventas.id',       '=', 'venta_pagos.venta_id')
            ->join('metodos_pago', 'metodos_pago.id', '=', 'venta_pagos.metodo_pago_id')
            ->where('ventas.sesion_caja_id', $sesion->id)
            ->where('ventas.estado', $completada)
            ->selectRaw('metodos_pago.nombre, SUM(venta_pagos.monto) AS total')
            ->groupBy('metodos_pago.id', 'metodos_pago.nombre')
            ->orderByDesc('total')
            ->get();

        foreach ($metodos as $m) {
            $stats[] = Stat::make($m->nombre, 'S/ ' . number_format((float) $m->total, 2))
                ->description('Método de pago')
                ->descriptionIcon('heroicon-o-credit-card')
                ->color('info')
                ->icon('heroicon-o-banknotes');
        }

        return $stats;
    }
}
