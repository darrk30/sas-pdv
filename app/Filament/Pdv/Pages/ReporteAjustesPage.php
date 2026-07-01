<?php

namespace App\Filament\Pdv\Pages;

use App\Models\Ajuste;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use UnitEnum;

class ReporteAjustesPage extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected string $view = 'filament.pdv.pages.reporte-ajustes';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Reporte de Ajustes';
    protected static string|UnitEnum|null $navigationGroup = 'Productos';
    protected static ?int $navigationSort = 8;
    protected static ?string $title = 'Reporte de Ajustes de Stock';

    public function getHeading(): string          { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }

    // ── Filtros ───────────────────────────────────────────────────────────────

    public ?string $filtroRango    = 'hoy';
    public ?string $filtroFechaDesde = null;
    public ?string $filtroFechaHasta = null;
    public ?string $filtroUsuario  = null;
    public ?string $filtroTipo     = null;
    public ?string $filtroEstado   = null;
    public ?string $filtroCodigo   = null;

    // ── Estado de modal ───────────────────────────────────────────────────────

    public ?int $ajusteDetalleId = null;

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
            Grid::make(['default' => 1, 'sm' => 2, 'md' => 4])->schema([

                Select::make('filtroRango')
                    ->label('Período')
                    ->options(['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes', 'personalizado' => 'Personalizado'])
                    ->native(false)
                    ->live()->afterStateUpdated(fn(?string $state) => $state ? $this->aplicarRango($state) : null),

                Select::make('filtroUsuario')
                    ->label('Responsable')
                    ->placeholder('Todos')
                    ->options(fn() => DB::table('users')
                        ->join('ajustes', 'users.id', '=', 'ajustes.user_id')
                        ->where('ajustes.empresa_id', Filament::getTenant()->id)
                        ->distinct()->orderBy('users.name')
                        ->pluck('users.name', 'users.id')->toArray())
                    ->native(false)->searchable()
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

                Select::make('filtroTipo')
                    ->label('Tipo')
                    ->placeholder('Todos')
                    ->options(['entrada' => 'Entrada', 'salida' => 'Salida'])
                    ->native(false)
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

                Select::make('filtroEstado')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->options(['borrador' => 'Borrador', 'confirmado' => 'Confirmado'])
                    ->native(false)
                    ->live()->afterStateUpdated(fn() => $this->resetPage()),

                TextInput::make('filtroCodigo')
                    ->label('Código')
                    ->placeholder('Ej: AJ-0001')
                    ->live(debounce: 400)->afterStateUpdated(fn() => $this->resetPage()),

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
        return $this->filtroRango !== 'hoy'
            || !empty($this->filtroUsuario)
            || !empty($this->filtroTipo)
            || !empty($this->filtroEstado)
            || !empty($this->filtroCodigo);
    }

    public function limpiarFiltros(): void
    {
        $hoy = today()->toDateString();
        $this->filtroRango      = 'hoy';
        $this->filtroFechaDesde = $hoy;
        $this->filtroFechaHasta = $hoy;
        $this->filtroUsuario    = null;
        $this->filtroTipo       = null;
        $this->filtroEstado     = null;
        $this->filtroCodigo     = null;
        $this->form->fill([
            'filtroRango' => 'hoy', 'filtroFechaDesde' => $hoy, 'filtroFechaHasta' => $hoy,
            'filtroUsuario' => null, 'filtroTipo' => null, 'filtroEstado' => null, 'filtroCodigo' => null,
        ]);
        $this->resetPage();
    }

    // ── Query base ────────────────────────────────────────────────────────────

    private function aplicarFiltros(\Illuminate\Database\Eloquent\Builder $q): void
    {
        $q->where('ajustes.empresa_id', Filament::getTenant()->id);

        if (!empty($this->filtroFechaDesde)) {
            $q->whereDate('ajustes.created_at', '>=', $this->filtroFechaDesde);
        }
        if (!empty($this->filtroFechaHasta)) {
            $q->whereDate('ajustes.created_at', '<=', $this->filtroFechaHasta);
        }
        if (!empty($this->filtroUsuario)) {
            $q->where('ajustes.user_id', $this->filtroUsuario);
        }
        if (!empty($this->filtroTipo)) {
            $q->where('ajustes.tipo', $this->filtroTipo);
        }
        if (!empty($this->filtroEstado)) {
            $q->where('ajustes.estado', $this->filtroEstado);
        }
        if (!empty($this->filtroCodigo)) {
            $q->where('ajustes.codigo', 'like', $this->filtroCodigo . '%');
        }
    }

    // ── Listado paginado ──────────────────────────────────────────────────────

    public function getAjustes(): LengthAwarePaginator
    {
        $q = Ajuste::with(['responsable:id,name'])
            ->withCount('detalles')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $this->aplicarFiltros($q);

        return $q->paginate(25);
    }

    // ── Resumen ───────────────────────────────────────────────────────────────

    public function getResumen(): array
    {
        $q = Ajuste::query();
        $this->aplicarFiltros($q);

        return [
            'cantidad'  => (clone $q)->count(),
            'entradas'  => (clone $q)->where('tipo', 'entrada')->count(),
            'salidas'   => (clone $q)->where('tipo', 'salida')->count(),
            'valorTotal' => (float) (clone $q)->sum('valor_total'),
        ];
    }

    // ── Modal detalle ─────────────────────────────────────────────────────────

    public function abrirDetalle(int $id): void { $this->ajusteDetalleId = $id; }
    public function cerrarDetalle(): void        { $this->ajusteDetalleId = null; }

    public function getAjusteDetalle(): ?Ajuste
    {
        if (!$this->ajusteDetalleId) return null;

        return Ajuste::with([
            'responsable:id,name',
            'detalles.unidad:id,simbolo',
        ])->find($this->ajusteDetalleId);
    }
}
