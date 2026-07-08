<?php

namespace App\Filament\Pdv\Widgets;

use App\Enums\EstadoOrden;
use App\Enums\EstadoVenta;
use App\Models\Orden;
use App\Models\Venta;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;

class ResumenDashboardWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    public string $desde = '';
    public string $hasta = '';

    public function mount(): void
    {
        $this->desde = now()->startOfDay()->toDateTimeString();
        $this->hasta = now()->endOfDay()->toDateTimeString();
    }

    #[On('filtroFechasActualizado')]
    public function actualizarFiltro(string $desde, string $hasta): void
    {
        $this->desde = $desde;
        $this->hasta = $hasta;
    }

    #[On('refrescarDashboard')]
    public function refrescar(): void
    {
        Cache::forget($this->cacheKey());
    }

    protected function getStats(): array
    {
        $key  = $this->cacheKey();
        $data = Cache::remember($key, 600, function () {
            $empresaId = Filament::getTenant()->id;
            $desde     = $this->desde ?: now()->startOfDay()->toDateTimeString();
            $hasta     = $this->hasta ?: now()->endOfDay()->toDateTimeString();

            Cache::put('dash_ts_' . $empresaId, now()->timestamp, 700);

            $baseVentas = Venta::where('empresa_id', $empresaId)
                ->whereBetween('created_at', [$desde, $hasta])
                ->where('estado', EstadoVenta::Completada);

            return [
                'totalVentas'        => (float) (clone $baseVentas)->sum('total'),
                'totalCosto'         => (float) (clone $baseVentas)->sum('costo_total'),
                'nVentas'            => (int)   (clone $baseVentas)->count(),
                'ordenesPendientes'  => Orden::where('empresa_id', $empresaId)
                                            ->where('estado', EstadoOrden::PendientePago)
                                            ->count(),
                'despachosPendientes' => Venta::where('empresa_id', $empresaId)
                                            ->where('estado_despacho', EstadoVenta::PendienteEnvio->value)
                                            ->count(),
            ];
        });

        $utilidad = $data['totalVentas'] - $data['totalCosto'];
        $n        = $data['nVentas'];

        return [
            Stat::make('Ventas', 'S/ ' . number_format($data['totalVentas'], 2))
                ->description($n . ' ' . ($n === 1 ? 'venta completada' : 'ventas completadas'))
                ->descriptionIcon('heroicon-o-receipt-percent')
                ->color('success')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Utilidad', 'S/ ' . number_format($utilidad, 2))
                ->description('Ventas − Costo')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color($utilidad >= 0 ? 'info' : 'danger')
                ->icon('heroicon-o-arrow-trending-up'),

            Stat::make('Órdenes pendientes', (string) $data['ordenesPendientes'])
                ->description('Por confirmar pago')
                ->descriptionIcon('heroicon-o-clock')
                ->color($data['ordenesPendientes'] > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-clipboard-document-list'),

            Stat::make('Despachos pendientes', (string) $data['despachosPendientes'])
                ->description('Por enviar al cliente')
                ->descriptionIcon('heroicon-o-truck')
                ->color($data['despachosPendientes'] > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-paper-airplane'),
        ];
    }

    private function cacheKey(): string
    {
        $empresaId = Filament::getTenant()->id;
        $hash      = md5(($this->desde ?: '') . '|' . ($this->hasta ?: ''));
        return "dash_stats_{$empresaId}_{$hash}";
    }
}
