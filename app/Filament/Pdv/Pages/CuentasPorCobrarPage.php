<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoMovimiento;
use App\Enums\TipoPago;
use App\Enums\TipoMovimiento;
use App\Filament\Pdv\Concerns\HasVentaDetalleModal;
use App\Filament\Pdv\Resources\Clientes\ClienteResource;
use App\Filament\Pdv\Widgets\CreditosClienteStatsWidget;
use App\Models\MetodoPago;
use App\Models\SesionCaja;
use App\Models\Transaccion;
use App\Models\Venta;
use App\Models\VentaPago;
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

class CuentasPorCobrarPage extends Page implements HasTable
{
    use HasVentaDetalleModal;
    use InteractsWithTable;
    use HasFullWidthPage;

    protected string $view = 'filament.pdv.pages.cuentas-por-cobrar';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wallet';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 5;
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        $empresa = Filament::getTenant();
        return $empresa->tieneFeature('cuentas')
            && (auth()->user()?->can('reportes.cuentas_cobrar') ?? false);
    }

    public static function getNavigationBadge(): ?string
    {
        $empresaId = Filament::getTenant()?->id;
        if (! $empresaId) return null;

        $count = cache()->remember("badge_creditos_vencidos_{$empresaId}", 60, fn () =>
            \App\Models\Venta::where('empresa_id', $empresaId)
                ->whereNotNull('fecha_vencimiento')
                ->whereDate('fecha_vencimiento', '<', today())
                ->where('saldo_pendiente', '>', 0)
                ->count()
        );

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Créditos vencidos sin cobrar';
    }

    public function getTitle(): string|Htmlable
    {
        return ! empty($this->filtroClienteNombre)
            ? "Créditos — {$this->filtroClienteNombre}"
            : 'Cuentas por Cobrar';
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
        if (! empty($this->filtroClienteId)) {
            $label = ! empty($this->filtroClienteNombre)
                ? "Créditos ({$this->filtroClienteNombre})"
                : 'Créditos';

            return [
                ClienteResource::getUrl('index') => 'Clientes',
                '#' => $label,
            ];
        }

        return parent::getBreadcrumbs();
    }

    public function getHeaderWidgets(): array
    {
        return [CreditosClienteStatsWidget::class];
    }

    public function getWidgetData(): array
    {
        return ['filtroClienteId' => $this->filtroClienteId];
    }

    // ── Filtros fijos (URL params) ────────────────────────────────────────────
    #[Url]
    public ?int $filtroClienteId = null;

    #[Url]
    public ?string $filtroClienteNombre = null;

    // ── Modal de historial ────────────────────────────────────────────────────
    public bool   $modalHistorial   = false;
    public ?int   $historialVentaId = null;
    public ?array $historialVenta   = null;
    public array  $historialPagos   = [];

    // ── Modal de cobro ────────────────────────────────────────────────────────
    public bool    $modalCobro  = false;
    public ?int    $ventaId     = null;
    public ?array  $ventaModal  = null;
    public ?int    $cobroMetodo = null;
    public string  $cobroMonto  = '';
    public string  $cobroRef    = '';

    public function mount(): void
    {
        if (empty($this->filtroClienteId)) {
            $this->redirect(ClienteResource::getUrl('index'));
        }
    }

    // ── Query base ────────────────────────────────────────────────────────────

    private function baseQuery(): Builder
    {
        $q = Venta::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', 'completada')
            ->where(function ($sub) {
                $sub->where('tipo_pago', TipoPago::Credito)
                    ->orWhere('estado_pago', 'parcial');
            });

        if (! empty($this->filtroClienteId)) {
            $q->where('cliente_id', $this->filtroClienteId);
        }

        return $q;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                $this->baseQuery()
                    ->with('serie')
                    ->orderByRaw('fecha_vencimiento IS NULL, fecha_vencimiento ASC')
                    ->orderBy('fecha_emision', 'asc')
            )
            ->columns([
                TextColumn::make('fecha_emision')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->description(fn (Venta $record): string =>
                        \Carbon\Carbon::parse($record->fecha_emision)->format('H:i')
                    )
                    ->sortable(),

                TextColumn::make('comprobante')
                    ->label('Comprobante')
                    ->state(fn (Venta $record): string =>
                        ($record->serie->serie ?? '?') . '-' . $record->correlativo
                    )
                    ->weight('bold')
                    ->copyable()
                    ->searchable(query: fn (Builder $query, string $search): Builder =>
                        $query->where(function ($q) use ($search) {
                            $num = preg_replace('/[^0-9]/', '', $search);
                            if ($num !== '') {
                                $q->where('correlativo', 'like', '%' . ltrim($num, '0') . '%');
                            }
                            $q->orWhereHas('serie', fn ($sq) =>
                                $sq->where('serie', 'like', '%' . $search . '%')
                            );
                        })
                    ),

                TextColumn::make('cliente_nombre')
                    ->label('Cliente')
                    ->description(fn (Venta $record): ?string =>
                        $record->cliente_num_doc
                            ? ($record->cliente_tipo_doc . ' ' . $record->cliente_num_doc)
                            : null
                    )
                    ->wrap(),

                TextColumn::make('estado_pago')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'pendiente' => 'Pendiente',
                        'pagado'    => 'Pagado',
                        'parcial'   => 'Parcial',
                        default     => ucfirst((string) $state),
                    })
                    ->color(fn ($state): string => match ($state) {
                        'pendiente' => 'warning',
                        'pagado'    => 'success',
                        'parcial'   => 'info',
                        default     => 'gray',
                    }),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('monto_pagado')
                    ->label('Pagado')
                    ->money('PEN')
                    ->alignRight()
                    ->color('success'),

                TextColumn::make('saldo_pendiente')
                    ->label('Pendiente')
                    ->money('PEN')
                    ->alignRight()
                    ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success')
                    ->weight('bold'),

                TextColumn::make('fecha_vencimiento')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->badge()
                    ->color(fn ($state, Venta $record): string =>
                        $record->fecha_vencimiento?->isPast() ? 'danger' : 'gray'
                    )
                    ->placeholder('Sin fecha'),
            ])
            ->filters([
                SelectFilter::make('estado_pago')
                    ->label('Estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'pagado'    => 'Pagado',
                        'parcial'   => 'Parcial',
                    ])
                    ->placeholder('Todos')
                    ->native(false),

                Filter::make('vencimiento')
                    ->form([
                        Select::make('vencimiento')
                            ->label('Vencimiento')
                            ->options(['vigente' => 'Vigente', 'vencida' => 'Vencida'])
                            ->placeholder('Todos')
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['vencimiento'] ?? '') {
                        'vigente' => $query->where(fn ($q) => $q->whereNull('fecha_vencimiento')->orWhere('fecha_vencimiento', '>=', today())),
                        'vencida' => $query->whereNotNull('fecha_vencimiento')->where('fecha_vencimiento', '<', today()),
                        default   => $query,
                    })
                    ->indicateUsing(fn (array $data): array => filled($data['vencimiento'] ?? null)
                        ? ['Vencimiento: ' . ($data['vencimiento'] === 'vigente' ? 'Vigente' : 'Vencida')]
                        : []
                    ),

                Filter::make('fecha_emision')
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
                        ->when($data['desde'] ?? null, fn ($q) => $q->whereDate('fecha_emision', '>=', $data['desde']))
                        ->when($data['hasta'] ?? null, fn ($q) => $q->whereDate('fecha_emision', '<=', $data['hasta']))
                    )
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        ($data['desde'] ?? null) ? 'Desde: ' . $data['desde'] : null,
                        ($data['hasta'] ?? null) ? 'Hasta: ' . $data['hasta'] : null,
                    ]))),
            ])
            ->searchPlaceholder('Buscar por comprobante…')
            ->recordActions([
                ActionGroup::make([
                    Action::make('ver')
                        ->label('Ver detalle')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->action(fn (Venta $record) => $this->abrirModalDetalle($record->id)),

                    Action::make('historial')
                        ->label('Historial de pagos')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->action(fn (Venta $record) => $this->abrirModalHistorial($record->id)),

                    Action::make('cobrar')
                        ->label('Registrar cobro')
                        ->icon('heroicon-o-banknotes')
                        ->color('warning')
                        ->visible(fn (Venta $record): bool =>
                            in_array($record->estado_pago, ['pendiente', 'parcial'])
                        )
                        ->action(fn (Venta $record) => $this->abrirModalCobro($record->id)),
                ]),
            ])
            ->striped()
            ->paginated([15, 25, 50]);
    }

    public function getMetodosPago(): Collection
    {
        return MetodoPago::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', 'activo')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    // ── Modal de historial ────────────────────────────────────────────────────

    public function abrirModalHistorial(int $ventaId): void
    {
        $venta = Venta::with(['serie', 'vendedor:id,name'])
            ->where('empresa_id', Filament::getTenant()->id)
            ->findOrFail($ventaId);

        $pagos = VentaPago::with(['metodoPago:id,nombre,condicion_pago', 'sesionCaja:id,user_id', 'sesionCaja.cajero:id,name'])
            ->where('venta_id', $ventaId)
            ->orderBy('created_at', 'asc')
            ->get();

        $total  = (float) $venta->total;
        $pagado = (float) $venta->monto_pagado;

        $this->historialVenta = [
            'comprobante'       => $venta->serie->serie . '-' . $venta->correlativo,
            'cliente'           => $venta->cliente_nombre ?: 'Cliente general',
            'cliente_doc'       => $venta->cliente_num_doc,
            'total'             => $total,
            'monto_pagado'      => $pagado,
            'saldo_pendiente'   => (float) $venta->saldo_pendiente,
            'estado_pago'       => $venta->estado_pago,
            'fecha_emision'     => \Carbon\Carbon::parse($venta->fecha_emision)->format('d/m/Y'),
            'fecha_vencimiento' => $venta->fecha_vencimiento?->format('d/m/Y'),
            'porcentaje'        => $total > 0 ? min(100, round(($pagado / $total) * 100, 1)) : 0,
        ];

        $this->historialPagos = $pagos
            ->filter(fn ($p) => $p->metodoPago?->condicion_pago !== \App\Enums\CondicionPago::Credito)
            ->map(fn ($p) => [
                'fecha'      => $p->created_at->format('d/m/Y'),
                'hora'       => $p->created_at->format('H:i'),
                'metodo'     => $p->metodoPago?->nombre ?? '—',
                'monto'      => (float) $p->monto,
                'referencia' => $p->referencia,
                'cajero'     => $p->sesionCaja?->cajero?->name ?? $venta->vendedor?->name ?? '—',
            ])->values()->toArray();

        $this->historialVentaId = $ventaId;
        $this->modalHistorial   = true;
        $this->dispatch('open-modal', id: 'historial-modal');
    }

    public function cerrarModalHistorial(): void
    {
        $this->modalHistorial   = false;
        $this->historialVentaId = null;
        $this->historialVenta   = null;
        $this->historialPagos   = [];
        $this->dispatch('close-modal', id: 'historial-modal');
    }

    public function cobrarDesdeHistorial(int $ventaId): void
    {
        $this->cerrarModalHistorial();
        $this->abrirModalCobro($ventaId);
    }

    // ── Modal de cobro ────────────────────────────────────────────────────────

    public function abrirModalCobro(int $ventaId): void
    {
        $venta = Venta::with('serie')
            ->where('empresa_id', Filament::getTenant()->id)
            ->findOrFail($ventaId);

        $this->ventaModal = [
            'id'              => $venta->id,
            'comprobante'     => $venta->serie->serie . '-' . $venta->correlativo,
            'cliente'         => $venta->cliente_nombre ?: 'Cliente general',
            'cliente_doc'     => $venta->cliente_num_doc,
            'total'           => (float) $venta->total,
            'monto_pagado'    => (float) $venta->monto_pagado,
            'saldo_pendiente' => (float) $venta->saldo_pendiente,
            'vencimiento'     => $venta->fecha_vencimiento?->format('d/m/Y'),
            'es_vencida'      => $venta->fecha_vencimiento && $venta->fecha_vencimiento->isPast(),
        ];

        $this->ventaId     = $ventaId;
        $this->cobroMetodo = null;
        $this->cobroMonto  = number_format((float) $venta->saldo_pendiente, 2, '.', '');
        $this->cobroRef    = '';
        $this->modalCobro  = true;
        $this->dispatch('open-modal', id: 'cobro-modal');
    }

    public function cerrarModal(): void
    {
        $this->modalCobro = false;
        $this->ventaId    = null;
        $this->ventaModal = null;
        $this->resetErrorBag();
        $this->dispatch('close-modal', id: 'cobro-modal');
    }

    public function registrarCobro(): void
    {
        $saldoMax = (float) ($this->ventaModal['saldo_pendiente'] ?? 0);

        $this->validate([
            'cobroMetodo' => 'required|integer|exists:metodos_pago,id',
            'cobroMonto'  => ['required', 'numeric', 'gt:0', "max:{$saldoMax}"],
        ], [
            'cobroMetodo.required' => 'Selecciona un método de pago.',
            'cobroMonto.required'  => 'Ingresa el monto a cobrar.',
            'cobroMonto.gt'        => 'El monto debe ser mayor a 0.',
            'cobroMonto.max'       => 'El monto no puede superar el saldo pendiente (S/ ' . number_format($saldoMax, 2) . ').',
        ]);

        $monto = (float) $this->cobroMonto;

        DB::transaction(function () use ($monto) {
            $empresaId = Filament::getTenant()->id;

            $venta = Venta::where('empresa_id', $empresaId)
                ->whereIn('estado_pago', ['pendiente', 'parcial'])
                ->with('serie')
                ->lockForUpdate()
                ->findOrFail($this->ventaId);

            $sesionCaja = SesionCaja::where('empresa_id', $empresaId)
                ->where('user_id', auth()->id())
                ->where('estado', 'abierta')
                ->first();

            VentaPago::create([
                'venta_id'       => $venta->id,
                'sesion_caja_id' => $sesionCaja?->id,
                'metodo_pago_id' => $this->cobroMetodo,
                'monto'          => $monto,
                'referencia'     => $this->cobroRef ?: null,
            ]);

            $nuevoPagado = round((float) $venta->monto_pagado + $monto, 2);
            $nuevoSaldo  = round(max(0, (float) $venta->total - $nuevoPagado), 2);

            $venta->update([
                'monto_pagado'    => $nuevoPagado,
                'saldo_pendiente' => $nuevoSaldo,
                'estado_pago'     => $nuevoSaldo <= 0 ? 'pagado' : $venta->estado_pago,
            ]);

            if ($sesionCaja) {
                $comprobante = $venta->serie->serie . '-' . $venta->correlativo;
                Transaccion::create([
                    'empresa_id'           => $empresaId,
                    'sesion_caja_id'       => $sesionCaja->id,
                    'transaccionable_type' => Venta::class,
                    'transaccionable_id'   => $venta->id,
                    'tipo'                 => TipoMovimiento::Ingreso,
                    'concepto'             => "Cobro crédito {$comprobante}",
                    'monto'                => $monto,
                    'metodo_pago_id'       => $this->cobroMetodo,
                    'estado'               => EstadoMovimiento::Aprobado,
                    'fecha'                => now(),
                ]);
            }
        });

        $this->modalCobro = false;
        $this->ventaId    = null;
        $this->ventaModal = null;
        $this->dispatch('close-modal', id: 'cobro-modal');

        Notification::make()
            ->title('Cobro registrado correctamente')
            ->success()
            ->send();
    }
}
