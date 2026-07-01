<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use App\Filament\Pdv\Concerns\HasVentaDetalleModal;
use App\Filament\Pdv\Resources\Clientes\ClienteResource;
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

class ReporteClienteComprasPage extends Page implements HasForms
{
    use HasVentaDetalleModal;
    use InteractsWithForms;
    use WithPagination;

    protected string $view = 'filament.pdv.pages.reporte-cliente-compras';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 5;
    protected static ?string $title = 'Compras del cliente';
    protected static bool $shouldRegisterNavigation = false;

    public function getHeading(): string          { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }

    #[Url]
    public ?string $clienteNombre = null;
    #[Url]
    public ?string $clienteNumDoc = null;

    public ?string $filtroSerie       = null;
    public ?string $filtroCorrelativo = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getBreadcrumbs(): array
    {
        return [
            ClienteResource::getUrl('index') => 'Clientes',
            $this->clienteNombre ?? 'Compras',
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

    public function getCompras(): LengthAwarePaginator
    {
        return DB::table('ventas as v')
            ->join('series as s', 'v.serie_id', '=', 's.id')
            ->join('users as u', 'v.vendedor_id', '=', 'u.id')
            ->where('v.empresa_id', Filament::getTenant()->id)
            ->where('v.estado', EstadoVenta::Completada->value)
            ->where('v.cliente_nombre', $this->clienteNombre ?? '')
            ->when($this->clienteNumDoc, fn($q) => $q->where('v.cliente_num_doc', $this->clienteNumDoc))
            ->when($this->filtroSerie,       fn($q) => $q->where('s.serie', $this->filtroSerie))
            ->when($this->filtroCorrelativo, fn($q) => $q->where('v.correlativo', 'like', $this->filtroCorrelativo . '%'))
            ->selectRaw("
                v.id, s.serie, v.correlativo,
                v.total, v.igv, v.costo_total,
                v.monto_pagado, v.saldo_pendiente, v.estado_pago,
                v.total - v.igv - v.costo_total AS utilidad,
                u.name AS vendedor,
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
            ->where('v.cliente_nombre', $this->clienteNombre ?? '')
            ->when($this->clienteNumDoc, fn($q) => $q->where('v.cliente_num_doc', $this->clienteNumDoc))
            ->selectRaw("
                COUNT(*) AS cantidad,
                COALESCE(SUM(v.total), 0)           AS total_gastado,
                COALESCE(SUM(v.saldo_pendiente), 0) AS credito_pendiente
            ")
            ->first();

        return [
            'cantidad'         => (int) ($row->cantidad ?? 0),
            'totalGastado'     => (float) ($row->total_gastado ?? 0),
            'creditoPendiente' => (float) ($row->credito_pendiente ?? 0),
        ];
    }
}
