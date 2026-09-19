<?php

namespace App\Filament\Pdv\Widgets;

use App\Enums\EstadoGasto;
use App\Enums\EstadoVenta;
use App\Enums\FrecuenciaGasto;
use App\Models\GastoFijo;
use App\Models\Gasto;
use App\Models\Venta;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class CaminoGananciasWidget extends Widget
{
    protected string $view = 'filament.pdv.widgets.camino-ganancias';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('resumen.camino_ganancias') ?? false;
    }

    public function getData(): array
    {
        $empresaId = Filament::getTenant()->id;
        $inicio    = Carbon::now()->startOfMonth()->toDateString();
        $fin       = Carbon::now()->endOfMonth()->toDateString();

        // Ventas del mes (brutas e IGV)
        $ventasRow = Venta::where('empresa_id', $empresaId)
            ->where('estado', '!=', EstadoVenta::Anulada->value)
            ->whereBetween('fecha_emision', [$inicio, $fin])
            ->selectRaw('COALESCE(SUM(total), 0) as total_bruto, COALESCE(SUM(igv), 0) as total_igv')
            ->first();

        $totalBruto = (float) ($ventasRow->total_bruto ?? 0);
        $totalIgv   = (float) ($ventasRow->total_igv   ?? 0);
        $totalNeto  = $totalBruto - $totalIgv;

        // Gastos variables del mes (no anulados)
        $gastosVariables = (float) Gasto::where('empresa_id', $empresaId)
            ->where('estado', '!=', EstadoGasto::Anulado->value)
            ->whereBetween('fecha', [$inicio, $fin])
            ->sum('monto');

        // Gastos fijos activos → normalizar a mensual
        $gastosFijos = GastoFijo::where('empresa_id', $empresaId)
            ->where('estado', 'activo')
            ->get();

        $totalFijosMensual = $gastosFijos->sum(
            fn ($g) => $g->frecuencia->montoEnPeriodo((float) $g->monto, FrecuenciaGasto::Mensual)
        );

        $meta     = $totalFijosMensual + $gastosVariables;
        $diferencia = $totalNeto - $meta;
        $pct      = $meta > 0 ? min(100, round($totalNeto / $meta * 100, 1)) : ($totalNeto > 0 ? 100 : 0);
        $ganando  = $diferencia >= 0;
        $mes      = Carbon::now()->locale('es')->isoFormat('MMMM YYYY');

        return compact(
            'totalBruto', 'totalIgv', 'totalNeto',
            'gastosVariables', 'totalFijosMensual',
            'meta', 'diferencia', 'pct', 'ganando', 'mes'
        );
    }
}
