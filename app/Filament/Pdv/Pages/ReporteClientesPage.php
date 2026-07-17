<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Select;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use UnitEnum;

class ReporteClientesPage extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.reporte-clientes';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Reporte de Clientes';

    public static function canAccess(): bool { return Filament::getTenant()->tieneModulo('reporte_clientes') && (auth()->user()?->can('reportes.clientes') ?? false); }


    public ?string $filtroRango          = 'hoy';
    public ?string $filtroFechaDesde     = null;
    public ?string $filtroFechaHasta     = null;
    public ?string $filtroBuscarCliente  = null;

    public function mount(): void
    {
        $hoy = today()->toDateString();
        $this->filtroFechaDesde = $hoy;
        $this->filtroFechaHasta = $hoy;
        $this->form->fill([
            'filtroRango'      => 'hoy',
            'filtroFechaDesde' => $hoy,
            'filtroFechaHasta' => $hoy,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 2, 'md' => 5])->schema([

                Select::make('filtroRango')
                    ->label('Período')
                    ->options([
                        'hoy'         => 'Hoy',
                        'semana'      => 'Esta semana',
                        'mes'         => 'Este mes',
                        'personalizado' => 'Personalizado',
                    ])
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn(string $state) => $this->aplicarRango($state)),

                TextInput::make('filtroBuscarCliente')
                    ->label('Buscar cliente')
                    ->placeholder('Nombre o documento…')
                    ->prefixIcon('heroicon-o-magnifying-glass')
                    ->live(debounce: 400)
                    ->afterStateUpdated(fn() => $this->resetPage()),

                DatePicker::make('filtroFechaDesde')
                    ->label('Desde')->displayFormat('d/m/Y')
                    ->live()->afterStateUpdated(fn() => $this->resetPage())
                    ->hidden(fn() => $this->filtroRango !== 'personalizado'),

                DatePicker::make('filtroFechaHasta')
                    ->label('Hasta')->displayFormat('d/m/Y')
                    ->live()->afterStateUpdated(fn() => $this->resetPage())
                    ->hidden(fn() => $this->filtroRango !== 'personalizado'),

                Actions::make([
                    Action::make('limpiarFiltros')
                        ->label('Limpiar')
                        ->color('gray')->size('sm')->outlined()
                        ->icon('heroicon-o-x-mark')
                        ->visible(fn() => $this->hayFiltros())
                        ->action(fn() => $this->limpiarFiltros()),
                ])->verticallyAlignEnd(),

            ]),
        ]);
    }

    private function aplicarRango(string $rango): void
    {
        [$desde, $hasta] = match($rango) {
            'semana' => [today()->startOfWeek()->toDateString(), today()->endOfWeek()->toDateString()],
            'mes'    => [today()->startOfMonth()->toDateString(), today()->endOfMonth()->toDateString()],
            default  => [today()->toDateString(), today()->toDateString()],
        };

        if ($rango !== 'personalizado') {
            $this->filtroFechaDesde = $desde;
            $this->filtroFechaHasta = $hasta;
            $this->form->fill(['filtroFechaDesde' => $desde, 'filtroFechaHasta' => $hasta]);
        }

        $this->resetPage();
    }

    public function hayFiltros(): bool
    {
        return !empty($this->filtroBuscarCliente)
            || $this->filtroRango !== 'hoy';
    }

    public function limpiarFiltros(): void
    {
        $hoy = today()->toDateString();
        $this->filtroBuscarCliente = null;
        $this->filtroRango         = 'hoy';
        $this->filtroFechaDesde    = $hoy;
        $this->filtroFechaHasta    = $hoy;
        $this->form->fill([
            'filtroRango'          => 'hoy',
            'filtroBuscarCliente'  => null,
            'filtroFechaDesde'     => $hoy,
            'filtroFechaHasta'     => $hoy,
        ]);
        $this->resetPage();
    }

    private function baseQuery(): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('ventas as v')
            ->where('v.empresa_id', Filament::getTenant()->id)
            ->where('v.estado', EstadoVenta::Completada->value)
            ->where('v.cliente_nombre', '!=', '');

        if (!empty($this->filtroFechaDesde)) {
            $q->whereDate('v.created_at', '>=', $this->filtroFechaDesde);
        }
        if (!empty($this->filtroFechaHasta)) {
            $q->whereDate('v.created_at', '<=', $this->filtroFechaHasta);
        }
        if (!empty($this->filtroBuscarCliente)) {
            $term = '%' . $this->filtroBuscarCliente . '%';
            $q->where(fn($s) => $s
                ->where('v.cliente_nombre', 'like', $term)
                ->orWhere('v.cliente_num_doc', 'like', $term)
            );
        }

        return $q;
    }

    public function getResumen(): array
    {
        $row = (clone $this->baseQuery())
            ->selectRaw("
                COUNT(DISTINCT CONCAT(v.cliente_nombre, '|', v.cliente_num_doc)) AS total_clientes,
                COUNT(*)                              AS total_compras,
                COALESCE(SUM(v.total), 0)             AS total_gastado,
                COALESCE(SUM(v.saldo_pendiente), 0)   AS credito_pendiente
            ")
            ->first();

        return [
            'totalClientes'    => (int) ($row->total_clientes ?? 0),
            'totalCompras'     => (int) ($row->total_compras ?? 0),
            'totalGastado'     => (float) ($row->total_gastado ?? 0),
            'creditoPendiente' => (float) ($row->credito_pendiente ?? 0),
        ];
    }

    public function getClientes(): LengthAwarePaginator
    {
        return (clone $this->baseQuery())
            ->selectRaw("
                v.cliente_nombre                  AS cliente,
                v.cliente_num_doc                 AS num_doc,
                v.cliente_tipo_doc                AS tipo_doc,
                COUNT(*)                              AS compras,
                COALESCE(SUM(v.total), 0)             AS total_gastado,
                COALESCE(SUM(v.saldo_pendiente), 0)   AS credito_pendiente,
                MAX(v.created_at)                     AS ultima_compra
            ")
            ->groupBy('v.cliente_nombre', 'v.cliente_num_doc', 'v.cliente_tipo_doc')
            ->orderByDesc('total_gastado')
            ->paginate(25);
    }
}
