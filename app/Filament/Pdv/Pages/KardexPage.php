<?php

namespace App\Filament\Pdv\Pages;

use App\Models\Kardex;
use App\Models\Producto;
use App\Services\KardexExportService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class KardexPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.kardex';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;
    protected static ?string $navigationLabel = 'Kardex';
    protected static string|UnitEnum|null $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Kardex de Inventario';

    public static function canAccess(): bool
    {
        return Filament::getTenant()->tieneModulo('kardex')
            && (auth()->user()?->can('inventario.kardex') ?? false);
    }

    public function getHeading(): string { return static::$title ?? ''; }

    // ── Filtros ───────────────────────────────────────────────────────────────

    public ?string $filtroProducto   = null;
    public ?string $filtroFechaDesde = null;
    public ?string $filtroFechaHasta = null;
    public ?string $filtroTipo       = null;
    public ?string $filtroOrigen     = null;

    public function mount(): void
    {
        $this->form->fill([
            'filtroFechaDesde' => now()->toDateString(),
            'filtroFechaHasta' => now()->toDateString(),
        ]);
    }

    // ── Header Widgets ────────────────────────────────────────────────────────

    protected function getHeaderWidgets(): array
    {
        return [\App\Filament\Pdv\Widgets\KardexStatsWidget::class];
    }

    public function getWidgetData(): array
    {
        return ['statsData' => $this->getResumen()];
    }

    // ── Form ──────────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Filtros')
                ->description('Filtra los movimientos del kardex.')
                ->columns(1)
                ->collapsible()
                ->collapsed(true)
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])->schema([

                        Select::make('filtroProducto')
                            ->label('Producto / Variante')
                            ->placeholder('Todos los productos')
                            ->options(fn () => $this->opcionesProductos())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        DatePicker::make('filtroFechaDesde')
                            ->label('Desde')
                            ->displayFormat('d/m/Y')
                            ->live(),

                        DatePicker::make('filtroFechaHasta')
                            ->label('Hasta')
                            ->displayFormat('d/m/Y')
                            ->live(),

                        Select::make('filtroTipo')
                            ->label('Tipo')
                            ->placeholder('Todos')
                            ->options(['entrada' => 'Entrada', 'salida' => 'Salida'])
                            ->native(false)
                            ->live(),

                        Select::make('filtroOrigen')
                            ->label('Origen')
                            ->placeholder('Todos')
                            ->options([
                                'App\\Models\\Ajuste' => 'Ajuste',
                                'App\\Models\\Compra' => 'Compra',
                                'App\\Models\\Venta'  => 'Venta',
                            ])
                            ->native(false)
                            ->live(),

                    ]),
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
        $this->form->fill([
            'filtroProducto'   => null,
            'filtroFechaDesde' => now()->toDateString(),
            'filtroFechaHasta' => now()->toDateString(),
            'filtroTipo'       => null,
            'filtroOrigen'     => null,
        ]);
    }

    // ── Tabla Filament ────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->buildTableQuery())
            ->defaultSort('fecha', 'desc')
            ->toolbarActions([
                Action::make('exportarExcel')
                    ->label('Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->action(fn () => $this->exportarExcel()),

                Action::make('exportarPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(fn () => $this->exportarPdf()),
            ])
            ->columns([

                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->formatStateUsing(fn (string $state): string => Carbon::parse($state)->format('d/m/Y'))
                    ->description(fn (Kardex $record): string => Carbon::parse($record->fecha)->format('H:i'))
                    ->sortable(),

                TextColumn::make('producto_nombre')
                    ->label('Producto')
                    ->description(fn (Kardex $record): ?string =>
                        $record->producto?->codigo_interno ?: null
                    )
                    ->wrap(),

                TextColumn::make('codigo_barras')
                    ->label('Cód. Barras')
                    ->state(fn (Kardex $record): string =>
                        $record->variante?->codigo_barras
                            ?: ($record->producto?->codigo_barras ?: '—')
                    ),

                TextColumn::make('concepto')
                    ->label('Concepto')
                    ->html()
                    ->state(fn (Kardex $record): string =>
                        e($record->concepto)
                        . ($record->notas
                            ? '<div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">' . e($record->notas) . '</div>'
                            : '')
                        . ($record->movible_type === 'App\\Models\\Venta'
                            && $record->tipo === 'salida'
                            && (float) ($record->precio_unitario ?? 0) == 0
                            ? '<div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Cortesía</div>'
                            : '')
                    )
                    ->wrap()
                    ->limit(50),

                TextColumn::make('movible_type')
                    ->label('Origen')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'App\\Models\\Ajuste' => 'Ajuste',
                        'App\\Models\\Compra' => 'Compra',
                        'App\\Models\\Venta'  => 'Venta',
                        default => '—',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'App\\Models\\Ajuste' => 'warning',
                        'App\\Models\\Compra' => 'info',
                        'App\\Models\\Venta'  => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => $state === 'entrada' ? 'success' : 'danger')
                    ->icon(fn (string $state): string => $state === 'entrada' ? 'heroicon-m-arrow-down' : 'heroicon-m-arrow-up'),

                TextColumn::make('cantidad')
                    ->label('Cantidad')
                    ->state(fn (Kardex $record): string =>
                        ($record->tipo === 'entrada' ? '+' : '−')
                        . number_format((float) $record->cantidad, 2)
                        . ' ' . ($record->unidad_simbolo ?? $record->unidad ?? '')
                    )
                    ->description(fn (Kardex $record): ?string =>
                        ((float) ($record->factor_conversion ?? 0) && (float) $record->factor_conversion != 1)
                            ? '= ' . number_format((float) $record->cantidad_base, 2) . ' uds.'
                            : null
                    )
                    ->color(fn (Kardex $record): string => $record->tipo === 'entrada' ? 'success' : 'danger')
                    ->alignEnd(),

                TextColumn::make('costo_unitario')
                    ->label('Costo / Precio')
                    ->state(fn (Kardex $record): string => $record->tipo === 'entrada'
                        ? ($record->costo_unitario !== null
                            ? 'S/ ' . number_format((float) $record->costo_unitario, 2)
                            : '—')
                        : ($record->precio_unitario !== null
                            ? 'S/ ' . number_format((float) $record->precio_unitario, 2)
                            : '—')
                    )
                    ->description(fn (Kardex $record): ?string => $record->tipo === 'entrada'
                        ? ($record->costo_total !== null
                            ? 'Total: S/ ' . number_format((float) $record->costo_total, 2)
                            : null)
                        : ($record->precio_total !== null
                            ? 'Total: S/ ' . number_format((float) $record->precio_total, 2)
                            : null)
                    )
                    ->alignEnd(),

                TextColumn::make('stock_despues')
                    ->label('Stock')
                    ->html()
                    ->state(fn (Kardex $record): string => sprintf(
                        '<span class="text-gray-400 dark:text-gray-500">%s</span> → <span class="%s">%s</span>',
                        number_format((float) $record->stock_antes, 2),
                        (float) $record->stock_despues >= (float) $record->stock_antes
                            ? 'text-success-600 dark:text-success-400'
                            : 'text-danger-600 dark:text-danger-400',
                        number_format((float) $record->stock_despues, 2)
                    ))
                    ->alignCenter(),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->default('Sistema')
                    ->limit(20),

            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Sin movimientos')
            ->emptyStateDescription('No hay movimientos para los filtros seleccionados.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }

    // ── Query ─────────────────────────────────────────────────────────────────

    private function buildTableQuery(): Builder
    {
        $empresaId = Filament::getTenant()->id;

        $q = Kardex::where('kardex.empresa_id', $empresaId)
            ->with(['user:id,name', 'producto:id,codigo_interno,codigo_barras', 'variante:id,codigo,codigo_barras'])
            ->leftJoin('unidades_medidas as um', function ($join) use ($empresaId) {
                $join->on('um.nombre', '=', 'kardex.unidad')
                     ->where('um.empresa_id', $empresaId);
            })
            ->select('kardex.*', 'um.simbolo as unidad_simbolo');

        $this->aplicarFiltros($q);

        return $q;
    }

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

    // ── KPIs ──────────────────────────────────────────────────────────────────

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

    // ── Opciones productos ────────────────────────────────────────────────────

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

    // ── Exportación ───────────────────────────────────────────────────────────

    private function getFiltros(): array
    {
        return [
            'producto' => $this->filtroProducto,
            'desde'    => $this->filtroFechaDesde,
            'hasta'    => $this->filtroFechaHasta,
            'tipo'     => $this->filtroTipo,
            'origen'   => $this->filtroOrigen,
        ];
    }

    public function exportarExcel(): mixed
    {
        return app(KardexExportService::class)
            ->generarExcel(Filament::getTenant(), $this->getFiltros(), $this->getResumen());
    }

    public function exportarPdf(): mixed
    {
        return app(KardexExportService::class)
            ->generarPdf(Filament::getTenant(), $this->getFiltros(), $this->getResumen());
    }
}
