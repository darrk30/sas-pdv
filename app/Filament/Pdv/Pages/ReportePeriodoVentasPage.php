<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use App\Models\Serie;
use BackedEnum;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use UnitEnum;

class ReportePeriodoVentasPage extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.reporte-periodo-ventas';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Ventas del período';
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool { return Filament::getTenant()->tieneModulo('ventas_periodo') && (auth()->user()?->can('reportes.ventas_periodo') ?? false); }


    #[Url]
    public ?string $periodo = null;
    #[Url]
    public ?string $agrupacion = 'dia';

    public ?string $filtroSerie       = null;
    public ?string $filtroCorrelativo = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getBreadcrumbs(): array
    {
        return [
            ReporteVentasPeriodoPage::getUrl() => 'Ventas por período',
            $this->getPeriodoLabel(),
        ];
    }

    public function getPeriodoLabel(): string
    {
        if (!$this->periodo) {
            return 'Detalle';
        }

        if ($this->agrupacion === 'mes') {
            $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril',
                      '05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto',
                      '09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
            [$year, $month] = explode('-', $this->periodo);
            return ($meses[$month] ?? $month) . ' ' . $year;
        }

        return Carbon::parse($this->periodo)->format('d/m/Y');
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

    private function baseQuery(): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('ventas as v')
            ->join('series as s', 'v.serie_id', '=', 's.id')
            ->join('users as u', 'v.vendedor_id', '=', 'u.id')
            ->where('v.empresa_id', Filament::getTenant()->id)
            ->where('v.estado', EstadoVenta::Completada->value);

        if ($this->periodo) {
            if ($this->agrupacion === 'mes') {
                $q->whereRaw("DATE_FORMAT(v.created_at, '%Y-%m') = ?", [$this->periodo]);
            } else {
                $q->whereDate('v.created_at', $this->periodo);
            }
        }

        return $q;
    }

    public function getVentas(): LengthAwarePaginator
    {
        return (clone $this->baseQuery())
            ->when($this->filtroSerie,       fn($q) => $q->where('s.serie', $this->filtroSerie))
            ->when($this->filtroCorrelativo, fn($q) => $q->where('v.correlativo', 'like', $this->filtroCorrelativo . '%'))
            ->selectRaw("
                v.id, s.serie, v.correlativo,
                v.cliente_nombre, v.total, v.igv, v.costo_total,
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
        $row = (clone $this->baseQuery())
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
