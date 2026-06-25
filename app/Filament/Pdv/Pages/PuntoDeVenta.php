<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoPromocion;
use App\Enums\TipoComprobante;
use App\Enums\TipoDocumento;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ProductoAtributoValor;
use App\Models\Promocion;
use App\Models\Producto;
use App\Models\Serie;
use App\Models\Variante;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
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
            ->with(['variantes.inventario', 'inventario']);

        if ($this->busqueda !== '') {
            $query->where('nombre', 'like', "%{$this->busqueda}%");
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
            ->orderBy('nombre')
            ->get();
    }

    // ── Modal variantes ───────────────────────────────────────────────────────

    public function abrirModalProducto(int $productoId): void
    {
        $producto = Producto::with([
            'variantes.inventario',
            'variantes.valores',
            'atributos.atributo',
            'atributos.detallesPrecios.valor',
            'atributos.detallesExclusiones',
        ])->findOrFail($productoId);

        $this->productoControlStock  = (bool) $producto->control_de_stock;
        $this->productoVentaSinStock = (bool) $producto->venta_sin_stock;
        $this->productoEsCortesia    = (bool) $producto->es_cortesia;

        if ($producto->variantes->isEmpty()) {
            $precio = $producto->es_cortesia ? 0.0 : (float) $producto->precio_venta;
            $this->agregarProductoSimple($productoId, $producto->nombre, $precio);
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

        $this->variantesInfo = $producto->variantes
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
        $this->seleccionados[$productoAtributoId] = $productoAtributoValorId;
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
                if (isset($this->seleccionados[$paId])) {
                    continue;
                }

                foreach ($atributo['valores'] as $valor) {
                    $pavId = (int) $valor['id'];
                    $tieneStock = false;

                    foreach ($this->variantesInfo as $varDatos) {
                        if (! in_array($pavId, $varDatos['pav_ids'])) {
                            continue;
                        }
                        $compatible = true;
                        foreach ($this->seleccionados as $selPavId) {
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

        $precio = $this->productoEsCortesia ? 0.0 : (float) $variante->precio_final;
        $this->agregarVariante($variante->id, $nombre, $precio);
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

    public function agregarProductoSimple(int $productoId, string $nombre = '', float $precio = 0): void
    {
        if ($nombre === '') {
            $producto = Producto::find($productoId);
            if (! $producto) return;
            $nombre = $producto->nombre;
            $precio = (float) $producto->precio_venta;
        }

        $this->pushCarrito("producto_{$productoId}", 'producto', $productoId, $nombre, $precio);
    }

    private function agregarVariante(int $varianteId, string $nombre, float $precio): void
    {
        $this->pushCarrito("variante_{$varianteId}", 'variante', $varianteId, $nombre, $precio);
    }

    public function agregarPromocion(int $promocionId): void
    {
        $promocion = Promocion::find($promocionId);
        if (! $promocion || ! $promocion->estaVigente()) {
            Notification::make()->title('Promoción no disponible')->warning()->send();
            return;
        }

        $this->pushCarrito("promocion_{$promocionId}", 'promocion', $promocionId, $promocion->nombre, (float) $promocion->precio);
    }

    private function pushCarrito(string $key, string $tipo, int $id, string $nombre, float $precio): void
    {
        $carrito = $this->carrito;
        if (isset($carrito[$key])) {
            $carrito[$key]['cantidad']++;
        } else {
            $carrito[$key] = ['key' => $key, 'tipo' => $tipo, 'id' => $id, 'nombre' => $nombre, 'precio' => $precio, 'cantidad' => 1];
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
}
