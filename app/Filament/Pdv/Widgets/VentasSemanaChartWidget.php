<?php

namespace App\Filament\Pdv\Widgets;

use App\Enums\EstadoVenta;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class VentasSemanaChartWidget extends ChartWidget
{
    protected ?string $heading    = 'Ventas del período';
    protected ?string $maxHeight  = '300px';
    protected static ?int $sort   = 5;
    protected int|string|array $columnSpan = 'full';

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

    protected function getData(): array
    {
        return Cache::remember($this->cacheKey(), 600, function () {
            $empresaId = Filament::getTenant()->id;
            $desde     = Carbon::parse($this->desde ?: now()->startOfDay());
            $hasta     = Carbon::parse($this->hasta ?: now()->endOfDay());
            $diffDias  = $desde->diffInDays($hasta);

            if ($diffDias < 1) {
                $raw = DB::table('ventas')
                    ->selectRaw('HOUR(created_at) as hora, SUM(total) as total')
                    ->where('empresa_id', $empresaId)
                    ->whereDate('created_at', $desde->toDateString())
                    ->where('estado', EstadoVenta::Completada->value)
                    ->groupByRaw('HOUR(created_at)')
                    ->pluck('total', 'hora');

                $labels = [];
                $data   = [];
                for ($h = 0; $h < 24; $h++) {
                    $labels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                    $data[]   = (float) ($raw[$h] ?? 0);
                }
            } else {
                $raw = DB::table('ventas')
                    ->selectRaw('DATE(created_at) as fecha, SUM(total) as total')
                    ->where('empresa_id', $empresaId)
                    ->whereBetween('created_at', [$desde->toDateTimeString(), $hasta->toDateTimeString()])
                    ->where('estado', EstadoVenta::Completada->value)
                    ->groupByRaw('DATE(created_at)')
                    ->pluck('total', 'fecha');

                $labels  = [];
                $data    = [];
                $current = $desde->copy()->startOfDay();
                while ($current <= $hasta) {
                    $key      = $current->format('Y-m-d');
                    $labels[] = $current->format('d/m');
                    $data[]   = (float) ($raw[$key] ?? 0);
                    $current->addDay();
                }
            }

            return [
                'datasets' => [[
                    'label'           => 'Ventas S/',
                    'data'            => $data,
                    'backgroundColor' => 'rgba(79, 127, 255, 0.15)',
                    'borderColor'     => 'rgb(79, 127, 255)',
                    'borderWidth'     => 2,
                    'borderRadius'    => 4,
                    'fill'            => true,
                    'tension'         => 0.3,
                ]],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string { return 'bar'; }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales'  => [
                'y' => ['beginAtZero' => true, 'grid' => ['color' => 'rgba(0,0,0,0.05)']],
            ],
        ];
    }

    private function cacheKey(): string
    {
        $empresaId = Filament::getTenant()->id;
        $hash      = md5(($this->desde ?: '') . '|' . ($this->hasta ?: ''));
        return "dash_chart_{$empresaId}_{$hash}";
    }
}
