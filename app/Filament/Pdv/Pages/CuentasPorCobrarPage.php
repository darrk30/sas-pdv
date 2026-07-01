<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoMovimiento;
use App\Enums\TipoPago;
use App\Enums\TipoMovimiento;
use App\Filament\Pdv\Concerns\HasVentaDetalleModal;
use App\Filament\Pdv\Resources\Clientes\ClienteResource;
use App\Models\MetodoPago;
use App\Models\SesionCaja;
use App\Models\Transaccion;
use App\Models\Venta;
use App\Models\VentaPago;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use UnitEnum;

class CuentasPorCobrarPage extends Page implements HasForms
{
    use HasVentaDetalleModal;
    use InteractsWithForms;
    use WithPagination;

    protected string $view = 'filament.pdv.pages.cuentas-por-cobrar';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static string|UnitEnum|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 6;
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool { return auth()->user()?->can('reportes.cuentas_cobrar') ?? false; }

    public function getHeading(): string { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }

    // public function getTitle(): string
    // {
    //     return ! empty($this->filtroClienteNombre)
    //         ? "Créditos — {$this->filtroClienteNombre}"
    //         : 'Cuentas por Cobrar';
    // }

    public function getBreadcrumbs(): array
    {
        if (! empty($this->filtroClienteId)) {
            return [
                ClienteResource::getUrl('index') => 'Clientes',
                '#' => 'Créditos',
            ];
        }

        return parent::getBreadcrumbs();
    }

    // ── Filtros fijos (URL params) ────────────────────────────────────────────
    #[Url]
    public ?int $filtroClienteId = null;

    #[Url]
    public ?string $filtroClienteNombre = null;

