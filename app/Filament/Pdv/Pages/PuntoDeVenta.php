<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoGeneral;
use App\Enums\EstadoMovimiento;
use App\Enums\EstadoPromocion;
use App\Enums\EstadoSesion;
use App\Enums\EstadoVenta;
use App\Enums\TipoComprobante;
use App\Enums\TipoDocumento;
use App\Enums\TipoItem;
use App\Enums\TipoMovimiento;
use App\Enums\TipoPago;
use App\Filament\Pdv\Resources\SesionCajas\SesionCajaResource;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MetodoPago;
use App\Models\Promocion;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\SesionCaja;
use App\Models\Transaccion;
use App\Models\Variante;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Events\VentaCompletada;
use App\Services\ImpresionDirectaService;
use App\Services\KardexService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use App\Filament\Pdv\Concerns\HasFullWidthPage;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\On;
use UnitEnum;

class PuntoDeVenta extends Page
{
    use HasFullWidthPage;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'Punto de Venta';
    protected static string|UnitEnum|null $navigationGroup = 'Punto de Venta';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pdv.pages.punto-de-venta';

    public static function canAccess(): bool { return Filament::getTenant()->tieneModulo('punto_de_venta') && (auth()->user()?->can('caja.punto_de_venta') ?? false); }

    // ── Carrito ───────────────────────────────────────────────────────────────
    public array $carrito = [];

    // ── Comprobante ───────────────────────────────────────────────────────────
    public ?string $tipoComprobante = null;
    public ?int $serieId = null;

    // ── Cliente ───────────────────────────────────────────────────────────────
    public ?int $clienteId = null;
    public ?string $clienteNombre = null;
    public ?string $clienteTipoDoc = null;
    public string $clienteBusqueda = '';
    public bool $mostrarSugerencias = false;


    // ── Modal pago ────────────────────────────────────────────────────────────
    public bool $modalPago = false;
    public bool $modalSinSesion = false;
    public array $metodosPagoDisponibles = [];
    public ?int $metodoPagoId = null;
    public string $montoPagoInput = '';
    public string $pagoReferencia = '';
    public array $pagosAgregados = [];
    public string $descuentoInput = '0';
    public bool $despachoRequerido = false;
    public string $despachoDireccion = '';

    // ── Reimpresión del último ticket (para el botón de reimprimir) ───────────

    // ── Reimpresión del último ticket ──────────────────────────────────────────
    public ?int   $ultimaVentaId     = null;
    public string $ultimaVentaNumero = '';

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    private function cacheKeyUltimaVenta(): string
    {
        return 'pdv:ultima_venta:' . Filament::getTenant()->id . ':' . auth()->id();
    }

    public function mount(): void
    {
        $this->autoSeleccionarComprobante();
        $this->autoSeleccionarClienteGeneral();

        $cached = Cache::get($this->cacheKeyUltimaVenta());
        if ($cached) {
            $this->ultimaVentaId     = $cached['id'];
            $this->ultimaVentaNumero = $cached['numero'];
        }
    }

    // ── Series ────────────────────────────────────────────────────────────────

    private ?Collection $seriesCache = null;

    public function getSeries(): Collection
    {
        return $this->seriesCache ??= Serie::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', true)
            ->whereIn('tipo', [
                TipoComprobante::Factura->value,
                TipoComprobante::Boleta->value,
                TipoComprobante::Ticket->value,
            ])
            ->get();
    }

    public function getSerieParaTipo(string $tipo): ?Serie
    {
        return $this->getSeries()->first(fn($s) => $s->tipo->value === $tipo);
    }

    public function getNumeroPreview(): string
    {
        if (! $this->serieId) return '---';
        $serie = Serie::find($this->serieId);
        if (! $serie) return '---';
        return $serie->serie . '-' . str_pad($serie->numero + 1, 8, '0', STR_PAD_LEFT);
    }

    public function seleccionarComprobante(string $tipo): void
    {
        $serie = $this->getSerieParaTipo($tipo);
        if (! $serie) return;
        $this->tipoComprobante = $tipo;
        $this->serieId = $serie->id;
    }

    private function autoSeleccionarComprobante(): void
    {
        foreach ([TipoComprobante::Ticket->value, TipoComprobante::Boleta->value, TipoComprobante::Factura->value] as $tipo) {
            $serie = $this->getSerieParaTipo($tipo);
            if ($serie) {
                $this->tipoComprobante = $tipo;
                $this->serieId = $serie->id;
                return;
            }
        }
    }

    private function autoSeleccionarClienteGeneral(): void
    {
        $cliente = Cliente::where('empresa_id', Filament::getTenant()->id)
            ->where('numero_documento', '99999999')
            ->first();

        if (! $cliente) return;

        $this->clienteId      = $cliente->id;
        $this->clienteNombre  = $cliente->nombre_completo;
        $this->clienteTipoDoc = $cliente->tipo_documento->value;
        $this->clienteBusqueda = $cliente->nombre_completo;
    }

    // ── Cliente ───────────────────────────────────────────────────────────────

    public function updatedClienteBusqueda(): void
    {
        $this->mostrarSugerencias = strlen($this->clienteBusqueda) >= 2;
        if ($this->clienteId && $this->clienteBusqueda !== $this->clienteNombre) {
            $this->clienteId      = null;
            $this->clienteNombre  = null;
            $this->clienteTipoDoc = null;
        }
    }

    public function getClientesSugeridos(): Collection
    {
        if (strlen($this->clienteBusqueda) < 2) return collect();

        return Cliente::where('empresa_id', Filament::getTenant()->id)
            ->where(function ($q) {
                $q->where('nombre', 'like', "%{$this->clienteBusqueda}%")
                  ->orWhere('apellidos', 'like', "%{$this->clienteBusqueda}%")
                  ->orWhere('numero_documento', 'like', "%{$this->clienteBusqueda}%");
            })
            ->limit(8)
            ->get();
    }

