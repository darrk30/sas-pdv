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
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Inventario;
use App\Models\MetodoPago;
use App\Models\ProductoAtributoValor;
use App\Models\Promocion;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\SesionCaja;
use App\Models\Transaccion;
use App\Models\Variante;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Services\KardexService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use UnitEnum;

class PuntoDeVenta extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'Punto de Venta';
    protected static string|UnitEnum|null $navigationGroup = 'Caja';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pdv.pages.punto-de-venta';

    public function getHeading(): string { return ''; }
    public function getMaxContentWidth(): ?string { return 'full'; }

    // ── Filtros ───────────────────────────────────────────────────────────────
    public string $busqueda = '';
    public ?int $categoriaId = null;

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

    // ── Modal nuevo cliente ───────────────────────────────────────────────────
    public bool $modalNuevoCliente = false;
    public string $ncNombre = '';
    public string $ncApellidos = '';
    public string $ncTipoDoc = 'dni';
    public string $ncNumeroDoc = '';

    // ── Modal variantes ───────────────────────────────────────────────────────
    public bool $modalAbierto = false;
    public ?int $productoModalId = null;
    public string $productoModalNombre = '';
    public float $precioBase = 0;
    public array $atributosModal = [];
    public array $seleccionados = [];
    public float $precioAdicionalTotal = 0;
    public bool $productoControlStock = false;
    public bool $productoVentaSinStock = false;
    public bool $productoEsCortesia = false;
    public array $variantesInfo = [];
    public array $exclusionesMap = [];
    public array $valoresDeshabilitados = [];

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

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->autoSeleccionarComprobante();
        $this->autoSeleccionarClienteGeneral();
    }

    // ── Series ────────────────────────────────────────────────────────────────

    public function getSeries(): Collection
    {
        return Serie::where('empresa_id', Filament::getTenant()->id)
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

    // ── Modal: nuevo cliente rápido ───────────────────────────────────────────

    public function abrirModalNuevoCliente(): void
    {
        $this->ncNombre    = '';
        $this->ncApellidos = '';
        $this->ncTipoDoc   = 'dni';
        $this->ncNumeroDoc = '';
        $this->modalNuevoCliente = true;
    }

    public function cerrarModalNuevoCliente(): void
    {
        $this->modalNuevoCliente = false;
    }

    public function crearCliente(): void
    {
        if (blank($this->ncNombre) || blank($this->ncNumeroDoc)) {
            Notification::make()->title('Nombre y número de documento son requeridos')->warning()->send();
            return;
        }

        $longitud = $this->ncTipoDoc === 'ruc' ? 11 : 8;
        if (strlen($this->ncNumeroDoc) !== $longitud) {
            Notification::make()->title("El {$this->ncTipoDoc} debe tener {$longitud} dígitos")->warning()->send();
            return;
        }

        $cliente = Cliente::create([
            'empresa_id'       => Filament::getTenant()->id,
            'user_id'          => auth()->id(),
            'nombre'           => $this->ncNombre,
            'apellidos'        => $this->ncApellidos,
            'tipo_documento'   => $this->ncTipoDoc,
            'numero_documento' => $this->ncNumeroDoc,
        ]);

        $this->cerrarModalNuevoCliente();
        $this->seleccionarCliente($cliente->id);
        Notification::make()->title('Cliente creado y seleccionado')->success()->send();
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

    // ── Filtros ───────────────────────────────────────────────────────────────

    public function seleccionarCategoria(?int $id): void
    {
        $this->categoriaId = $id;
    }

    public function limpiarBusqueda(): void
    {
        $this->busqueda = '';
    }

    #[On('pdv-barcode')]
    public function recibirBarcode(string $code): void
    {
        $this->busqueda = $code;
    }

    #[On('camera-not-available')]
    public function cameraNoDiponible(): void
    {
        Notification::make()
            ->title('Cámara no disponible')
            ->body('Activa los permisos de cámara en el navegador o usa un escáner USB conectado al equipo.')
            ->warning()
            ->send();
    }

    // ── Datos para la vista ───────────────────────────────────────────────────

    public function getCategorias(): Collection
    {
        return Categoria::where('empresa_id', Filament::getTenant()->id)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    public function getProductos(): Collection
    {
        $empresaId = Filament::getTenant()->id;

        $query = Producto::where('empresa_id', $empresaId)
            ->where('estado', 'activo')
            ->with([
                'variantesActivas' => fn($q) => $q->with('inventario'),
                'inventario',
            ]);

        if ($this->busqueda !== '') {
            $b = $this->busqueda;
            $query->where(function ($q) use ($b) {
                $q->where('nombre', 'like', "%{$b}%")
                  ->orWhere('codigo_interno', 'like', "%{$b}%")
                  ->orWhere('codigo_barras', 'like', "%{$b}%");
            });
        }

        if ($this->categoriaId !== null) {
            $query->where('categoria_id', $this->categoriaId);
        }

        return $query->orderBy('nombre')->get();
    }

    public function getPromociones(): Collection
    {
        if ($this->categoriaId !== null || $this->busqueda !== '') {
            return collect();
        }

        return Promocion::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', EstadoPromocion::Activo->value)
            ->withCount('detalles')
            ->with([
                'detalles.producto.inventario',
                'detalles.variante.producto',
                'detalles.variante.inventario',
            ])
            ->orderBy('nombre')
            ->get();
    }

    // ── Modal variantes ───────────────────────────────────────────────────────

    public function abrirModalProducto(int $productoId): void
    {
        $producto = Producto::with([
            'variantesActivas' => fn($q) => $q->with(['inventario', 'valores']),
            'atributos.atributo',
            'atributos.detallesPrecios.valor',
            'atributos.detallesExclusiones',
        ])->findOrFail($productoId);

        $this->productoControlStock  = (bool) $producto->control_de_stock;
        $this->productoVentaSinStock = (bool) $producto->venta_sin_stock;
        $this->productoEsCortesia    = (bool) $producto->es_cortesia;

        if ($producto->variantesActivas->isEmpty()) {
            $esCortesia = (bool) $producto->es_cortesia;
            $precio     = $esCortesia ? 0.0 : (float) $producto->precio_venta;
            $this->agregarProductoSimple($productoId, $producto->nombre, $precio, $esCortesia);
            return;
        }

        $this->productoModalId      = $productoId;
        $this->productoModalNombre  = $producto->nombre;
        $this->precioBase           = $producto->es_cortesia ? 0.0 : (float) $producto->precio_venta;
        $this->seleccionados        = [];
        $this->precioAdicionalTotal = 0;

        $this->atributosModal = $producto->atributos
            ->map(fn($pa) => [
                'id'     => $pa->id,
                'nombre' => $pa->atributo->nombre,
                'valores' => $pa->detallesPrecios
                    ->map(fn($pav) => [
                        'id'               => $pav->id,
                        'valor_id'         => $pav->valor_id,
                        'nombre'           => $pav->valor->nombre,
                        'precio_adicional' => (float) $pav->precio_adicional,
                    ])
                    ->values()
                    ->toArray(),
            ])
            ->values()
            ->toArray();

        $this->variantesInfo = $producto->variantesActivas
            ->map(fn($v) => [
                'pav_ids' => $v->valores->pluck('id')->toArray(),
                'stock'   => (float) ($v->inventario?->stock_real ?? 0),
            ])
            ->values()
            ->toArray();

        $exclusionesMap = [];
        foreach ($producto->atributos as $pa) {
            foreach ($pa->detallesExclusiones as $ex) {
                $exclusionesMap[$ex->valor_base_id][] = $ex->valor_exluido_id;
            }
        }
        $this->exclusionesMap = $exclusionesMap;

        $this->calcularDeshabilitados();
        $this->modalAbierto = true;
    }

    public function seleccionarValor(int $productoAtributoId, int $productoAtributoValorId): void
    {
        if (isset($this->seleccionados[$productoAtributoId])
            && (int) $this->seleccionados[$productoAtributoId] === $productoAtributoValorId) {
            unset($this->seleccionados[$productoAtributoId]);
        } else {
            $this->seleccionados[$productoAtributoId] = $productoAtributoValorId;
        }
        $this->recalcularPrecioAdicional();
        $this->calcularDeshabilitados();
    }

    private function calcularDeshabilitados(): void
    {
        $deshabilitados = [];

        foreach ($this->seleccionados as $paId => $pavId) {
            $valorId = null;
            foreach ($this->atributosModal as $atributo) {
                if ((int) $atributo['id'] === (int) $paId) {
                    foreach ($atributo['valores'] as $v) {
                        if ((int) $v['id'] === (int) $pavId) {
                            $valorId = $v['valor_id'];
                            break;
                        }
                    }
                    break;
                }
            }

            if ($valorId && isset($this->exclusionesMap[$valorId])) {
                foreach ($this->exclusionesMap[$valorId] as $exclValorId) {
                    foreach ($this->atributosModal as $atributo) {
                        foreach ($atributo['valores'] as $v) {
                            if ((int) $v['valor_id'] === (int) $exclValorId) {
                                $deshabilitados[] = (int) $v['id'];
                            }
                        }
                    }
                }
            }
        }

        if ($this->productoControlStock && ! $this->productoVentaSinStock) {
            foreach ($this->atributosModal as $atributo) {
                $paId = $atributo['id'];

                foreach ($atributo['valores'] as $valor) {
                    $pavId = (int) $valor['id'];

                    // No deshabilitar el valor que ya está seleccionado en este atributo
                    if (isset($this->seleccionados[$paId])
                        && (int) $this->seleccionados[$paId] === $pavId) {
                        continue;
                    }

                    $tieneStock = false;

                    foreach ($this->variantesInfo as $varDatos) {
                        if (! in_array($pavId, $varDatos['pav_ids'])) {
                            continue;
                        }
                        $compatible = true;
                        // Comparar contra selecciones de los OTROS atributos (no el propio)
                        foreach ($this->seleccionados as $selPaId => $selPavId) {
                            if ((int) $selPaId === (int) $paId) {
                                continue;
                            }
                            if (! in_array((int) $selPavId, $varDatos['pav_ids'])) {
                                $compatible = false;
                                break;
                            }
                        }
                        if ($compatible && $varDatos['stock'] > 0) {
                            $tieneStock = true;
                            break;
                        }
                    }

                    if (! $tieneStock) {
                        $deshabilitados[] = $pavId;
                    }
                }
            }
        }

        $this->valoresDeshabilitados = array_values(array_unique($deshabilitados));
    }

    private function recalcularPrecioAdicional(): void
    {
        if (empty($this->seleccionados)) {
            $this->precioAdicionalTotal = 0;
            return;
        }

        $this->precioAdicionalTotal = (float) ProductoAtributoValor::whereIn('id', array_values($this->seleccionados))
            ->sum('precio_adicional');
    }

    public function confirmarModal(): void
    {
        if (! $this->productoModalId || count($this->seleccionados) < count($this->atributosModal)) {
            return;
        }

        $selectedPavIds = array_values($this->seleccionados);

        $variante = Variante::where('producto_id', $this->productoModalId)
            ->where(function ($q) use ($selectedPavIds) {
                foreach ($selectedPavIds as $pavId) {
                    $q->whereHas('valores', fn($vq) => $vq->where('producto_atributo_valors_id', $pavId));
                }
            })
            ->first();

        if (! $variante) {
            Notification::make()
                ->title('Combinación no disponible')
                ->body('No existe una variante con la combinación seleccionada.')
                ->warning()
                ->send();
            return;
        }

        $sufijo = collect($this->atributosModal)
            ->map(fn($a) => collect($a['valores'])->firstWhere('id', $this->seleccionados[$a['id']] ?? null)['nombre'] ?? null)
            ->filter()
            ->implode(' - ');

        $nombre = $sufijo ? "{$this->productoModalNombre} ({$sufijo})" : $this->productoModalNombre;

        $precio = $this->productoEsCortesia ? 0.0 : ($this->precioBase + $this->precioAdicionalTotal);
        $this->agregarVariante($variante->id, $nombre, $precio, $this->productoEsCortesia);
        $this->cerrarModal();
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto          = false;
        $this->productoModalId       = null;
        $this->productoModalNombre   = '';
        $this->atributosModal        = [];
        $this->seleccionados         = [];
        $this->precioAdicionalTotal  = 0;
        $this->productoControlStock  = false;
        $this->productoVentaSinStock = false;
        $this->productoEsCortesia    = false;
        $this->variantesInfo         = [];
        $this->exclusionesMap        = [];
        $this->valoresDeshabilitados = [];
    }

    // ── Carrito: agregar ──────────────────────────────────────────────────────

    public function agregarProductoSimple(int $productoId, string $nombre = '', float $precio = 0, bool $esCortesia = false): void
    {
        if ($nombre === '') {
            $producto = Producto::find($productoId);
            if (! $producto) return;
            $nombre     = $producto->nombre;
            $esCortesia = (bool) $producto->es_cortesia;
            $precio     = $esCortesia ? 0.0 : (float) $producto->precio_venta;
        }

        $this->pushCarrito("producto_{$productoId}", 'producto', $productoId, $nombre, $precio, $esCortesia);
    }

    private function agregarVariante(int $varianteId, string $nombre, float $precio, bool $esCortesia = false): void
    {
        $this->pushCarrito("variante_{$varianteId}", 'variante', $varianteId, $nombre, $precio, $esCortesia);
    }

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

    private function pushCarrito(string $key, string $tipo, int $id, string $nombre, float $precio, bool $esCortesia = false): void
    {
        $carrito = $this->carrito;
        if (isset($carrito[$key])) {
            $carrito[$key]['cantidad']++;
        } else {
            $carrito[$key] = [
                'key'      => $key,
                'tipo'     => $tipo,
                'id'       => $id,
                'nombre'   => $nombre,
                'precio'   => $precio,
                'cortesia' => $esCortesia,
                'cantidad' => 1,
            ];
        }
        $this->carrito = $carrito;
    }

    // ── Carrito: gestión ──────────────────────────────────────────────────────

    public function aumentarCantidad(string $key): void
    {
        $carrito = $this->carrito;
        if (isset($carrito[$key])) {
            $carrito[$key]['cantidad']++;
            $this->carrito = $carrito;
        }
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
            ])
            ->values()
            ->toArray();

        $this->metodoPagoId      = null;
        $this->montoPagoInput    = '';
        $this->pagoReferencia    = '';
        $this->pagosAgregados    = [];
        $this->descuentoInput    = '0';
        $this->despachoRequerido = false;
        $this->modalPago         = true;
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
        if (! $this->metodoPagoId) {
            $efectivo = collect($this->metodosPagoDisponibles)
                ->first(fn($m) => mb_strtolower($m['nombre']) === 'efectivo');

            if ($efectivo) {
                $this->seleccionarMetodoPago($efectivo['id']);
                return;
            }
        }

        $saldo = $this->getSaldoRestante();
        $this->montoPagoInput = number_format(max(0, $saldo), 2, '.', '');
    }

    public function ajustarMonto(float $delta): void
    {
        $actual = (float) str_replace(',', '.', $this->montoPagoInput ?: '0');
        $this->montoPagoInput = number_format(max(0, $actual + $delta), 2, '.', '');
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
            'metodo_pago_id' => $this->metodoPagoId,
            'nombre'         => $metodo['nombre'] ?? '',
            'imagen'         => $metodo['imagen'] ?? null,
            'monto'          => $monto,
            'referencia'     => $this->pagoReferencia,
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

    public function getOpGravadas(): float
    {
        return round($this->getTotalConDescuento() / 1.18, 2);
    }

    public function getIgv(): float
    {
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
        $opGravadas        = $this->getOpGravadas();
        $igv               = $this->getIgv();
        $montoPagado       = min($this->getTotalPagado(), $totalConDescuento);
        $pagosAgregados    = $this->pagosAgregados;
        $carrito           = $this->carrito;
        $clienteId         = $this->clienteId;
        $clienteNombre     = $this->clienteNombre;
        $clienteTipoDoc    = $this->clienteTipoDoc;
        $serieId           = $this->serieId;
        $despachoRequerido = $this->despachoRequerido;

        try {
            DB::transaction(function () use (
                $empresaId, $descuento, $totalConDescuento, $opGravadas, $igv,
                $montoPagado, $pagosAgregados, $carrito,
                $clienteId, $clienteNombre, $clienteTipoDoc, $serieId, $despachoRequerido
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
                    'tipo_pago'        => TipoPago::Contado,
                    'op_gravadas'      => $opGravadas,
                    'op_exoneradas'    => 0,
                    'op_inafectas'     => 0,
                    'descuento_total'  => $descuento,
                    'igv'              => $igv,
                    'total'            => $totalConDescuento,
                    'costo_total'      => 0,
                    'monto_pagado'     => $montoPagado,
                    'saldo_pendiente'  => 0,
                    'estado'           => EstadoVenta::Completada,
                    'estado_despacho'  => $despachoRequerido ? EstadoVenta::PendienteEnvio : null,
                ]);

                $costoTotalVenta = 0.0;

                foreach ($carrito as $item) {
                    $variante = $item['tipo'] === 'variante'
                        ? Variante::with('producto')->find($item['id'])
                        : null;

                    $costoUnitario = match ($item['tipo']) {
                        'producto' => (float) (Producto::find($item['id'])?->precio_costo ?? 0),
                        'variante' => (float) ($variante?->precio_costo ?? $variante?->producto?->precio_costo ?? 0),
                        default    => 0.0,
                    };

                    $calc = VentaDetalle::calcular(
                        cantidad: (float) $item['cantidad'],
                        precioUnitario: (float) $item['precio'],
                        costoUnitario: $costoUnitario,
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

                foreach ($pagosAgregados as $pago) {
                    VentaPago::create([
                        'venta_id'       => $venta->id,
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

                $kardex  = app(KardexService::class);
                $concepto = $serie->serie . '-' . $correlativo;

                foreach ($carrito as $item) {
                    $cantidad = (float) $item['cantidad'];

                    if ($item['tipo'] === 'producto') {
                        $producto = Producto::find($item['id']);
                        if ($producto?->control_de_stock) {
                            $inv = Inventario::where('empresa_id', $empresaId)
                                ->where('producto_id', $item['id'])
                                ->whereNull('variante_id')
                                ->lockForUpdate()
                                ->first();
                            if ($inv) {
                                $stockAntes   = (float) $inv->stock_real;
                                $stockDespues = max(0, $stockAntes - $cantidad);
                                $inv->update(['stock_real' => $stockDespues]);
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
                                    'unidad'            => 'unidad',
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
                        $variante = Variante::find($item['id']);
                        if ($variante) {
                            $prodVariante = Producto::find($variante->producto_id);
                            if ($prodVariante?->control_de_stock) {
                                $inv = Inventario::where('empresa_id', $empresaId)
                                    ->where('producto_id', $variante->producto_id)
                                    ->where('variante_id', $item['id'])
                                    ->lockForUpdate()
                                    ->first();
                                if ($inv) {
                                    $stockAntes   = (float) $inv->stock_real;
                                    $stockDespues = max(0, $stockAntes - $cantidad);
                                    $inv->update(['stock_real' => $stockDespues]);
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
                                        'unidad'            => 'unidad',
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
                        // Incrementar usos y reducir stock de cada ítem del combo
                        Promocion::where('id', $item['id'])->increment('usos_actuales', (int) $cantidad);

                        $promo = Promocion::with([
                            'detalles.producto',
                            'detalles.variante.producto',
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
                                            $stockAntes   = (float) $inv->stock_real;
                                            $stockDespues = max(0, $stockAntes - $cantidadDetalle);
                                            $inv->update(['stock_real' => $stockDespues]);
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
                                                'unidad'            => 'unidad',
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
                                            $stockAntes   = (float) $inv->stock_real;
                                            $stockDespues = max(0, $stockAntes - $cantidadDetalle);
                                            $inv->update(['stock_real' => $stockDespues]);
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
                                                'unidad'            => 'unidad',
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

        $this->carrito = [];
        $this->cerrarModalPago();
        $this->autoSeleccionarComprobante();
        $this->autoSeleccionarClienteGeneral();

        Notification::make()->title('Venta procesada correctamente')->success()->send();
    }
}
