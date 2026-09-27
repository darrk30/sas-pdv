<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use App\Filament\Pdv\Concerns\HasVentaDetalleModal;
use App\Filament\Pdv\Resources\Clientes\ClienteResource;
use App\Models\Serie;
use App\Models\Venta;
use BackedEnum;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Pages\Page;
use Filament\Actions\Action as TableAction;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use UnitEnum;

class ReporteClienteComprasPage extends Page implements HasTable
{
    use HasVentaDetalleModal;
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.reporte-cliente-compras';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 5;
    protected static ?string $title = 'Compras del cliente';
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool { return Filament::getTenant()->tieneModulo('clientes') && (auth()->user()?->can('reportes.clientes') ?? false); }

    protected function getHeaderWidgets(): array
    {
        return [\App\Filament\Pdv\Widgets\ReporteClienteComprasStatsWidget::class];
    }

    public function getWidgetData(): array
    {
        $resumen = $this->getResumen();
        $resumen['clienteNombre'] = $this->clienteNombre;
        return ['statsData' => $resumen];
    }

    #[Url]
    public ?int    $clienteId     = null;
    #[Url]
    public ?string $clienteNombre = null;
    #[Url]
    public ?string $clienteNumDoc = null;

    public function getBreadcrumbs(): array
    {
        return [
            ClienteResource::getUrl('index') => 'Clientes',
            $this->clienteNombre ?? 'Compras',
        ];
    }