    public function seleccionarCliente(int $id): void
    {
        $cliente = Cliente::find($id);
        if (! $cliente) return;

        $this->clienteId      = $id;
        $this->clienteNombre  = $cliente->nombre_completo;
        $this->clienteTipoDoc = $cliente->tipo_documento->value;
        $this->clienteBusqueda = $cliente->nombre_completo;
        $this->mostrarSugerencias = false;

        if ($cliente->tipo_documento === TipoDocumento::RUC) {
            $this->seleccionarComprobante(TipoComprobante::Factura->value);
        }
    }

    public function limpiarCliente(): void
    {
        $this->clienteId        = null;
        $this->clienteNombre    = null;
        $this->clienteTipoDoc   = null;
        $this->clienteBusqueda  = '';
        $this->mostrarSugerencias = false;
    }

    // ── Modal: nuevo cliente rápido (delegado a NuevoClienteModal) ───────────

    public function abrirModalNuevoCliente(): void
    {
        $this->dispatch('abrir-modal-nuevo-cliente');
    }

    #[On('cliente-creado')]
    public function onClienteCreado(int $id): void
    {
        $this->seleccionarCliente($id);
    }

    // ── Validación de comprobante ─────────────────────────────────────────────

    public function tieneSerieActiva(string $tipo): bool
    {
        return $this->getSerieParaTipo($tipo) !== null;
    }

    public function comprobanteEsValidoParaCliente(string $tipo): bool
    {
        return match ($tipo) {
            TipoComprobante::Factura->value => $this->clienteTipoDoc === TipoDocumento::RUC->value,
            TipoComprobante::Boleta->value  => true,
            TipoComprobante::Ticket->value  => true,
            default                         => false,
        };
    }

    // ── Carrito: resumen para ProductCatalog ──────────────────────────────────

    public function getCarritoResumen(): array
    {
        $resumen = [];
        foreach ($this->carrito as $item) {
            if ($item['tipo'] === 'promocion') continue;
            $rKey = "{$item['tipo']}_{$item['id']}";
            $resumen[$rKey] = ($resumen[$rKey] ?? 0) + (float) $item['cantidad'];
        }
        return $resumen;
    }

    // ── Carrito: agregar desde ProductCatalog ─────────────────────────────────

    #[On('product-selected')]
    public function agregarAlCarrito(array $payload): void
    {
        $tipo          = $payload['tipo'];
        $id            = (int) $payload['id'];
        $nombre        = $payload['nombre'];
        $precio        = (float) $payload['precio'];
        $precioNorm    = (float) ($payload['precio_normal'] ?? $precio);
        $esCortesia    = (bool) ($payload['es_cortesia'] ?? false);
        $cantidad      = (float) ($payload['cantidad'] ?? 1);
        $puedeCortesia = (bool) ($payload['puede_cortesia'] ?? false);
        $esDecimal     = (bool) ($payload['es_decimal'] ?? false);

        if ($tipo === 'promocion') {
            $this->agregarPromocion($id);
            return;
        }

        $baseKey = "{$tipo}_{$id}";

        if ($tipo === 'producto') {
            $prod = Producto::with('inventario')->find($id);
            if (! $prod) return;
            if ($prod->control_de_stock && ! $prod->venta_sin_stock) {
                $stock     = (float) ($prod->inventario?->stock_real ?? 0);
                $enCarrito = $this->cantidadEnCarritoPorBase($baseKey);
                if ($enCarrito + $cantidad > $stock) {
                    Notification::make()->title('Stock insuficiente')->body("Disponible: {$stock}.")->warning()->send();
                    return;
                }
            }
        } elseif ($tipo === 'variante') {
            $var = Variante::with('producto')->find($id);
            if (! $var) return;
            $prod = $var->producto;
            if ($prod?->control_de_stock && ! $prod?->venta_sin_stock) {
                $inv   = Inventario::where('variante_id', $id)->first();
                $stock = (float) ($inv?->stock_real ?? 0);
                $enCarrito = $this->cantidadEnCarritoPorBase($baseKey);
                if ($enCarrito + $cantidad > $stock) {
                    Notification::make()->title('Stock insuficiente')->body("Disponible: {$stock}.")->warning()->send();
                    return;
                }
            }
        }

        $resolvedKey = $this->resolveCarritoKey($baseKey, $esCortesia);
        $this->pushCarrito($resolvedKey, $tipo, $id, $nombre, $precio, $esCortesia, $esDecimal, $precioNorm, $puedeCortesia, $cantidad);
    }

    // ── Carrito: agregar promoción ────────────────────────────────────────────

    public function agregarPromocion(int $promocionId): void
    {
        $promocion = Promocion::with([
            'detalles.producto.inventario',
            'detalles.variante.producto',
            'detalles.variante.inventario',
        ])->find($promocionId);

        if (! $promocion || ! $promocion->estaVigente()) {
            Notification::make()->title('Promoción no disponible')->warning()->send();
            return;
        }

        $stock     = $promocion->stockPredictivo();
        $enCarrito = (int) ($this->carrito["promocion_{$promocionId}"]['cantidad'] ?? 0);

        if ($stock !== null && ($enCarrito + 1) > $stock) {
            Notification::make()->title('Stock insuficiente para esta promoción')->warning()->send();
            return;
        }

        $this->pushCarrito("promocion_{$promocionId}", 'promocion', $promocionId, $promocion->nombre, (float) $promocion->precio);
    }

    // ── Carrito: gestión ──────────────────────────────────────────────────────

