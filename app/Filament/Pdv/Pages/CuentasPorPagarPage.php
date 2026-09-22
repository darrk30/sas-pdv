<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\CondicionPago;
use App\Enums\EstadoGeneral;
use App\Enums\EstadoPago;
use App\Filament\Pdv\Resources\Proveedores\ProveedorResource;
use App\Models\Compra;
use App\Models\CompraPago;
use App\Models\MetodoPago;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use UnitEnum;

class CuentasPorPagarPage extends Page implements HasTable
{
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.cuentas-por-pagar';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 6;
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('compras.ver') ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return ! empty($this->filtroProveedorNombre)
            ? "Cuentas por Pagar — {$this->filtroProveedorNombre}"
            : 'Cuentas por Pagar';
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        if (! empty($this->filtroProveedorId)) {
            $label = ! empty($this->filtroProveedorNombre)
                ? "Pagos ({$this->filtroProveedorNombre})"
                : 'Pagos';

            return [
                ProveedorResource::getUrl('index') => 'Proveedores',
                '#' => $label,
            ];
        }

        return parent::getBreadcrumbs();
    }

    // ── Filtros fijos (URL params) ────────────────────────────────────────────
    #[Url]
    public ?int $filtroProveedorId = null;

    #[Url]
    public ?string $filtroProveedorNombre = null;

    // ── Modal de historial ────────────────────────────────────────────────────
    public bool   $modalHistorial    = false;
    public ?int   $historialCompraId = null;
    public ?array $historialCompra   = null;
    public array  $historialPagos    = [];

    // ── Modal de pago ─────────────────────────────────────────────────────────
    public bool    $modalPago   = false;
    public ?int    $compraId    = null;
    public ?array  $compraModal = null;
    public ?int    $pagoMetodo  = null;
    public string  $pagoMonto   = '';
    public string  $pagoRef     = '';

    public function mount(): void
    {
        if (empty($this->filtroProveedorId)) {
            $this->redirect(ProveedorResource::getUrl('index'));
        }
    }

    // ── Query base ────────────────────────────────────────────────────────────

    private function baseQuery(): Builder
    {
        $q = Compra::where('empresa_id', Filament::getTenant()->id)
            ->whereIn('estado_pago', [EstadoPago::Pendiente->value, EstadoPago::Parcial->value])
            ->withSum('pagos', 'monto');

        if (! empty($this->filtroProveedorId)) {
            $q->where('proveedor_id', $this->filtroProveedorId);
        }

        return $q;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                $this->baseQuery()
                    ->orderBy('fecha_compra', 'desc')
            )
            ->columns([
                TextColumn::make('fecha_compra')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('codigo')
                    ->label('Comprobante')
                    ->weight('bold')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('tipo_comprobante')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'factura'         => 'Factura',
                        'boleta'          => 'Boleta',
                        'sin_comprobante' => 'Sin comp.',
                        default           => ucfirst((string) $state),
                    })
                    ->color('gray'),

                TextColumn::make('estado_pago')
                    ->label('Estado pago')
                    ->badge()
                    ->formatStateUsing(fn ($state): string =>
                        EstadoPago::tryFrom((string) $state)?->getLabel() ?? ucfirst((string) $state)
                    )
                    ->color(fn ($state): string =>
                        EstadoPago::tryFrom((string) $state)?->getColor() ?? 'gray'
                    ),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('pagos_sum_monto')
                    ->label('Pagado')
                    ->state(fn (Compra $record): float => (float) ($record->pagos_sum_monto ?? 0))
                    ->money('PEN')
                    ->alignRight()
                    ->color('success'),

                TextColumn::make('saldo_pendiente')
                    ->label('Saldo')
                    ->state(fn (Compra $record): float =>
                        max(0, (float) $record->total - (float) ($record->pagos_sum_monto ?? 0))
                    )
                    ->money('PEN')
                    ->alignRight()
                    ->color(fn (Compra $record): string =>
                        max(0, (float) $record->total - (float) ($record->pagos_sum_monto ?? 0)) > 0
                            ? 'danger'
                            : 'success'
                    )
                    ->weight('bold'),
            ])
            ->filters([
                SelectFilter::make('estado_pago')
                    ->label('Estado pago')
                    ->options([
                        EstadoPago::Pendiente->value => EstadoPago::Pendiente->getLabel(),
                        EstadoPago::Parcial->value   => EstadoPago::Parcial->getLabel(),
                    ])
                    ->placeholder('Todos (sin pagadas)')
                    ->native(false),

                Filter::make('fecha_compra')
                    ->label('Período')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->displayFormat('d/m/Y')
                            ->default(now()->subDays(90)->toDateString()),
                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->displayFormat('d/m/Y')
                            ->default(now()->toDateString()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn ($q) => $q->whereDate('fecha_compra', '>=', $data['desde']))
                        ->when($data['hasta'] ?? null, fn ($q) => $q->whereDate('fecha_compra', '<=', $data['hasta']))
                    )
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        ($data['desde'] ?? null) ? 'Desde: ' . $data['desde'] : null,
                        ($data['hasta'] ?? null) ? 'Hasta: ' . $data['hasta'] : null,
                    ]))),
            ])
            ->searchPlaceholder('Buscar por comprobante…')
            ->recordActions([
                ActionGroup::make([
                    Action::make('historial')
                        ->label('Historial de pagos')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->action(fn (Compra $record) => $this->abrirModalHistorial($record->id)),

                    Action::make('pagar')
                        ->label('Registrar pago')
                        ->icon('heroicon-o-banknotes')
                        ->color('warning')
                        ->visible(fn (Compra $record): bool =>
                            in_array($record->estado_pago, [EstadoPago::Pendiente->value, EstadoPago::Parcial->value])
                        )
                        ->action(fn (Compra $record) => $this->abrirModalPago($record->id)),
                ]),
            ])
            ->striped()
            ->paginated([15, 25, 50]);
    }

    public function getMetodosPago(): Collection
    {
        return MetodoPago::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', EstadoGeneral::Activo->value)
            ->where(function ($q) {
                $q->whereNull('condicion_pago')
                  ->orWhere('condicion_pago', '!=', CondicionPago::Credito->value);
            })
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    // ── Modal de historial ────────────────────────────────────────────────────

    public function abrirModalHistorial(int $compraId): void
    {
        $compra = Compra::with(['proveedor:id,nombre'])
            ->where('empresa_id', Filament::getTenant()->id)
            ->findOrFail($compraId);

        $pagos = CompraPago::with(['metodoPago:id,nombre'])
            ->where('compra_id', $compraId)
            ->orderBy('created_at', 'asc')
            ->get();

        $total  = (float) $compra->total;
        $pagado = (float) $pagos->sum('monto');
        $saldo  = max(0, $total - $pagado);

        $this->historialCompra = [
            'comprobante'     => $compra->codigo,
            'proveedor'       => $compra->proveedor?->nombre ?? '—',
            'total'           => $total,
            'monto_pagado'    => $pagado,
            'saldo_pendiente' => $saldo,
            'estado_pago'     => $compra->estado_pago,
            'fecha_compra'    => \Carbon\Carbon::parse($compra->fecha_compra)->format('d/m/Y'),
            'porcentaje'      => $total > 0 ? min(100, round(($pagado / $total) * 100, 1)) : 0,
        ];

        $this->historialPagos = $pagos->map(fn ($p) => [
            'fecha'      => $p->created_at->format('d/m/Y'),
            'hora'       => $p->created_at->format('H:i'),
            'metodo'     => $p->metodoPago?->nombre ?? '—',
            'monto'      => (float) $p->monto,
            'referencia' => $p->referencia,
        ])->values()->toArray();

        $this->historialCompraId = $compraId;
        $this->modalHistorial    = true;
        $this->dispatch('open-modal', id: 'historial-pagar-modal');
    }

    public function cerrarModalHistorial(): void
    {
        $this->modalHistorial    = false;
        $this->historialCompraId = null;
        $this->historialCompra   = null;
        $this->historialPagos    = [];
        $this->dispatch('close-modal', id: 'historial-pagar-modal');
    }

    public function pagarDesdeHistorial(int $compraId): void
    {
        $this->cerrarModalHistorial();
        $this->abrirModalPago($compraId);
    }

    // ── Modal de pago ─────────────────────────────────────────────────────────

    public function abrirModalPago(int $compraId): void
    {
        $compra = Compra::with(['proveedor:id,nombre'])
            ->where('empresa_id', Filament::getTenant()->id)
            ->findOrFail($compraId);

        $pagado = (float) $compra->pagos()->sum('monto');
        $saldo  = max(0, (float) $compra->total - $pagado);

        $this->compraModal = [
            'id'              => $compra->id,
            'comprobante'     => $compra->codigo,
            'proveedor'       => $compra->proveedor?->nombre ?? '—',
            'total'           => (float) $compra->total,
            'monto_pagado'    => $pagado,
            'saldo_pendiente' => $saldo,
        ];

        $this->compraId   = $compraId;
        $this->pagoMetodo = null;
        $this->pagoMonto  = number_format($saldo, 2, '.', '');
        $this->pagoRef    = '';
        $this->modalPago  = true;
        $this->dispatch('open-modal', id: 'pago-modal');
    }

    public function cerrarModalPago(): void
    {
        $this->modalPago  = false;
        $this->compraId   = null;
        $this->compraModal = null;
        $this->resetErrorBag();
        $this->dispatch('close-modal', id: 'pago-modal');
    }

    public function registrarPago(): void
    {
        $saldoMax = (float) ($this->compraModal['saldo_pendiente'] ?? 0);

        $this->validate([
            'pagoMetodo' => 'required|integer|exists:metodos_pago,id',
            'pagoMonto'  => ['required', 'numeric', 'gt:0', "max:{$saldoMax}"],
        ], [
            'pagoMetodo.required' => 'Selecciona un método de pago.',
            'pagoMonto.required'  => 'Ingresa el monto a pagar.',
            'pagoMonto.gt'        => 'El monto debe ser mayor a 0.',
            'pagoMonto.max'       => 'El monto no puede superar el saldo pendiente (S/ ' . number_format($saldoMax, 2) . ').',
        ]);

        $monto = (float) $this->pagoMonto;

        DB::transaction(function () use ($monto) {
            $empresaId = Filament::getTenant()->id;

            $compra = Compra::where('empresa_id', $empresaId)
                ->whereIn('estado_pago', [EstadoPago::Pendiente->value, EstadoPago::Parcial->value])
                ->lockForUpdate()
                ->findOrFail($this->compraId);

            CompraPago::create([
                'empresa_id'     => $empresaId,
                'user_id'        => auth()->id(),
                'compra_id'      => $compra->id,
                'metodo_pago_id' => $this->pagoMetodo,
                'monto'          => $monto,
                'referencia'     => $this->pagoRef ?: null,
            ]);

            $nuevoPagado = round((float) $compra->pagos()->sum('monto'), 2);
            $nuevoSaldo  = round(max(0, (float) $compra->total - $nuevoPagado), 2);

            $compra->update([
                'estado_pago' => $nuevoSaldo <= 0
                    ? EstadoPago::Pagado->value
                    : EstadoPago::Parcial->value,
            ]);
        });

        $this->modalPago  = false;
        $this->compraId   = null;
        $this->compraModal = null;
        $this->dispatch('close-modal', id: 'pago-modal');

        Notification::make()
            ->title('Pago registrado correctamente')
            ->success()
            ->send();
    }
}
