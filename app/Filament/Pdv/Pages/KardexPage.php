<?php

namespace App\Filament\Pdv\Pages;

use App\Models\Kardex;
use App\Models\Producto;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;
use UnitEnum;

class KardexPage extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected string $view = 'filament.pdv.pages.kardex';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;
    protected static ?string $navigationLabel = 'Kardex';
    protected static string|UnitEnum|null $navigationGroup = 'Inventario';
    protected static ?int    $navigationSort  = 10;
    protected static ?string $title = 'Kardex de Inventario';

    public function getHeading(): string { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }

    // ── Filtros ───────────────────────────────────────────────────────────────
    // filtroProducto: "p:{id}" para producto simple | "v:{id}" para variante
    public ?string $filtroProducto   = null;
    public ?string $filtroFechaDesde = null;
    public ?string $filtroFechaHasta = null;
    public ?string $filtroTipo       = null;
    public ?string $filtroOrigen     = null;

    public function mount(): void
    {
        $this->filtroFechaDesde = now()->subDays(30)->toDateString();
        $this->filtroFechaHasta = now()->toDateString();
        $this->form->fill();
    }

    // ── Form Filament ─────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])->schema([

                Select::make('filtroProducto')
                    ->label('Producto / Variante')
                    ->placeholder('Todos los productos')
                    ->options(fn () => $this->opcionesProductos())
                    ->native(false)
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetPage()),

                DatePicker::make('filtroFechaDesde')
                    ->label('Desde')
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetPage()),

                DatePicker::make('filtroFechaHasta')
                    ->label('Hasta')
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetPage()),

                Select::make('filtroTipo')
                    ->label('Tipo')
                    ->placeholder('Todos')
                    ->options(['entrada' => 'Entrada', 'salida' => 'Salida'])
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetPage()),

                Select::make('filtroOrigen')
                    ->label('Origen')
                    ->placeholder('Todos')
                    ->options([
                        'App\\Models\\Ajuste' => 'Ajuste',
                        'App\\Models\\Compra' => 'Compra',
                        'App\\Models\\Venta'  => 'Venta',
                    ])
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetPage()),

            ]),
        ]);
    }

    public function hayFiltros(): bool
    {
        return ! empty($this->filtroProducto)
            || ! empty($this->filtroTipo)
            || ! empty($this->filtroOrigen);
    }

    public function limpiarFiltros(): void
    {
        $this->filtroProducto   = null;
        $this->filtroFechaDesde = now()->subDays(30)->toDateString();
        $this->filtroFechaHasta = now()->toDateString();
        $this->filtroTipo       = null;
        $this->filtroOrigen     = null;
        $this->form->fill();
        $this->resetPage();
    }

    // ── Opciones para el Select de productos ─────────────────────────────────

    private function opcionesProductos(): array
    {
        $empresaId = Filament::getTenant()->id;

        $productos = Producto::where('empresa_id', $empresaId)
            ->whereIn('estado', ['activo', 'inactivo', 'archivado'])
            ->with(['variantes' => fn ($q) => $q->with('valores.valor')])
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'estado']);

        $options = [];

        foreach ($productos as $producto) {
            $estadoVal = $producto->estado instanceof BackedEnum
                ? $producto->estado->value
                : (string) $producto->estado;
            $sufijo = $estadoVal !== 'activo' ? " ({$estadoVal})" : '';

            if ($producto->variantes->isEmpty()) {
                $options["p:{$producto->id}"] = $producto->nombre . $sufijo;
            } else {
                foreach ($producto->variantes as $variante) {
                    $valores = $variante->valores
                        ->map(fn ($pav) => $pav->valor->nombre ?? '')
                        ->filter()
                        ->implode(' - ');

                    $label = $valores
                        ? "{$producto->nombre} ({$valores}){$sufijo}"
                        : "{$producto->nombre}{$sufijo}";

                    $options["v:{$variante->id}"] = $label;
                }
            }
        }

        return $options;
    }

    // ── Aplicar filtros a query ───────────────────────────────────────────────

    private function aplicarFiltros(Builder $q): void
    {
        if (! empty($this->filtroProducto)) {
            [$tipo, $id] = explode(':', $this->filtroProducto, 2);
            if ($tipo === 'v') {
                $q->where('variante_id', (int) $id);
            } else {
                $q->where('producto_id', (int) $id)->whereNull('variante_id');
            }
        }

        if (! empty($this->filtroFechaDesde)) {
            $q->whereDate('fecha', '>=', $this->filtroFechaDesde);
        }

        if (! empty($this->filtroFechaHasta)) {
            $q->whereDate('fecha', '<=', $this->filtroFechaHasta);
        }

        if (! empty($this->filtroTipo)) {
            $q->where('tipo', $this->filtroTipo);
        }

        if (! empty($this->filtroOrigen)) {
            $q->where('movible_type', $this->filtroOrigen);
        }
    }

    public function getMovimientos(): LengthAwarePaginator
    {
        $q = Kardex::where('empresa_id', Filament::getTenant()->id)
            ->with(['user'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        $this->aplicarFiltros($q);

        return $q->paginate(25);
    }

    public function getResumen(): array
    {
        $q = Kardex::where('empresa_id', Filament::getTenant()->id);
        $this->aplicarFiltros($q);

        return [
            'total'    => $q->count(),
            'entradas' => (clone $q)->where('tipo', 'entrada')->count(),
            'salidas'  => (clone $q)->where('tipo', 'salida')->count(),
        ];
    }
}