    public function toggleCortesia(string $key): void
    {
        $carrito = $this->carrito;
        if (! isset($carrito[$key])) return;
        if (! ($carrito[$key]['puede_cortesia'] ?? false)) return;

        $item       = $carrito[$key];
        $turningOn  = ! ($item['cortesia'] ?? false);

        foreach ($carrito as $k => $other) {
            if ($k === $key) continue;
            if ($other['tipo'] !== $item['tipo'] || $other['id'] !== $item['id']) continue;

            $otherEsCortesia = (bool) ($other['cortesia'] ?? false);

            if ($turningOn && $otherEsCortesia) {
                $carrito[$k]['cantidad'] += $item['cantidad'];
                unset($carrito[$key]);
                $this->carrito = $carrito;
                return;
            }

            if (! $turningOn && ! $otherEsCortesia) {
                $carrito[$k]['cantidad'] += $item['cantidad'];
                unset($carrito[$key]);
                $this->carrito = $carrito;
                return;
            }
        }

        $carrito[$key]['cortesia'] = $turningOn;
        $carrito[$key]['precio']   = $turningOn ? 0.0 : (float) ($item['precio_normal'] ?? 0);
        $this->carrito = $carrito;
    }

    public function aumentarCantidad(string $key): void
    {
        $carrito = $this->carrito;
        if (! isset($carrito[$key])) return;

        $item = $carrito[$key];

        if (! ($item['cortesia'] ?? false)) {
            if ($item['tipo'] === 'producto') {
                $prod = Producto::with('inventario')->find($item['id']);
                if ($prod && $prod->control_de_stock && ! $prod->venta_sin_stock) {
                    $stock = (float) ($prod->inventario?->stock_real ?? 0);
                    if ($item['cantidad'] + 1 > $stock) {
                        Notification::make()
                            ->title('Stock insuficiente')
                            ->body("Disponible: {$stock} unidad(es).")
                            ->warning()->send();
                        return;
                    }
                }
            } elseif ($item['tipo'] === 'variante') {
                $inv  = Inventario::where('variante_id', $item['id'])->first();
                $var  = Variante::with('producto')->find($item['id']);
                if ($var && $var->producto?->control_de_stock && ! $var->producto?->venta_sin_stock) {
                    $stock = (float) ($inv?->stock_real ?? 0);
                    if ($item['cantidad'] + 1 > $stock) {
                        Notification::make()
                            ->title('Stock insuficiente')
                            ->body("Disponible: {$stock} unidad(es).")
                            ->warning()->send();
                        return;
                    }
                }
            }
        }

        $carrito[$key]['cantidad']++;
        $this->carrito = $carrito;
    }

    public function disminuirCantidad(string $key): void
    {
        $carrito = $this->carrito;
        if (! isset($carrito[$key])) return;
        if ($carrito[$key]['cantidad'] > 1) {
            $carrito[$key]['cantidad']--;
        } else {
            unset($carrito[$key]);
        }
        $this->carrito = $carrito;
    }

    public function actualizarPrecio(string $key, float $precio): void
    {
        if (! isset($this->carrito[$key])) return;
        $carrito = $this->carrito;
        $carrito[$key]['precio'] = round(max(0, $precio), 2);
        $this->carrito = $carrito;
    }

    public function actualizarCantidad(string $key, float $cant): void
    {
        if (! isset($this->carrito[$key])) return;
        $cant  = max(0.001, round($cant, 3));
        $item  = $this->carrito[$key];

        if (! ($item['cortesia'] ?? false)) {
            if ($item['tipo'] === 'producto') {
                $prod = Producto::with('inventario')->find($item['id']);
                if ($prod && $prod->control_de_stock && ! $prod->venta_sin_stock) {
                    $stock = (float) ($prod->inventario?->stock_real ?? 0);
                    if ($cant > $stock) {
                        Notification::make()
                            ->title('Stock insuficiente')
                            ->body("Disponible: {$stock}.")
                            ->warning()->send();
                        $cant = $stock;
                    }
                }
            } elseif ($item['tipo'] === 'variante') {
                $inv = Inventario::where('variante_id', $item['id'])->first();
                $var = Variante::with('producto')->find($item['id']);
                if ($var && $var->producto?->control_de_stock && ! $var->producto?->venta_sin_stock) {
                    $stock = (float) ($inv?->stock_real ?? 0);
                    if ($cant > $stock) {
                        Notification::make()
                            ->title('Stock insuficiente')
                            ->body("Disponible: {$stock}.")
                            ->warning()->send();
                        $cant = $stock;
                    }
                }
            }
        }

        $carrito         = $this->carrito;
        $carrito[$key]['cantidad'] = $cant;
        $this->carrito   = $carrito;
    }

    public function eliminarItem(string $key): void
    {
        $carrito = $this->carrito;
        unset($carrito[$key]);
        $this->carrito = $carrito;
    }

    public function vaciarCarrito(): void
    {
        $this->carrito = [];
    }

    public function getTotal(): float
    {
        return collect($this->carrito)->sum(fn($item) => $item['precio'] * $item['cantidad']);
    }

    public function getItemCount(): int
    {
        return collect($this->carrito)->sum('cantidad');
    }

    // ── Helpers de carrito ────────────────────────────────────────────────────

    private function resolveCarritoKey(string $baseKey, bool $esCortesia = false): string
    {
        if (! isset($this->carrito[$baseKey])) {
            return $baseKey;
        }
        if ((bool) $this->carrito[$baseKey]['cortesia'] === $esCortesia) {
            return $baseKey;
        }
        $i = 2;
        while (true) {
            $k = "{$baseKey}_{$i}";
            if (! isset($this->carrito[$k])) {
                return $k;
            }
            if ((bool) $this->carrito[$k]['cortesia'] === $esCortesia) {
                return $k;
            }
            $i++;
        }
    }

    private function cantidadEnCarritoPorBase(string $baseKey): float
    {
        $total = 0.0;
        foreach ($this->carrito as $key => $item) {
            if ($key === $baseKey || str_starts_with($key, "{$baseKey}_")) {
                $total += (float) $item['cantidad'];
            }
        }
        return $total;
    }

