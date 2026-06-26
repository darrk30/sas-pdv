<?php

namespace App\Filament\Pdv\Pages;

use App\Models\Kardex;
use App\Models\Producto;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\WithPagination;

class KardexPage extends Page
{
    use WithPagination;

    protected static string $view = 'filament.pdv.pages.kardex';
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Kardex';
    protected static ?string $navigationGroup = 'Inventario';
    protected static ?int    $navigationSort  = 10;
    protected static ?string $title           = 'Kardex de Inventario';

    public function getHeading(): string { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }

    // ── Filtros ───────────────────────────────────────────────────────────────
    public string  $busqueda   = '';
    public string  $fechaDesde = '';
    public string  $fechaHasta = '';
    public string  $tipo       = '';   // '' | 'entrada' | 'salida'
    public string  $origen     = '';   // '' | 'App\Models\Ajuste' | 'App\Models\Compra' | 'App\Models\Venta'
    public ?int    $productoId = null;

    public function mount(): void
    {
        $this->fechaDesde = now()->subDays(30)->toDateString();
        $this->fechaHasta = now()->toDateString();
    }

    public function updatedBusqueda(): void   { $this->resetPage(); }
    public function updatedFechaDesde(): void { $this->resetPage(); }
    public function updatedFechaHasta(): void { $this->resetPage(); }
    public function updatedTipo(): void       { $this->resetPage(); }
    public function updatedOrigen(): void     { $this->resetPage(); }
    public function updatedProductoId(): void { $this->resetPage(); }

    public function limpiarFiltros(): void
    {
        $this->busqueda   = '';
        $this->fechaDesde = now()->subDays(30)->toDateString();
        $this->fechaHasta = now()->toDateString();
        $this->tipo       = '';
        $this->origen     = '';
        $this->productoId = null;
        $this->resetPage();
    }

    public function getMovimientos(): LengthAwarePaginator
    {
        $empresaId = Filament::getTenant()->id;

        $q = Kardex::where('empresa_id', $empresaId)
            ->with(['user'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        if ($this->busqueda !== '') {
            $b = $this->busqueda;
            $q->where(function ($sub) use ($b) {
                $sub->where('producto_nombre', 'like', "%{$b}%")
                    ->orWhere('variante_nombre', 'like', "%{$b}%")
                    ->orWhere('concepto', 'like', "%{$b}%");
            });
        }

        if ($this->productoId) {
            $q->where('producto_id', $this->productoId);
        }

        if ($this->fechaDesde !== '') {
            $q->whereDate('fecha', '>=', $this->fechaDesde);
        }

        if ($this->fechaHasta !== '') {
            $q->whereDate('fecha', '<=', $this->fechaHasta);
        }

        if ($this->tipo !== '') {
            $q->where('tipo', $this->tipo);
        }

        if ($this->origen !== '') {
            $q->where('movible_type', $this->origen);
        }

        return $q->paginate(25);
    }

    public function getProductosParaFiltro(): Collection
    {
        return Producto::where('empresa_id', Filament::getTenant()->id)
            ->whereIn('estado', ['activo', 'inactivo', 'archivado'])
            ->orderByRaw("FIELD(estado,'activo','inactivo','archivado')")
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'estado']);
    }

    public function getResumen(): array
    {
        $empresaId = Filament::getTenant()->id;

        $q = Kardex::where('empresa_id', $empresaId);

        if ($this->busqueda !== '') {
            $b = $this->busqueda;
            $q->where(function ($sub) use ($b) {
                $sub->where('producto_nombre', 'like', "%{$b}%")
                    ->orWhere('variante_nombre', 'like', "%{$b}%")
                    ->orWhere('concepto', 'like', "%{$b}%");
            });
        }
        if ($this->productoId) $q->where('producto_id', $this->productoId);
        if ($this->fechaDesde !== '') $q->whereDate('fecha', '>=', $this->fechaDesde);
        if ($this->fechaHasta !== '') $q->whereDate('fecha', '<=', $this->fechaHasta);
        if ($this->tipo !== '') $q->where('tipo', $this->tipo);
        if ($this->origen !== '') $q->where('movible_type', $this->origen);

        return [
            'total'    => $q->count(),
            'entradas' => (clone $q)->where('tipo', 'entrada')->count(),
            'salidas'  => (clone $q)->where('tipo', 'salida')->count(),
        ];
    }
}