    public function getResumen(): array
    {
        $row = Venta::query()
            ->where('empresa_id', Filament::getTenant()->id)
            ->where('estado', EstadoVenta::Completada->value)
            ->where(function ($q) {
                if ($this->clienteId) {
                    $q->where('cliente_id', $this->clienteId)
                      ->orWhere(function ($sub) {
                          $sub->whereNull('cliente_id')
                              ->where('cliente_nombre', $this->clienteNombre ?? '')
                              ->when($this->clienteNumDoc, fn($s) => $s->where('cliente_num_doc', $this->clienteNumDoc));
                      });
                } else {
                    $q->where('cliente_nombre', $this->clienteNombre ?? '')
                      ->when($this->clienteNumDoc, fn($s) => $s->where('cliente_num_doc', $this->clienteNumDoc));
                }
            })
            ->selectRaw("
                COUNT(*) AS cantidad,
                COALESCE(SUM(total), 0)           AS total_gastado,
                COALESCE(SUM(saldo_pendiente), 0) AS credito_pendiente
            ")
            ->first();

        return [
            'cantidad'         => (int) ($row->cantidad ?? 0),
            'totalGastado'     => (float) ($row->total_gastado ?? 0),
            'creditoPendiente' => (float) ($row->credito_pendiente ?? 0),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => $this->buildComprasQuery())
            ->filters([
                Filter::make('serie')
                    ->form([
                        \Filament\Forms\Components\Select::make('serie')
                            ->label('Serie')
                            ->placeholder('Todas las series')
                            ->options(fn() => \App\Models\Serie::where('empresa_id', \Filament\Facades\Filament::getTenant()->id)
                                ->orderBy('serie')->pluck('serie', 'serie')->toArray())
                            ->native(false)
                            ->searchable(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when($data['serie'] ?? null, fn($q, $v) => $q->where('s.serie', $v))
                    )
                    ->indicateUsing(fn (array $data): ?string =>
                        ($data['serie'] ?? null) ? 'Serie: ' . $data['serie'] : null
                    ),

                Filter::make('correlativo')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('correlativo')
                            ->label('Correlativo')
                            ->placeholder('Ej: 00001'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when($data['correlativo'] ?? null, fn($q, $v) => $q->where('ventas.correlativo', 'like', $v . '%'))
                    )
                    ->indicateUsing(fn (array $data): ?string =>
                        ($data['correlativo'] ?? null) ? 'Correlativo: ' . $data['correlativo'] : null
                    ),
            ])
            ->columns([
                TextColumn::make('serie')
                    ->label('Comprobante')
                    ->formatStateUsing(fn($state, $record): string => "{$state}-{$record->correlativo}")
                    ->fontFamily('mono')
                    ->weight('semibold'),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->formatStateUsing(fn($state): string =>
                        Carbon::parse($state)->format('d/m/Y') . "\n" . Carbon::parse($state)->format('H:i')
                    )
                    ->wrap(),

                TextColumn::make('vendedor')
                    ->label('Vendedor')
                    ->color('gray')
                    ->limit(20),

                TextColumn::make('estado_pago')
                    ->label('Pago')
                    ->badge()
                    ->color(fn($state): string => match((string) $state) {
                        'pendiente' => 'warning',
                        'parcial'   => 'info',
                        default     => 'success',
                    })
                    ->formatStateUsing(fn($state): string => match((string) $state) {
                        'pendiente' => 'Crédito',
                        'parcial'   => 'Parcial',
                        default     => 'Contado',
                    })
                    ->description(fn($record): ?string =>
                        in_array($record->estado_pago, ['pendiente', 'parcial'])
                            ? 'pend. S/ ' . number_format((float) $record->saldo_pendiente, 2)
                            : null
                    ),

                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn($state): string => 'S/ ' . number_format((float) $state, 2))
                    ->alignRight(),

                TextColumn::make('igv')
                    ->label('IGV')
                    ->formatStateUsing(fn($state): string =>
                        (float) $state > 0 ? 'S/ ' . number_format((float) $state, 2) : '—'
                    )
                    ->alignRight()
                    ->color('gray'),

                TextColumn::make('costo_total')
                    ->label('Costo')
                    ->formatStateUsing(fn($state): string => 'S/ ' . number_format((float) $state, 2))
                    ->alignRight()
                    ->color('warning'),

                TextColumn::make('utilidad')
                    ->label('Utilidad')
                    ->formatStateUsing(fn($state): string => 'S/ ' . number_format((float) $state, 2))
                    ->alignRight()
                    ->color(fn($state): string => (float) $state >= 0 ? 'success' : 'danger'),
            ])
            ->actions([
                TableAction::make('verDetalle')
                    ->label('Ver')
                    ->icon('heroicon-m-eye')
                    ->size('sm')
                    ->action(fn(Venta $record) => $this->abrirModalDetalle($record->id)),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Sin compras')
            ->emptyStateDescription('No se encontraron compras con los filtros seleccionados.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }

    private function buildComprasQuery(): Builder
    {
        return Venta::query()
            ->join('series as s', 'ventas.serie_id', '=', 's.id')
            ->join('users as u', 'ventas.vendedor_id', '=', 'u.id')
            ->selectRaw("
                ventas.id,
                s.serie,
                ventas.correlativo,
                ventas.total,
                ventas.igv,
                ventas.costo_total,
                ventas.monto_pagado,
                ventas.saldo_pendiente,
                ventas.estado_pago,
                ventas.total - ventas.igv - ventas.costo_total AS utilidad,
                u.name AS vendedor,
                ventas.created_at
            ")
            ->where('ventas.empresa_id', Filament::getTenant()->id)
            ->where('ventas.estado', EstadoVenta::Completada->value)
            ->where(function ($q) {
                if ($this->clienteId) {
                    $q->where('ventas.cliente_id', $this->clienteId)
                      ->orWhere(function ($sub) {
                          $sub->whereNull('ventas.cliente_id')
                              ->where('ventas.cliente_nombre', $this->clienteNombre ?? '')
                              ->when($this->clienteNumDoc, fn($s) => $s->where('ventas.cliente_num_doc', $this->clienteNumDoc));
                      });
                } else {
                    $q->where('ventas.cliente_nombre', $this->clienteNombre ?? '')
                      ->when($this->clienteNumDoc, fn($s) => $s->where('ventas.cliente_num_doc', $this->clienteNumDoc));
                }
            })
            ->orderByDesc('ventas.created_at');
    }
}