    private function pushCarrito(string $key, string $tipo, int $id, string $nombre, float $precio, bool $esCortesia = false, bool $esDecimal = false, float $precioNormal = 0, bool $puedeCortesia = false, float $cantidadAgregar = 1.0): void
    {
        $carrito = $this->carrito;
        if (isset($carrito[$key])) {
            $carrito[$key]['cantidad'] += $cantidadAgregar;
        } else {
            $carrito[$key] = [
                'key'           => $key,
                'tipo'          => $tipo,
                'id'            => $id,
                'nombre'        => $nombre,
                'precio'        => $precio,
                'precio_normal' => $precioNormal ?: $precio,
                'cortesia'      => $esCortesia,
                'puede_cortesia'=> $puedeCortesia,
                'decimal'       => $esDecimal,
                'cantidad'      => $cantidadAgregar,
            ];
        }
        $this->carrito = $carrito;
    }

    // ── Modal pago ────────────────────────────────────────────────────────────

    public function getUrlAperturaCaja(): string
    {
        return SesionCajaResource::getUrl('create');
    }

    public function abrirModalPago(): void
    {
        if (empty($this->carrito)) {
            Notification::make()->title('El carrito está vacío')->warning()->send();
            return;
        }

        if (! $this->serieId) {
            Notification::make()->title('No hay serie configurada para este comprobante')->warning()->send();
            return;
        }

        $sesionActiva = SesionCaja::where('empresa_id', Filament::getTenant()->id)
            ->where('user_id', auth()->id())
            ->where('estado', EstadoSesion::Abierta->value)
            ->exists();

        if (! $sesionActiva) {
            $this->modalSinSesion = true;
            return;
        }

        $this->metodosPagoDisponibles = MetodoPago::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', EstadoGeneral::Activo->value)
            ->orderBy('nombre')
            ->get()
            ->map(fn($m) => [
                'id'                  => $m->id,
                'nombre'              => $m->nombre,
                'imagen'              => $m->imagen,
                'requiere_referencia' => (bool) $m->requiere_referencia,
                'condicion_pago'      => $m->condicion_pago->value,
            ])
            ->values()
            ->toArray();

        $this->metodoPagoId       = null;
        $this->montoPagoInput     = '';
        $this->pagoReferencia     = '';
        $this->pagosAgregados     = [];
        $this->descuentoInput     = '0';
        $this->despachoRequerido  = false;
        $this->despachoDireccion  = '';
        $this->modalPago          = true;

        $this->autoSeleccionarEfectivo();
        if ($this->metodoPagoId) {
            $this->montoPagoInput = number_format($this->getSaldoRestante(), 2, '.', '');
        }
    }

    public function cerrarModalSinSesion(): void
    {
        $this->modalSinSesion = false;
    }

    public function cerrarModalPago(): void
    {
        $this->modalPago              = false;
        $this->metodosPagoDisponibles = [];
        $this->metodoPagoId           = null;
        $this->montoPagoInput         = '';
        $this->pagoReferencia         = '';
        $this->pagosAgregados         = [];
        $this->descuentoInput         = '0';
        $this->despachoRequerido      = false;
        $this->despachoDireccion      = '';
    }

    public function seleccionarMetodoPago(int $id): void
    {
        $this->metodoPagoId   = $id;
        $this->pagoReferencia = '';
        $saldo = $this->getSaldoRestante();
        $this->montoPagoInput = $saldo > 0 ? number_format($saldo, 2, '.', '') : '0.00';
    }

    public function setMontoExacto(): void
    {
        $this->autoSeleccionarEfectivo();

        $saldo = $this->getSaldoRestante();
        $this->montoPagoInput = number_format(max(0, $saldo), 2, '.', '');
    }

    public function ajustarMonto(float $delta): void
    {
        $this->autoSeleccionarEfectivo();

        $actual = (float) str_replace(',', '.', $this->montoPagoInput ?: '0');
        $this->montoPagoInput = number_format(max(0, $actual + $delta), 2, '.', '');
    }

    private function autoSeleccionarEfectivo(): void
    {
        if ($this->metodoPagoId) return;

        $efectivo = collect($this->metodosPagoDisponibles)
            ->first(fn($m) => mb_strtolower($m['nombre']) === 'efectivo');

        if ($efectivo) {
            $this->metodoPagoId   = $efectivo['id'];
            $this->pagoReferencia = '';
        }
    }

    public function agregarPago(): void
    {
        $monto = (float) str_replace(',', '.', $this->montoPagoInput ?: '0');

        if (! $this->metodoPagoId) {
            Notification::make()->title('Selecciona un método de pago')->warning()->send();
            return;
        }

        if ($monto <= 0) {
            Notification::make()->title('El monto debe ser mayor a 0')->warning()->send();
            return;
        }

        $metodo = collect($this->metodosPagoDisponibles)->firstWhere('id', $this->metodoPagoId);

        if ($metodo && $metodo['requiere_referencia'] && blank($this->pagoReferencia)) {
            Notification::make()->title('Este método requiere una referencia')->warning()->send();
            return;
        }

        $this->pagosAgregados[] = [
            'metodo_pago_id'  => $this->metodoPagoId,
            'nombre'          => $metodo['nombre'] ?? '',
            'imagen'          => $metodo['imagen'] ?? null,
            'monto'           => $monto,
            'referencia'      => $this->pagoReferencia,
            'condicion_pago'  => $metodo['condicion_pago'] ?? 'contado',
        ];

        $saldo = $this->getSaldoRestante();
        $this->montoPagoInput = $saldo > 0 ? number_format($saldo, 2, '.', '') : '0.00';
        $this->pagoReferencia = '';
    }

    public function eliminarPago(int $index): void
    {
        $pagos = $this->pagosAgregados;
        unset($pagos[$index]);
        $this->pagosAgregados = array_values($pagos);

        $saldo = $this->getSaldoRestante();
        if ($saldo > 0) {
            $this->montoPagoInput = number_format($saldo, 2, '.', '');
        }
    }

