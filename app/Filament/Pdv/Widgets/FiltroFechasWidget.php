<?php

namespace App\Filament\Pdv\Widgets;

use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class FiltroFechasWidget extends Widget
{
    protected string $view          = 'filament.pdv.widgets.filtro-fechas';
    protected static ?int $sort     = 3;
    protected int|string|array $columnSpan = 'full';

    public string $filtro      = 'hoy';
    public string $desde       = '';
    public string $hasta       = '';
    public string $cacheInfo   = '';

    public function mount(): void
    {
        [$this->desde, $this->hasta] = $this->calcularRango('hoy');
        $this->cacheInfo = $this->leerCacheInfo();
    }

    public function setFiltro(string $filtro): void
    {
        $this->filtro = $filtro;
        if ($filtro !== 'personalizado') {
            [$this->desde, $this->hasta] = $this->calcularRango($filtro);
            $this->despachar();
        }
        $this->cacheInfo = $this->leerCacheInfo();
    }

    public function updatedDesde(): void
    {
        if ($this->filtro === 'personalizado' && $this->desde && $this->hasta) {
            $this->despachar();
            $this->cacheInfo = $this->leerCacheInfo();
        }
    }

    public function updatedHasta(): void
    {
        if ($this->filtro === 'personalizado' && $this->desde && $this->hasta) {
            $this->despachar();
            $this->cacheInfo = $this->leerCacheInfo();
        }
    }

    public function refrescar(): void
    {
        $empresaId = Filament::getTenant()->id;

        // 1. Borra las claves de caché de stats y gráfico para este empresa
        $this->dispatch('refrescarDashboard');

        // 2. Registra el nuevo timestamp ANTES de re-despachar
        //    así cuando stats/chart re-rendericen y guarden en caché, el ts ya está
        Cache::put('dash_ts_' . $empresaId, now()->timestamp, 700);

        // 3. Vuelve a despachar el filtro para que stats/chart re-ejecuten sus queries
        //    y guarden los datos frescos en caché
        $this->despachar();

        // 4. Muestra la hora real, no un texto fantasma
        $this->cacheInfo = $this->leerCacheInfo();
    }

    private function despachar(): void
    {
        $desde = Carbon::parse($this->desde)->startOfDay()->toDateTimeString();
        $hasta = Carbon::parse($this->hasta)->endOfDay()->toDateTimeString();
        $this->dispatch('filtroFechasActualizado', desde: $desde, hasta: $hasta);
    }

    private function calcularRango(string $filtro): array
    {
        return match ($filtro) {
            'semana' => [now()->startOfWeek()->format('Y-m-d'), now()->endOfWeek()->format('Y-m-d')],
            'mes'    => [now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d')],
            default  => [now()->format('Y-m-d'), now()->format('Y-m-d')],
        };
    }

    private function leerCacheInfo(): string
    {
        $empresaId = Filament::getTenant()?->id;
        if (!$empresaId) return '';

        $ts = Cache::get('dash_ts_' . $empresaId);
        if (!$ts) return 'Sin caché · Se actualizará al cargar datos';

        $mins = (int) round((time() - $ts) / 60);
        $label = match (true) {
            $mins <= 0  => 'ahora mismo',
            $mins === 1 => 'hace 1 min',
            default     => "hace {$mins} min",
        };

        return "Datos actualizados {$label} · Caché de 10 min";
    }
}
