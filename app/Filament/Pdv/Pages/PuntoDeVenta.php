<?php

namespace App\Filament\Pdv\Pages;

use App\Enums\EstadoPromocion;
use App\Models\Categoria;
use App\Models\ProductoAtributoValor;
use App\Models\Promocion;
use App\Models\Producto;
use App\Models\Variante;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class PuntoDeVenta extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationLabel = 'Punto de Venta';
    protected static ?string $navigationGroup = 'Caja';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pdv.pages.punto-de-venta';
    protected ?string $heading = null;

    // ── Filtros ──────────────────────────────────────────────────────────────
    public string $busqueda = '';
    public ?int $categoriaId = null;

    // ── Carrito ──────────────────────────────────────────────────────────────
    public array $carrito = [];

    // ── Modal variantes ───────────────────────────────────────────────────────
    public bool $modalAbierto = false;
    public ?int $productoModalId = null;
    public string $productoModalNombre = '';
    public float $precioBase = 0;
    public array $atributosModal = [];        // [{id, nombre, valores:[{id, nombre, precio_adicional}]}]
    public array $seleccionados = [];         // productoAtributoId => productoAtributoValorId
    public float $precioAdicionalTotal = 0;

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
            'variantes',
            'atributos.atributo',
            'atributos.detallesPrecios.valor',
        ])->findOrFail($productoId);

        // Producto sin variantes → agregar directo
        if ($producto->variantes->isEmpty()) {
            $this->agregarProductoSimple($productoId, $producto->nombre, (float) $producto->precio_venta);
            return;
        }

        $this->productoModalId      = $productoId;
        $this->productoModalNombre  = $producto->nombre;
        $this->precioBase           = (float) $producto->precio_venta;
        $this->seleccionados        = [];
        $this->precioAdicionalTotal = 0;

        $this->atributosModal = $producto->atributos
            ->map(fn($pa) => [
                'id'     => $pa->id,
                'nombre' => $pa->atributo->nombre,
                'valores' => $pa->detallesPrecios
                    ->map(fn($pav) => [
                        'id'               => $pav->id,
                        'nombre'           => $pav->valor->nombre,
                        'precio_adicional' => (float) $pav->precio_adicional,
                    ])
                    ->values()
                    ->toArray(),
            ])
            ->values()
            ->toArray();

        $this->modalAbierto = true;
    }

    public function seleccionarValor(int $productoAtributoId, int $productoAtributoValorId): void
    {
        $this->seleccionados[$productoAtributoId] = $productoAtributoValorId;
        $this->recalcularPrecioAdicional();
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

        // Nombre: "Producto (Rojo - S)"
        $sufijo = collect($this->atributosModal)
            ->map(fn($a) => collect($a['valores'])->firstWhere('id', $this->seleccionados[$a['id']] ?? null)['nombre'] ?? null)
            ->filter()
            ->implode(' - ');

        $nombre = $sufijo ? "{$this->productoModalNombre} ({$sufijo})" : $this->productoModalNombre;

        $this->agregarVariante($variante->id, $nombre, (float) $variante->precio_final);
        $this->cerrarModal();
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto         = false;
        $this->productoModalId      = null;
        $this->productoModalNombre  = '';
        $this->atributosModal       = [];
        $this->seleccionados        = [];
        $this->precioAdicionalTotal = 0;
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

        $key = "producto_{$productoId}";
        $this->pushCarrito($key, 'producto', $productoId, $nombre, $precio);
    }

    private function agregarVariante(int $varianteId, string $nombre, float $precio): void
    {
        $key = "variante_{$varianteId}";
        $this->pushCarrito($key, 'variante', $varianteId, $nombre, $precio);
    }

    public function agregarPromocion(int $promocionId): void
    {
        $promocion = Promocion::find($promocionId);
        if (! $promocion || ! $promocion->estaVigente()) {
            Notification::make()
                ->title('Promoción no disponible')
                ->warning()
                ->send();
            return;
        }

        $key = "promocion_{$promocionId}";
        $this->pushCarrito($key, 'promocion', $promocionId, $promocion->nombre, (float) $promocion->precio);
    }

    private function pushCarrito(string $key, string $tipo, int $id, string $nombre, float $precio): void
    {
        if (isset($this->carrito[$key])) {
            $this->carrito[$key]['cantidad']++;
        } else {
            $this->carrito[$key] = [
                'key'      => $key,
                'tipo'     => $tipo,
                'id'       => $id,
                'nombre'   => $nombre,
                'precio'   => $precio,
                'cantidad' => 1,
            ];
        }
    }

    // ── Carrito: gestión ──────────────────────────────────────────────────────

    public function aumentarCantidad(string $key): void
    {
        if (isset($this->carrito[$key])) {
            $this->carrito[$key]['cantidad']++;
        }
    }

    public function disminuirCantidad(string $key): void
    {
        if (! isset($this->carrito[$key])) return;

        if ($this->carrito[$key]['cantidad'] > 1) {
            $this->carrito[$key]['cantidad']--;
        } else {
            $carrito = $this->carrito;
            unset($carrito[$key]);
            $this->carrito = $carrito;
        }
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