    public function getDescuento(): float
    {
        $d = (float) str_replace(',', '.', $this->descuentoInput ?: '0');
        return max(0.0, min($d, $this->getTotal()));
    }

    public function getTotalConDescuento(): float
    {
        return round(max(0.0, $this->getTotal() - $this->getDescuento()), 2);
    }

    public function esTicket(): bool
    {
        return $this->tipoComprobante === TipoComprobante::Ticket->value;
    }

    public function getOpGravadas(): float
    {
        if ($this->esTicket()) return 0.0;
        return round($this->getTotalConDescuento() / 1.18, 2);
    }

    public function getIgv(): float
    {
        if ($this->esTicket()) return 0.0;
        return round($this->getTotalConDescuento() - $this->getOpGravadas(), 2);
    }

    public function getTotalPagado(): float
    {
        return round(collect($this->pagosAgregados)->sum('monto'), 2);
    }

    public function getSaldoRestante(): float
    {
        return round($this->getTotalConDescuento() - $this->getTotalPagado(), 2);
    }

    public function totalEsCero(): bool
    {
        return $this->getTotalConDescuento() <= 0.01;
    }

    public function procesarVenta(): void
    {
        if (empty($this->carrito)) {
            Notification::make()->title('El carrito está vacío')->warning()->send();
            return;
        }

        if (empty($this->pagosAgregados) && ! $this->totalEsCero()) {
            Notification::make()->title('Agrega al menos un pago')->warning()->send();
            return;
        }

        if ($this->getSaldoRestante() > 0.01) {
            Notification::make()->title('El monto pagado es insuficiente')->warning()->send();
            return;
        }

        $empresaId = Filament::getTenant()->id;

        $sesionActiva = SesionCaja::where('empresa_id', $empresaId)
            ->where('user_id', auth()->id())
            ->where('estado', EstadoSesion::Abierta->value)
            ->exists();

        if (! $sesionActiva) {
            $this->cerrarModalPago();
            $this->modalSinSesion = true;
            return;
        }

        $descuento         = $this->getDescuento();
        $totalConDescuento = $this->getTotalConDescuento();
        $pagosAgregados    = $this->pagosAgregados;
        $carrito           = $this->carrito;
        $clienteId         = $this->clienteId;
        $clienteNombre     = $this->clienteNombre;
        $clienteTipoDoc    = $this->clienteTipoDoc;
        $serieId           = $this->serieId;
        $despachoRequerido = $this->despachoRequerido;
        $despachoDireccion = trim($this->despachoDireccion);

        $esTicket    = $this->esTicket();
        $tasaIgv     = $esTicket ? 0.0 : 0.18;
        $opGravadas  = $esTicket ? 0.0 : $this->getOpGravadas();
        $opInafectas = 0.0;
        $igv         = $esTicket ? 0.0 : $this->getIgv();

        $pagosContado  = array_values(array_filter($pagosAgregados, fn($p) => ($p['condicion_pago'] ?? 'contado') !== 'credito'));
        $pagosCredito  = array_values(array_filter($pagosAgregados, fn($p) => ($p['condicion_pago'] ?? 'contado') === 'credito'));
        $montoContado  = round(array_sum(array_column($pagosContado, 'monto')), 2);
        $montoPagado   = min($montoContado, $totalConDescuento);
        $saldoPendiente = round(max(0.0, $totalConDescuento - $montoPagado), 2);
        $tipoPagoVenta = count($pagosCredito) > 0 ? TipoPago::Credito : TipoPago::Contado;
        $estadoPago    = $saldoPendiente > 0.01 ? 'pendiente' : 'pagado';

        $venta = null;

        try {
            DB::transaction(function () use (
                $empresaId, $descuento, $totalConDescuento,
                $opGravadas, $opInafectas, $igv, $tasaIgv,
                $montoPagado, $saldoPendiente, $tipoPagoVenta, $estadoPago,
                $pagosContado, $pagosCredito, $carrito,
                $clienteId, $clienteNombre, $clienteTipoDoc, $serieId, $despachoRequerido, $despachoDireccion,
                &$venta
            ) {
                $serie = Serie::lockForUpdate()->findOrFail($serieId);
                $nuevoNumero = $serie->numero + 1;
                $serie->update(['numero' => $nuevoNumero]);
                $correlativo = str_pad($nuevoNumero, 8, '0', STR_PAD_LEFT);

                $sesionCaja = SesionCaja::where('empresa_id', $empresaId)
                    ->where('user_id', auth()->id())
                    ->where('estado', EstadoSesion::Abierta->value)
                    ->latest()
                    ->lockForUpdate()
                    ->first();

                if (! $sesionCaja) {
                    throw new \RuntimeException('__SIN_SESION__');
                }

                $cliente             = $clienteId ? Cliente::find($clienteId) : null;
                $clienteNombreFinal  = $cliente?->nombre_completo ?? $clienteNombre;
                $clienteTipoDocFinal = $cliente?->tipo_documento?->value ?? $clienteTipoDoc;
                $clienteNumDoc       = $cliente?->numero_documento ?? null;

                $venta = Venta::create([
                    'empresa_id'       => $empresaId,
                    'sesion_caja_id'   => $sesionCaja->id,
                    'cliente_id'       => $clienteId,
                    'cliente_nombre'   => $clienteNombreFinal,
                    'cliente_tipo_doc' => $clienteTipoDocFinal,
                    'cliente_num_doc'  => $clienteNumDoc,
                    'serie_id'         => $serieId,
                    'correlativo'      => $correlativo,
                    'tipo_pago'        => $tipoPagoVenta,
                    'op_gravadas'      => $opGravadas,
                    'op_exoneradas'    => 0,
                    'op_inafectas'     => $opInafectas,
                    'descuento_total'  => $descuento,
                    'igv'              => $igv,
                    'total'            => $totalConDescuento,
                    'costo_total'      => 0,
                    'monto_pagado'     => $montoPagado,
                    'saldo_pendiente'  => $saldoPendiente,
                    'estado_pago'      => $estadoPago,
                    'estado'              => EstadoVenta::Completada,
                    'estado_despacho'     => $despachoRequerido ? 'pendiente_envio' : null,
                    'despacho_direccion'  => $despachoRequerido && $despachoDireccion !== '' ? $despachoDireccion : null,
                ]);

                // Pre-cargar productos y variantes en batch para evitar N+1 dentro del loop
                $productoIds = collect($carrito)->where('tipo', 'producto')->pluck('id')->unique()->all();
                $varianteIds = collect($carrito)->where('tipo', 'variante')->pluck('id')->unique()->all();

                $productosMap = $productoIds
                    ? Producto::with('unidadMedida')->whereIn('id', $productoIds)->get()->keyBy('id')
                    : collect();
                $variantesMap = $varianteIds
                    ? Variante::with('producto.unidadMedida')->whereIn('id', $varianteIds)->get()->keyBy('id')
                    : collect();

                $costoTotalVenta = 0.0;

                foreach ($carrito as $item) {
                    $variante = $item['tipo'] === 'variante'
                        ? $variantesMap->get($item['id'])
                        : null;

                    $costoUnitario = match ($item['tipo']) {
                        'producto' => (float) ($productosMap->get($item['id'])?->precio_costo ?? 0),
                        'variante' => (float) ($variante?->precio_costo ?? $variante?->producto?->precio_costo ?? 0),
                        default    => 0.0,
                    };

                    $calc = VentaDetalle::calcular(
                        cantidad: (float) $item['cantidad'],
                        precioUnitario: (float) $item['precio'],
                        costoUnitario: $costoUnitario,
                        tasaIgv: $tasaIgv,
                    );

                    $tipoItem = match ($item['tipo']) {
                        'variante'  => TipoItem::Variante,
                        'promocion' => TipoItem::Promocion,
                        default     => TipoItem::Producto,
                    };

                    $esCortesiaItem = $item['cortesia'] ?? false;

                    $detalleData = [
                        'venta_id'        => $venta->id,
                        'tipo_item'       => $tipoItem,
                        'descripcion'     => $esCortesiaItem ? $item['nombre'] . ' (Cortesía)' : $item['nombre'],
                        'cantidad'        => $item['cantidad'],
                        'precio_unitario' => $item['precio'],
                        'valor_unitario'  => $calc['valorUnitario'],
                        'costo_unitario'  => $costoUnitario,
                        'descuento'       => 0,
                        'subtotal'        => $calc['subtotal'],
                        'valor_total'     => $calc['valorTotal'],
                        'igv'             => $calc['igv'],
                        'total'           => $calc['total'],
                        'costo_total'     => $calc['costoTotal'],
                    ];

                    if ($item['tipo'] === 'producto') {
                        $detalleData['producto_id'] = $item['id'];
                    } elseif ($item['tipo'] === 'variante') {
                        $detalleData['variante_id'] = $item['id'];
                        $detalleData['producto_id'] = $variante?->producto_id;
                    } elseif ($item['tipo'] === 'promocion') {
                        $detalleData['promocion_id'] = $item['id'];
                    }

                    VentaDetalle::create($detalleData);
                    $costoTotalVenta += $calc['costoTotal'];
                }

                $venta->update(['costo_total' => round($costoTotalVenta, 2)]);

                foreach ($pagosContado as $pago) {
                    VentaPago::create([
                        'venta_id'       => $venta->id,
                        'sesion_caja_id' => $sesionCaja->id,
                        'metodo_pago_id' => $pago['metodo_pago_id'],
                        'monto'          => $pago['monto'],
                        'referencia'     => $pago['referencia'] ?: null,
                    ]);

                    Transaccion::create([
                        'empresa_id'           => $empresaId,
                        'sesion_caja_id'       => $sesionCaja->id,
                        'transaccionable_type' => Venta::class,
                        'transaccionable_id'   => $venta->id,
                        'tipo'                 => TipoMovimiento::Ingreso,
                        'concepto'             => "Venta {$serie->serie}-{$correlativo}",
                        'monto'                => $pago['monto'],
                        'metodo_pago_id'       => $pago['metodo_pago_id'],
                        'estado'               => EstadoMovimiento::Aprobado,
                        'fecha'                => now(),
                    ]);
                }

                foreach ($pagosCredito as $pago) {
                    VentaPago::create([
                        'venta_id'       => $venta->id,
                        'sesion_caja_id' => $sesionCaja->id,
                        'metodo_pago_id' => $pago['metodo_pago_id'],
                        'monto'          => $pago['monto'],
                        'referencia'     => $pago['referencia'] ?: null,
                    ]);

                    Transaccion::create([
                        'empresa_id'           => $empresaId,
                        'sesion_caja_id'       => $sesionCaja->id,
                        'transaccionable_type' => Venta::class,
                        'transaccionable_id'   => $venta->id,
                        'tipo'                 => TipoMovimiento::Ingreso,
                        'concepto'             => "Crédito {$serie->serie}-{$correlativo}",
                        'monto'                => $pago['monto'],
                        'metodo_pago_id'       => $pago['metodo_pago_id'],
                        'estado'               => EstadoMovimiento::PorCobrar,
                        'fecha'                => now(),
                    ]);
                }

                $kardex  = app(KardexService::class);
                $concepto = $serie->serie . '-' . $correlativo;

                // Batch-cargar inventarios con lockForUpdate antes del loop para evitar N+1
                // Los locks se adquieren aquí, dentro de la transacción, de forma segura
                $inventariosProducto = $productoIds
                    ? Inventario::where('empresa_id', $empresaId)
                        ->whereIn('producto_id', $productoIds)
                        ->whereNull('variante_id')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('producto_id')
                    : collect();
                $inventariosVariante = $varianteIds
                    ? Inventario::where('empresa_id', $empresaId)
                        ->whereIn('variante_id', $varianteIds)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('variante_id')
                    : collect();

                foreach ($carrito as $item) {
                    $cantidad = (float) $item['cantidad'];

                    if ($item['tipo'] === 'producto') {
                        $producto = $productosMap->get($item['id']);
                        if ($producto?->control_de_stock) {
                            $inv = $inventariosProducto->get($item['id']);
                            if ($inv) {
                                $stockAntes = (float) $inv->stock_real;
                                if (! $producto->venta_sin_stock && $stockAntes < $cantidad) {
                                    throw new \RuntimeException(
                                        "Stock insuficiente para \"{$item['nombre']}\": disponible {$stockAntes}, solicitado {$cantidad}."
                                    );
                                }
                                $stockDespues = $producto->venta_sin_stock
                                    ? $stockAntes - $cantidad
                                    : max(0, $stockAntes - $cantidad);
                                $inv->update([
                                    'stock_real'    => $stockDespues,
                                    'stock_reserva' => max(0, (float) $inv->stock_reserva - ($stockAntes - $stockDespues)),
                                ]);
                                $kardex->registrar([
                                    'empresa_id'        => $empresaId,
                                    'user_id'           => auth()->id(),
                                    'movible'           => $venta,
                                    'producto_id'       => $item['id'],
                                    'variante_id'       => null,
                                    'producto_nombre'   => $item['nombre'],
                                    'tipo'              => 'salida',
                                    'concepto'          => $concepto,
                                    'cantidad'          => $cantidad,
                                    'unidad'            => $producto->unidadMedida?->nombre ?? 'unidad',
                                    'factor_conversion' => 1,
                                    'cantidad_base'     => $cantidad,
                                    'precio_unitario'   => $item['precio'],
                                    'precio_total'      => $item['precio'] * $cantidad,
                                    'stock_antes'       => $stockAntes,
                                    'stock_despues'     => $stockDespues,
                                ]);
                            }
                        }
                    } elseif ($item['tipo'] === 'variante') {
                        $variante = $variantesMap->get($item['id']);
                        if ($variante) {
                            $prodVariante = $variante->producto;
                            if ($prodVariante?->control_de_stock) {
                                $inv = $inventariosVariante->get($item['id']);
                                if ($inv) {
                                    $stockAntes = (float) $inv->stock_real;
                                    if (! $prodVariante->venta_sin_stock && $stockAntes < $cantidad) {
                                        throw new \RuntimeException(
                                            "Stock insuficiente para \"{$item['nombre']}\": disponible {$stockAntes}, solicitado {$cantidad}."
                                        );
                                    }
                                    $stockDespues = $prodVariante->venta_sin_stock
                                        ? $stockAntes - $cantidad
                                        : max(0, $stockAntes - $cantidad);
                                    $inv->update([
                                        'stock_real'    => $stockDespues,
                                        'stock_reserva' => max(0, (float) $inv->stock_reserva - ($stockAntes - $stockDespues)),
                                    ]);
                                    $kardex->registrar([
                                        'empresa_id'        => $empresaId,
                                        'user_id'           => auth()->id(),
                                        'movible'           => $venta,
                                        'producto_id'       => $variante->producto_id,
                                        'variante_id'       => $item['id'],
                                        'producto_nombre'   => $item['nombre'],
                                        'tipo'              => 'salida',
                                        'concepto'          => $concepto,
                                        'cantidad'          => $cantidad,
                                        'unidad'            => $prodVariante->unidadMedida?->nombre ?? 'unidad',
                                        'factor_conversion' => 1,
                                        'cantidad_base'     => $cantidad,
                                        'precio_unitario'   => $item['precio'],
                                        'precio_total'      => $item['precio'] * $cantidad,
                                        'stock_antes'       => $stockAntes,
                                        'stock_despues'     => $stockDespues,
                                    ]);
                                }
                            }
                        }
                    } elseif ($item['tipo'] === 'promocion') {
                        Promocion::where('id', $item['id'])->increment('usos_actuales', (int) $cantidad);

                        $promo = Promocion::with([
                            'detalles.producto.unidadMedida',
                            'detalles.variante.producto.unidadMedida',
                        ])->find($item['id']);

                        if ($promo) {
                            foreach ($promo->detalles as $detalle) {
                                $cantidadDetalle = $cantidad * (float) $detalle->cantidad;

                                if ($detalle->variante_id) {
                                    $varianteDetalle = $detalle->variante;
                                    $prodDetalle     = $varianteDetalle?->producto;
                                    if ($prodDetalle?->control_de_stock) {
                                        $inv = Inventario::where('empresa_id', $empresaId)
                                            ->where('variante_id', $detalle->variante_id)
                                            ->lockForUpdate()->first();
                                        if ($inv) {
                                            $stockAntes = (float) $inv->stock_real;
                                            if (! ($prodDetalle->venta_sin_stock ?? false) && $stockAntes < $cantidadDetalle) {
                                                throw new \RuntimeException(
                                                    "Stock insuficiente en combo \"{$item['nombre']}\": disponible {$stockAntes}, solicitado {$cantidadDetalle}."
                                                );
                                            }
                                            $stockDespues = ($prodDetalle->venta_sin_stock ?? false)
                                                ? $stockAntes - $cantidadDetalle
                                                : max(0, $stockAntes - $cantidadDetalle);
                                            $inv->update([
                                                'stock_real'    => $stockDespues,
                                                'stock_reserva' => max(0, (float) $inv->stock_reserva - ($stockAntes - $stockDespues)),
                                            ]);
                                            $kardex->registrar([
                                                'empresa_id'        => $empresaId,
                                                'user_id'           => auth()->id(),
                                                'movible'           => $venta,
                                                'producto_id'       => $varianteDetalle->producto_id,
                                                'variante_id'       => $detalle->variante_id,
                                                'tipo'              => 'salida',
                                                'concepto'          => $concepto,
                                                'notas'             => "Promo: {$item['nombre']}",
                                                'cantidad'          => $cantidadDetalle,
                                                'unidad'            => $prodDetalle?->unidadMedida?->nombre ?? 'unidad',
                                                'factor_conversion' => 1,
                                                'cantidad_base'     => $cantidadDetalle,
                                                'stock_antes'       => $stockAntes,
                                                'stock_despues'     => $stockDespues,
                                            ]);
                                        }
                                    }
                                } elseif ($detalle->producto_id) {
                                    $prodDetalle = $detalle->producto;
                                    if ($prodDetalle?->control_de_stock) {
                                        $inv = Inventario::where('empresa_id', $empresaId)
                                            ->where('producto_id', $detalle->producto_id)
                                            ->whereNull('variante_id')
                                            ->lockForUpdate()->first();
                                        if ($inv) {
                                            $stockAntes = (float) $inv->stock_real;
                                            if (! ($prodDetalle->venta_sin_stock ?? false) && $stockAntes < $cantidadDetalle) {
                                                throw new \RuntimeException(
                                                    "Stock insuficiente en combo \"{$item['nombre']}\": disponible {$stockAntes}, solicitado {$cantidadDetalle}."
                                                );
                                            }
                                            $stockDespues = ($prodDetalle->venta_sin_stock ?? false)
                                                ? $stockAntes - $cantidadDetalle
                                                : max(0, $stockAntes - $cantidadDetalle);
                                            $inv->update([
                                                'stock_real'    => $stockDespues,
                                                'stock_reserva' => max(0, (float) $inv->stock_reserva - ($stockAntes - $stockDespues)),
                                            ]);
                                            $kardex->registrar([
                                                'empresa_id'        => $empresaId,
                                                'user_id'           => auth()->id(),
                                                'movible'           => $venta,
                                                'producto_id'       => $detalle->producto_id,
                                                'variante_id'       => null,
                                                'tipo'              => 'salida',
                                                'concepto'          => $concepto,
                                                'notas'             => "Promo: {$item['nombre']}",
                                                'cantidad'          => $cantidadDetalle,
                                                'unidad'            => $prodDetalle?->unidadMedida?->nombre ?? 'unidad',
                                                'factor_conversion' => 1,
                                                'cantidad_base'     => $cantidadDetalle,
                                                'stock_antes'       => $stockAntes,
                                                'stock_despues'     => $stockDespues,
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            });
        } catch (\Exception $e) {
            if ($e->getMessage() === '__SIN_SESION__') {
                $this->cerrarModalPago();
                $this->modalSinSesion = true;
                return;
            }
            Notification::make()
                ->title('Error al procesar la venta')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        if ($venta && in_array($venta->serie?->tipo, [TipoComprobante::Boleta, TipoComprobante::Factura])) {
            VentaCompletada::dispatch($venta);
        }

        $this->carrito = [];
        $this->cerrarModalPago();
        $this->autoSeleccionarComprobante();
        $this->autoSeleccionarClienteGeneral();
        $this->dispatch('cerrar-carrito-mobile');

        Notification::make()->title('Venta procesada correctamente')->success()->send();

        if ($venta) {
            $venta->loadMissing('serie');
            $serie    = $venta->serie;
            $empresa  = Filament::getTenant();

            $ventaNumero = ($serie?->serie ?? '---') . '-' . str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT);
            $shareUrl    = URL::temporarySignedRoute(
                'pdv.ticket.venta.compartir',
                now()->addHours(24),
                ['id' => $venta->id]
            );

            $imprimioDirecto = false;
            try {
                $imprimioDirecto = app(ImpresionDirectaService::class)
                    ->imprimirComprobante($venta, $empresa);

                app(ImpresionDirectaService::class)
                    ->imprimirComandas($venta, $empresa);
            } catch (\Throwable) {}

            $this->dispatch('abrir-modal-impresion',
                ventaId:      $venta->id,
                ventaNumero:  $ventaNumero,
                ventaTotal:   (float) $venta->total,
                autoImprimir: ! $imprimioDirecto,
                shareUrl:     $shareUrl,
            );

            $this->ultimaVentaId     = $venta->id;
            $this->ultimaVentaNumero = $ventaNumero;
            Cache::put($this->cacheKeyUltimaVenta(), [
                'id'     => $venta->id,
                'numero' => $ventaNumero,
            ], now()->addDays(30));
        }
    }

    #[On('modal-impresion-cerrada')]
    public function onModalImpresionCerrada(): void
    {
        // PDV no necesita redirigir al cerrar el modal
    }

    public function reimprimirDirecto(): void
    {
        if (! $this->ultimaVentaId) {
            return;
        }

        $empresa = Filament::getTenant();
        $config  = $empresa->cachedConfigImpresion();

        if (! $config['impresion_comprobante_directo']) {
            $this->dispatch('pdv-reprint-browser');
            return;
        }

        try {
            $venta = \App\Models\Venta::find($this->ultimaVentaId);

            if (! $venta) {
                $this->dispatch('pdv-reprint-browser');
                return;
            }

            $ok = app(ImpresionDirectaService::class)->imprimirComprobante($venta, $empresa);

            if ($ok) {
                Notification::make()
                    ->title('Comprobante enviado a la impresora')
                    ->success()
                    ->duration(3000)
                    ->send();
            } else {
                $this->dispatch('pdv-reprint-browser');
            }
        } catch (\Throwable) {
            Notification::make()
                ->title('Error al reimprimir')
                ->body('No se pudo enviar a la impresora. Intenta con el botón de imprimir del navegador.')
                ->danger()
                ->send();
            $this->dispatch('pdv-reprint-browser');
        }
    }

}