    // ── Filtros interactivos ──────────────────────────────────────────────────
    public ?string $filtroCliente     = null;
    public ?string $filtroEstadoPago  = null; // '' | 'pendiente' | 'pagado'
    public ?string $filtroVencimiento = null; // '' | 'vigente' | 'vencida'
    public ?string $filtroFechaDesde  = null;
    public ?string $filtroFechaHasta  = null;

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
            return;
        }

        $this->filtroFechaDesde = now()->subDays(90)->toDateString();
        $this->filtroFechaHasta = now()->toDateString();
        $this->form->fill();
    }

    // ── Formulario de filtros ─────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        $clienteFijo = ! empty($this->filtroClienteId);
        $cols        = ['default' => 1, 'sm' => 2, 'lg' => $clienteFijo ? 4 : 5];

        $fields = [];

        if (! $clienteFijo) {
            $fields[] = TextInput::make('filtroCliente')
                ->label('Cliente')
                ->placeholder('Nombre o documento…')
                ->prefixIcon('heroicon-o-magnifying-glass')
                ->live(debounce: 300)
                ->afterStateUpdated(fn () => $this->resetPage());
        }

        $fields[] = Select::make('filtroEstadoPago')
            ->label('Estado pago')
            ->placeholder('Todos')
            ->options(['pendiente' => 'Pendientes', 'pagado' => 'Pagados'])
            ->native(false)
            ->live()
            ->afterStateUpdated(fn () => $this->resetPage());

        $fields[] = Select::make('filtroVencimiento')
            ->label('Vencimiento')
            ->placeholder('Todos')
            ->options(['vigente' => 'Vigentes', 'vencida' => 'Vencidas'])
            ->native(false)
            ->live()
            ->afterStateUpdated(fn () => $this->resetPage());

        $fields[] = DatePicker::make('filtroFechaDesde')
            ->label('Emisión desde')
            ->displayFormat('d/m/Y')
            ->live()
            ->afterStateUpdated(fn () => $this->resetPage());

        $fields[] = DatePicker::make('filtroFechaHasta')
            ->label('Emisión hasta')
            ->displayFormat('d/m/Y')
            ->live()
            ->afterStateUpdated(fn () => $this->resetPage());

        return $schema->components([
            Grid::make($cols)->schema($fields),
        ]);
    }

    public function hayFiltros(): bool
    {
        return (empty($this->filtroClienteId) && ! empty($this->filtroCliente))
            || ! empty($this->filtroEstadoPago)
            || ! empty($this->filtroVencimiento);
    }

    public function limpiarFiltros(): void
    {
        $this->filtroCliente     = null;
        $this->filtroEstadoPago  = null;
        $this->filtroVencimiento = null;
        $this->filtroFechaDesde  = now()->subDays(90)->toDateString();
        $this->filtroFechaHasta  = now()->toDateString();
        $this->form->fill();
        $this->resetPage();
    }

    // ── Query base ────────────────────────────────────────────────────────────

    private function baseQuery(): Builder
    {
        $q = Venta::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', 'completada')
            ->where('tipo_pago', TipoPago::Credito);

        // Filtro fijo por cliente (viene del resource de clientes)
        if (! empty($this->filtroClienteId)) {
            $q->where('cliente_id', $this->filtroClienteId);
        } elseif (! empty($this->filtroCliente)) {
            $b = $this->filtroCliente;
            $q->where(function ($sub) use ($b) {
                $sub->where('cliente_nombre', 'like', "%{$b}%")
                    ->orWhere('cliente_num_doc', 'like', "%{$b}%");
            });
        }

        if (! empty($this->filtroEstadoPago)) {
            $q->where('estado_pago', $this->filtroEstadoPago);
        }

        if ($this->filtroVencimiento === 'vigente') {
            $q->where(function ($sub) {
                $sub->whereNull('fecha_vencimiento')
                    ->orWhere('fecha_vencimiento', '>=', today());
            });
        } elseif ($this->filtroVencimiento === 'vencida') {
            $q->whereNotNull('fecha_vencimiento')
              ->where('fecha_vencimiento', '<', today());
        }

        if (! empty($this->filtroFechaDesde)) {
            $q->whereDate('fecha_emision', '>=', $this->filtroFechaDesde);
        }

        if (! empty($this->filtroFechaHasta)) {
            $q->whereDate('fecha_emision', '<=', $this->filtroFechaHasta);
        }

        return $q;
    }

    public function getVentas(): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->with(['serie'])
            ->orderByRaw('fecha_vencimiento IS NULL, fecha_vencimiento ASC')
            ->orderBy('fecha_emision', 'asc')
            ->paginate(20);
    }

    public function getResumen(): array
    {
        $row = (clone $this->baseQuery())->selectRaw('
            COUNT(*) as total_creditos,
            COALESCE(SUM(total), 0) as total_facturado,
            COALESCE(SUM(monto_pagado), 0) as total_cobrado,
            COALESCE(SUM(saldo_pendiente), 0) as total_pendiente,
            COALESCE(SUM(CASE WHEN estado_pago = "pendiente" AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURDATE() THEN 1 ELSE 0 END), 0) as cuentas_vencidas,
            COALESCE(SUM(CASE WHEN estado_pago = "pendiente" AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURDATE() THEN saldo_pendiente ELSE 0 END), 0) as monto_vencido
        ')->first();

        return [
            'total_creditos'   => (int)   ($row->total_creditos   ?? 0),
            'total_facturado'  => (float) ($row->total_facturado  ?? 0),
            'total_cobrado'    => (float) ($row->total_cobrado    ?? 0),
            'total_pendiente'  => (float) ($row->total_pendiente  ?? 0),
            'cuentas_vencidas' => (int)   ($row->cuentas_vencidas ?? 0),
            'monto_vencido'    => (float) ($row->monto_vencido    ?? 0),
        ];
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
        $venta = Venta::with('serie')
            ->where('empresa_id', Filament::getTenant()->id)
            ->findOrFail($ventaId);

        $pagos = VentaPago::with(['metodoPago:id,nombre,condicion_pago', 'sesionCaja:id,user_id', 'sesionCaja.cajero:id,name'])
            ->where('venta_id', $ventaId)
            ->orderBy('created_at', 'asc')
            ->get();

        $total  = (float) $venta->total;
        $pagado = (float) $venta->monto_pagado;

        $this->historialVenta = [
            'comprobante'      => $venta->serie->serie . '-' . $venta->correlativo,
            'cliente'          => $venta->cliente_nombre ?: 'Cliente general',
            'cliente_doc'      => $venta->cliente_num_doc,
            'total'            => $total,
            'monto_pagado'     => $pagado,
            'saldo_pendiente'  => (float) $venta->saldo_pendiente,
            'estado_pago'      => $venta->estado_pago,
            'fecha_emision'    => \Carbon\Carbon::parse($venta->fecha_emision)->format('d/m/Y'),
            'fecha_vencimiento'=> $venta->fecha_vencimiento?->format('d/m/Y'),
            'porcentaje'       => $total > 0 ? min(100, round(($pagado / $total) * 100, 1)) : 0,
        ];

        $this->historialPagos = $pagos
            ->filter(fn ($p) => $p->metodoPago?->condicion_pago !== \App\Enums\CondicionPago::Credito)
            ->map(fn ($p) => [
                'fecha'      => $p->created_at->format('d/m/Y'),
                'hora'       => $p->created_at->format('H:i'),
                'metodo'     => $p->metodoPago?->nombre ?? '—',
                'monto'      => (float) $p->monto,
                'referencia' => $p->referencia,
                'cajero'     => $p->sesionCaja?->cajero?->name ?? '—',
            ])->values()->toArray();

        $this->historialVentaId = $ventaId;
        $this->modalHistorial   = true;
    }

    public function cerrarModalHistorial(): void
    {
        $this->modalHistorial   = false;
        $this->historialVentaId = null;
        $this->historialVenta   = null;
        $this->historialPagos   = [];
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
    }

    public function cerrarModal(): void
    {
        $this->modalCobro = false;
        $this->ventaId    = null;
        $this->ventaModal = null;
        $this->resetErrorBag();
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
                ->where('estado_pago', 'pendiente')
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
                'estado_pago'     => $nuevoSaldo <= 0 ? 'pagado' : 'pendiente',
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

        Notification::make()
            ->title('Cobro registrado correctamente')
            ->success()
            ->send();
    }
}
