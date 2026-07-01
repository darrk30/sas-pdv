<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use App\Models\Serie;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use UnitEnum;

class ReporteVendedorVentasPage extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected string $view = 'filament.pdv.pages.reporte-vendedor-ventas';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 4;
    protected static ?string $title = 'Ventas del vendedor';
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool { return auth()->user()?->can('caja.reporte_vendedores') ?? false; }

    public function getHeading(): string          { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }

    #[Url]
    public ?int $vendedorId = null;
    #[Url]
    public ?string $vendedorNombre = null;
    #[Url]
    public ?string $fechaDesde = null;
    #[Url]
    public ?string $fechaHasta = null;

    public ?string $filtroSerie       = null;
    public ?string $filtroCorrelativo = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getBreadcrumbs(): array
    {
        return [
            ReporteVendedoresPage::getUrl() => 'Vendedores',
            $this->vendedorNombre ?? 'Ventas',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 2])->schema([

                Select::make('filtroSerie')
                    ->label('Serie')
                    ->placeholder('Todas las series')
                    ->options(fn() => Serie::where('empresa_id', Filament::getTenant()->id)
                        ->orderBy('serie')->pluck('serie', 'serie')->toArray())
                    ->native(false)->searchable()
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

                TextInput::make('filtroCorrelativo')
                    ->label('Correlativo')
                    ->placeholder('Ej: 00001')
                    ->live(debounce: 400)
                    ->afterStateUpdated(fn() => $this->resetPage()),

            ]),
        ]);
    }

    public function limpiarFiltros(): void
    {
        $this->filtroSerie       = null;
        $this->filtroCorrelativo = null;
        $this->form->fill();
        $this->resetPage();
    }

    public function hayFiltros(): bool
    {
        return !empty($this->filtroSerie) || !empty($this->filtroCorrelativo);
    }

    public function getVentas(): LengthAwarePaginator
    {
        return DB::table('ventas as v')
            ->join('series as s', 'v.serie_id', '=', 's.id')
            ->where('v.empresa_id', Filament::getTenant()->id)
            ->where('v.estado', EstadoVenta::Completada->value)
            ->where('v.vendedor_id', $this->vendedorId)
            ->when($this->fechaDesde, fn($q) => $q->whereDate('v.created_at', '>=', $this->fechaDesde))
            ->when($this->fechaHasta, fn($q) => $q->whereDate('v.created_at', '<=', $this->fechaHasta))
            ->when($this->filtroSerie,       fn($q) => $q->where('s.serie', $this->filtroSerie))
            ->when($this->filtroCorrelativo, fn($q) => $q->where('v.correlativo', 'like', $this->filtroCorrelativo . '%'))
            ->selectRaw("
                v.id, s.serie, v.correlativo,
                v.cliente_nombre, v.total, v.igv, v.costo_total,
                v.monto_pagado, v.saldo_pendiente, v.estado_pago,
                v.total - v.igv - v.costo_total AS utilidad,
                v.created_at
            ")
            ->orderByDesc('v.created_at')
            ->paginate(25);
    }

    public function getResumen(): array
    {
        $row = DB::table('ventas as v')
            ->where('v.empresa_id', Filament::getTenant()->id)
            ->where('v.estado', EstadoVenta::Completada->value)
            ->where('v.vendedor_id', $this->vendedorId)
            ->when($this->fechaDesde, fn($q) => $q->whereDate('v.created_at', '>=', $this->fechaDesde))
            ->when($this->fechaHasta, fn($q) => $q->whereDate('v.created_at', '<=', $this->fechaHasta))
            ->selectRaw("
                COUNT(*) AS cantidad,
                COALESCE(SUM(v.monto_pagado), 0)                  AS cobrado,
                COALESCE(SUM(v.saldo_pendiente), 0)               AS credito_pendiente,
                COALESCE(SUM(v.total - v.igv - v.costo_total), 0) AS utilidad
            ")
            ->first();

        return [
            'cantidad'         => (int) ($row->cantidad ?? 0),
            'cobrado'          => (float) ($row->cobrado ?? 0),
            'creditoPendiente' => (float) ($row->credito_pendiente ?? 0),
            'utilidad'         => (float) ($row->utilidad ?? 0),
        ];
    }
}
