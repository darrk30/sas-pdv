<?php

namespace App\Filament\Pdv\Widgets;

use App\Enums\EstadoVenta;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class ReporteProductosStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;
    protected int|array|null $columns = 3;

    public string $desde      = '';
    public string $hasta      = '';
    public string $categoriaId = '';

    #[On('reporte-productos-filtros')]
    public function updateFiltros(string $desde, string $hasta, string $categoriaId): void
    {
        $this->desde       = $desde;
        $this->hasta       = $hasta;
        $this->categoriaId = $categoriaId;
    }

    protected function getStats(): array
    {
        $empresaId   = Filament::getTenant()->id;
        $desde       = $this->desde  ?: null;
        $hasta       = $this->hasta  ?: null;
        $categoriaId = $this->categoriaId ?: null;

        $row = DB::table('venta_detalles as vd')
            ->join('ventas as v', 'vd.venta_id', '=', 'v.id')
            ->leftJoin('productos as p', 'vd.producto_id', '=', 'p.id')
            ->where('v.empresa_id', $empresaId)
            ->where('v.estado', EstadoVenta::Completada->value)
            ->where('vd.precio_unitario', '>', 0)
            ->when($desde,       fn ($q) => $q->where('v.fecha_emision', '>=', $desde))
            ->when($hasta,       fn ($q) => $q->where('v.fecha_emision', '<=', $hasta))
            ->when($categoriaId, fn ($q) => $q->where('p.categoria_id', $categoriaId))
            ->selectRaw("
                COUNT(DISTINCT vd.descripcion)              AS productos,
                COALESCE(SUM(vd.cantidad), 0)               AS unidades,
                COALESCE(SUM(vd.total), 0)                  AS ingresos,
                COALESCE(SUM(vd.costo_total), 0)            AS costo,
                COALESCE(SUM(vd.total - vd.costo_total), 0) AS utilidad
            ")
            ->first();

        $productos = (int)   ($row->productos ?? 0);
        $unidades  = (float) ($row->unidades  ?? 0);
        $ingresos  = (float) ($row->ingresos  ?? 0);
        $costo     = (float) ($row->costo     ?? 0);
        $utilidad  = (float) ($row->utilidad  ?? 0);
        $margenPct = $ingresos > 0 ? round($utilidad / $ingresos * 100, 1) : 0.0;

        return [
            Stat::make('Productos distintos', number_format($productos))
                ->description(number_format($unidades, 2) . ' unidades vendidas')
                ->descriptionIcon('heroicon-o-cube')
                ->color('gray'),

            Stat::make('Ingresos', 'S/ ' . number_format($ingresos, 2))
                ->description('costo: S/ ' . number_format($costo, 2))
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('primary'),

            Stat::make('Utilidad', 'S/ ' . number_format($utilidad, 2))
                ->description('margen: ' . number_format($margenPct, 1) . '%')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color($margenPct >= 20 ? 'success' : ($margenPct > 0 ? 'warning' : 'danger')),
        ];
    }
}
