<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoVenta;
use App\Enums\TipoPago;
use App\Filament\Pdv\Concerns\HasVentaDetalleModal;
use App\Models\Venta;
use BackedEnum;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use UnitEnum;

class DespachoPage extends Page implements HasForms
{
    use HasVentaDetalleModal;
    use InteractsWithForms;
    use WithPagination;

    protected string $view = 'filament.pdv.pages.despacho';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Despachos';
    protected static string|UnitEnum|null $navigationGroup = 'Caja';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Despachos pendientes';

    public function getHeading(): string          { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }

    public static function getNavigationBadge(): ?string
    {
        $empresaId = Filament::getTenant()?->id;
        if (! $empresaId) return null;

        $count = Venta::where('empresa_id', $empresaId)
            ->whereNotNull('estado_despacho')
            ->whereNotIn('estado_despacho', ['entregado'])
            ->where('estado', EstadoVenta::Completada)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    // ── Filtros ───────────────────────────────────────────────────────────────
    public ?string $filtroCliente    = null;
    public ?string $filtroFechaDesde = null;
    public ?string $filtroFechaHasta = null;

    // ── Modal cambiar estado ──────────────────────────────────────────────────
    public bool    $modalEstado    = false;
    public ?int    $estadoVentaId  = null;
    public ?string $nuevoEstado    = null;
    public ?array  $estadoVenta    = null;

    // ── Máquina de estados ────────────────────────────────────────────────────

    private const ORDEN = [
        'pendiente_envio',
        'en_preparacion',
        'en_agencia',
        'en_camino',
        'entregado',     // terminal: guarda NULL en la base de datos
    ];

    private const META = [
        'pendiente_envio' => ['label' => 'Pendiente',      'css' => 'gray'],
        'en_preparacion'  => ['label' => 'En preparación', 'css' => 'blue'],
        'en_agencia'      => ['label' => 'En agencia',     'css' => 'purple'],
        'en_camino'       => ['label' => 'En camino',      'css' => 'amber'],
        'entregado'       => ['label' => 'Entregado',      'css' => 'green'],
    ];

    /** Retorna los estados disponibles para avanzar desde $actual. */
    public static function siguientesEstados(?string $actual): array
    {
        $idx = array_search($actual ?? 'pendiente_envio', self::ORDEN);
        return array_slice(self::ORDEN, ($idx !== false ? $idx : 0) + 1);
    }

    /** Retorna label y css para un estado dado. */
    public static function metaEstado(string $estado): array
    {
        return self::META[$estado] ?? ['label' => ucfirst($estado), 'css' => 'gray'];
    }

    // ── Inicialización ────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->filtroFechaDesde = now()->subDays(30)->toDateString();
        $this->filtroFechaHasta = now()->toDateString();
        $this->form->fill();
    }

    // ── Formulario ────────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                TextInput::make('filtroCliente')
                    ->label('Cliente')
                    ->placeholder('Nombre o documento…')
                    ->prefixIcon('heroicon-o-magnifying-glass')
                    ->live(debounce: 300)
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
            ]),
        ]);
    }

    public function hayFiltros(): bool
    {
        return ! empty($this->filtroCliente);
    }

    public function limpiarFiltros(): void
    {
        $this->filtroCliente    = null;
        $this->filtroFechaDesde = now()->subDays(30)->toDateString();
        $this->filtroFechaHasta = now()->toDateString();
        $this->form->fill();
        $this->resetPage();
    }

    // ── Queries ───────────────────────────────────────────────────────────────

    private function baseQuery()
    {
        $q = Venta::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', EstadoVenta::Completada)
            ->whereNotNull('estado_despacho');

        if (! empty($this->filtroCliente)) {
            $b = $this->filtroCliente;
            $q->where(function ($sub) use ($b) {
                $sub->where('cliente_nombre', 'like', "%{$b}%")
                    ->orWhere('cliente_num_doc', 'like', "%{$b}%");
            });
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
            ->with(['serie', 'detalles', 'cliente:id,telefono', 'orden:id,numero,venta_id'])
            ->orderBy('fecha_emision', 'asc')
            ->paginate(25);
    }

    public function getResumen(): array
    {
        $empresaId = Filament::getTenant()->id;

        $row = DB::table('ventas')
            ->where('empresa_id', $empresaId)
            ->where('estado', EstadoVenta::Completada->value)
            ->whereNotNull('estado_despacho')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN DATE(fecha_emision) = CURDATE() THEN 1 ELSE 0 END) as hoy,
                SUM(CASE WHEN fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as semana
            ')
            ->first();

        return [
            'total'  => (int) ($row->total  ?? 0),
            'hoy'    => (int) ($row->hoy    ?? 0),
            'semana' => (int) ($row->semana ?? 0),
        ];
    }

    // ── Modal: cambiar estado de despacho ─────────────────────────────────────

    public function abrirModalEstado(int $ventaId, string $nuevoEstado): void
    {
        $venta = Venta::with(['serie', 'detalles', 'cliente:id,telefono'])
            ->where('empresa_id', Filament::getTenant()->id)
            ->findOrFail($ventaId);

        $tel    = preg_replace('/\D/', '', $venta->cliente?->telefono ?? '');
        $wspUrl = $tel ? 'https://wa.me/51' . ltrim($tel, '0') : null;

        $this->estadoVenta = [
            'comprobante'     => $venta->serie->serie . '-' . $venta->correlativo,
            'estado_actual'   => $venta->estado_despacho ?? 'pendiente_envio',
            'cliente'         => $venta->cliente_nombre ?: 'Cliente general',
            'cliente_doc'     => $venta->cliente_num_doc,
            'telefono'        => $tel ? '+51 ' . $tel : null,
            'wsp_url'         => $wspUrl,
            'fecha'           => Carbon::parse($venta->fecha_emision)->format('d/m/Y H:i'),
            'total'           => (float) $venta->total,
            'saldo'           => (float) $venta->saldo_pendiente,
            'es_cred_pend'    => $venta->tipo_pago === TipoPago::Credito
                                    && $venta->estado_pago === 'pendiente',
            'items'           => $venta->detalles->map(fn ($d) => [
                'descripcion' => $d->descripcion,
                'cantidad'    => (float) $d->cantidad,
            ])->toArray(),
        ];

        $this->estadoVentaId = $ventaId;
        $this->nuevoEstado   = $nuevoEstado;
        $this->modalEstado   = true;
    }

    /** Llamado por wire:change del <select> en la tabla. */
    public function seleccionarEstado(int $ventaId, string $estado): void
    {
        if (! $estado) return;
        $this->abrirModalEstado($ventaId, $estado);
    }

    public function cerrarModalEstado(): void
    {
        $this->modalEstado   = false;
        $this->estadoVentaId = null;
        $this->nuevoEstado   = null;
        $this->estadoVenta   = null;
    }

    public function confirmarCambioEstado(): void
    {
        $venta = Venta::where('empresa_id', Filament::getTenant()->id)
            ->whereNotNull('estado_despacho')
            ->findOrFail($this->estadoVentaId);

        // 'entregado' guarda NULL → sale de la lista
        $valorDb = $this->nuevoEstado === 'entregado' ? null : $this->nuevoEstado;

        $venta->update(['estado_despacho' => $valorDb]);

        $label = self::metaEstado($this->nuevoEstado)['label'];
        $this->cerrarModalEstado();

        Notification::make()
            ->title("Estado actualizado: {$label}")
            ->success()
            ->send();
    }
}
