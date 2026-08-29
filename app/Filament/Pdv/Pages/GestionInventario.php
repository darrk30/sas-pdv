<?php

namespace App\Filament\Pdv\Pages;

use App\Models\AjusteDetalle;
use App\Models\Inventario;
use App\Services\InventarioExportService;
use BackedEnum;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use UnitEnum;

class GestionInventario extends Page implements HasTable
{
    use InteractsWithTable;
    use HasFullWidthPage;

    public function mount(): void
    {
        if ($q = request()->query('tableSearch')) {
            $this->tableSearch = $q;
        }

        $this->js(<<<'JS'
            if (!window.__scannerInventario) {
                window.__scannerInventario = true;
                var buf = '', last = 0;
                document.addEventListener('keydown', function (e) {
                    var a = document.activeElement;
                    if (a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.isContentEditable)) {
                        buf = ''; return;
                    }
                    if (e.key === 'Enter') {
                        if (buf.length >= 4) {
                            var code = buf; buf = '';
                            Livewire.dispatch('barcode-result', { path: 'inventario_barcode_filter', code: code });
                        } else { buf = ''; }
                        return;
                    }
                    var now = Date.now();
                    if (now - last > 60 && buf.length > 0) buf = '';
                    last = now;
                    if (e.key.length === 1) buf += e.key;
                });
            }
        JS);
    }

    public function getStats(): array
    {
        $empresaId = Filament::getTenant()->id;

        $counts = Inventario::query()
            ->where('empresa_id', $empresaId)
            ->where('estado_almacen', 'activo')
            ->whereHas('producto', fn(Builder $q) => $q->where('estado', '!=', 'archivado'))
            ->selectRaw("
                SUM(CASE WHEN estado_inventario = 'disponible'   THEN 1 ELSE 0 END) AS disponible,
                SUM(CASE WHEN estado_inventario = 'por_agotarse' THEN 1 ELSE 0 END) AS por_agotarse,
                SUM(CASE WHEN estado_inventario = 'agotado'      THEN 1 ELSE 0 END) AS agotado,
                COUNT(*) AS total
            ")
            ->first();

        return [
            'disponible'   => (int) ($counts->disponible   ?? 0),
            'por_agotarse' => (int) ($counts->por_agotarse ?? 0),
            'agotado'      => (int) ($counts->agotado      ?? 0),
            'total'        => (int) ($counts->total        ?? 0),
        ];
    }

    #[On('barcode-result')]
    public function filtrarPorBarcode(string $path, string $code): void
    {
        if ($path === 'inventario_barcode_filter') {
            $this->tableSearch = $code;
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    // Estas propiedades de navegación SÍ son estáticas en Filament
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Inventario';

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';
    protected static ?int $navigationSort = 2;
    
    // ELIMINAMOS 'static' de aquí para evitar el Error Fatal de PHP
    protected string $view = 'filament.pdv.pages.gestion-inventario';
    
    // Usamos $heading (no estático) en lugar de $title para evitar posibles choques similares
    protected ?string $heading = 'Inventario Activo';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Inventario::query()
                    ->where('estado_almacen', 'activo')
                    ->whereHas('producto', fn(Builder $q) => $q->where('estado', '!=', 'archivado'))
                    ->with([
                        'producto',
                        'variante.valores.valor',
                        'variante.producto',
                    ])
            )
            ->columns([
                TextColumn::make('producto.nombre')
                    ->label('Producto')
                    ->formatStateUsing(function (string $state, Inventario $record): string {
                        if ($record->variante_id && $record->variante) {
                            return AjusteDetalle::generarNombre(null, $record->variante);
                        }
                        return $state;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            // Busca por nombre del producto (cubre simples y variantes)
                            $q->whereHas('producto', fn(Builder $pq) =>
                                $pq->where('nombre', 'like', "%{$search}%")
                            )
                            // Busca por valor de atributo: Rojo, S, M, etc.
                            ->orWhereHas('variante.valores.valor', fn(Builder $vq) =>
                                $vq->where('nombre', 'like', "%{$search}%")
                            );
                        });
                    })
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('codigo')
                    ->label('Código')
                    ->state(function (Inventario $record): string {
                        if ($record->variante_id && $record->variante) {
                            return $record->variante->codigo ?? '—';
                        }
                        return $record->producto?->codigo_interno ?? '—';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->whereHas('variante', fn(Builder $vq) =>
                                $vq->where('codigo', 'like', "%{$search}%")
                            )
                            ->orWhereHas('producto', fn(Builder $pq) =>
                                $pq->where('codigo_interno', 'like', "%{$search}%")
                                   ->orWhere('codigo_barras', 'like', "%{$search}%")
                            );
                        });
                    })
                    ->copyable()
                    ->color('gray')
                    ->fontFamily('mono'),

                TextColumn::make('producto.codigo_barras')
                    ->label('Cód. Barras')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stock_real')
                    ->label('Stock Actual')
                    ->numeric()
                    ->sortable()
                    ->alignRight(),

                TextColumn::make('stock_minimo')
                    ->label('Min.')
                    ->numeric()
                    ->alignRight()
                    ->color('gray'),

                TextColumn::make('estado_inventario')
                    ->label('Estado')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('estado_inventario')
                    ->label('Estado de stock')
                    ->options([
                        'agotado'      => 'Agotado',
                        'por_agotarse' => 'Por agotarse',
                        'disponible'   => 'Disponible',
                    ])
                    ->multiple()
                    ->placeholder('Todos los estados'),
            ])
            ->toolbarActions([
                Action::make('escanear_barcode')
                    ->label('')
                    ->icon('heroicon-o-camera')
                    ->color('gray')
                    ->extraAttributes([
                        'x-on:click' => "window.dispatchEvent(new CustomEvent('open-barcode-scanner', { detail: { path: 'inventario_barcode_filter' } }))",
                    ]),

                Action::make('exportarExcel')
                    ->label('Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->action(fn() => app(InventarioExportService::class)
                        ->generarExcel(Filament::getTenant(), $this->getStats())),

                Action::make('exportarPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(fn() => app(InventarioExportService::class)
                        ->generarPdf(Filament::getTenant(), $this->getStats())),
            ])
            ->recordActions([]);
    }

    public static function canAccess(): bool
    {
        return Filament::getTenant()->tieneModulo('gestion_inventario') && (auth()->user()?->can('inventario.ver') ?? false);
    }
}