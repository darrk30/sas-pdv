<?php

namespace App\Livewire\Pdv;

use App\Enums\EstadoPromocion;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Promocion;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class ProductCatalog extends Component
{
    // ── Props reactivas desde el padre ───────────────────────────────────────
    #[Reactive]
    public array $carritoResumen = []; // ['producto_123' => 2, 'variante_456' => 1]

    /** Solo ítems aún no persistidos en BD (para descontar de stock_reserva sin doble conteo) */
    #[Reactive]
    public array $pendienteResumen = [];

    #[Reactive]
    public ?bool $showPromociones = false; // true in PDV, false in restaurant pages

    // ── Filtros ───────────────────────────────────────────────────────────────
    public string $busqueda    = '';
    public ?int   $categoriaId = null;
    public int    $perPage     = 48;

    // ── Plan feature: lista de precios ───────────────────────────────────────

    #[Computed]
    public function featureListaPrecios(): bool
    {
        $empresa = Filament::getTenant();
        return ($empresa?->tienePlanListaPrecios() ?? true)
            && (auth()->user()?->can('listas_precios.ver') ?? false);
    }

    // ── Filtros ───────────────────────────────────────────────────────────────

    public function seleccionarCategoria(?int $id): void
    {
        $this->categoriaId = $id;
        $this->perPage     = 48;
    }

    public function updatedBusqueda(): void
    {
        $this->perPage = 48;
    }

    public function cargarMas(): void
    {
        $this->perPage += 48;
    }

    public function limpiarBusqueda(): void
    {
        $this->busqueda = '';
    }

    #[On('pdv-barcode')]
    public function recibirBarcode(string $code): void
    {
        $empresaId = Filament::getTenant()->id;

        $producto = Producto::where('empresa_id', $empresaId)
            ->where('estado', 'activo')
            ->where(function ($q) use ($code) {
                $q->where('codigo_barras', $code)
                  ->orWhere('codigo_interno', $code);
            })
            ->with(['inventario', 'variantesActivas', 'unidadMedida.dimension'])
            ->first();

        if (! $producto) {
            Notification::make()
                ->title('Código no encontrado')
                ->body("No hay producto activo con código: {$code}")
                ->warning()
                ->send();
            return;
        }

        if ($producto->variantesActivas->isNotEmpty()) {
            $this->busqueda = $code;
            return;
        }

        $precioNormal = (float) $producto->precio_venta;
        $precio       = ($producto->porcentaje_descuento > 0 && $producto->precio_con_descuento)
            ? (float) $producto->precio_con_descuento
            : $precioNormal;
        $esDecimal = $producto->unidadMedida?->esContinua() ?? false;
        $stockMax  = $producto->control_de_stock ? (float) ($producto->inventario?->stock_reserva ?? 0) : null;

        $this->dispatch('product-selected', [
            'tipo'            => 'producto',
            'id'              => $producto->id,
            'nombre'          => $producto->nombre,
            'precio'          => $precio,
            'precio_normal'   => $precioNormal,
            'es_cortesia'     => false,
            'cantidad'        => 1,
            'producto_id'     => $producto->id,
            'puede_cortesia'  => (bool) $producto->es_cortesia,
            'es_decimal'      => $esDecimal,
            'stock_max'       => $stockMax,
            'venta_sin_stock' => (bool) $producto->venta_sin_stock,
        ]);
        $this->skipRender();
    }

    #[On('camera-not-available')]
    public function cameraNoDiponible(): void
    {
        Notification::make()
            ->title('Cámara no disponible')
            ->body('Activa los permisos de cámara en el navegador o usa un escáner USB.')
            ->warning()
            ->send();
    }

    // ── Promociones (solo cuando $showPromociones = true) ────────────────────

    public function getHayPromociones(): bool
    {
        if (! ($this->showPromociones ?? false)) return false;

        return Promocion::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', EstadoPromocion::Activo->value)
            ->exists();
    }

    public function getPromociones(): Collection
    {
        if (! ($this->showPromociones ?? false) || $this->categoriaId !== -1) {
            return collect();
        }

        $query = Promocion::where('empresa_id', Filament::getTenant()->id)
            ->where('estado', EstadoPromocion::Activo->value)
            ->withCount('detalles')
            ->with([
                'detalles.producto.inventario',
                'detalles.variante.producto',
                'detalles.variante.inventario',
                'detalles.variante.valores.valor',
            ]);

        if ($this->busqueda !== '') {
            $query->where('nombre', 'like', "%{$this->busqueda}%");
        }

        return $query->orderBy('nombre')->get();
    }

    public function seleccionarPromocion(int $promocionId): void
    {
        $promo = Promocion::with([
            'detalles.producto',
            'detalles.variante.producto',
            'detalles.variante.valores.valor',
        ])->find($promocionId);
        if (! $promo) return;

        $detallesResumen = $promo->detalles->map(function ($d) {
            $nombre = $d->variante?->producto?->nombre ?? $d->producto?->nombre ?? '—';
            if ($d->variante_id && $d->variante) {
                $vals = $d->variante->valores->map(fn ($pav) => $pav->valor?->nombre)->filter()->join(' / ');
                if ($vals) $nombre .= " ({$vals})";
            }
            return [
                'nombre'      => $nombre,
                'cantidad'    => (float) ($d->cantidad ?? 1),
                'producto_id' => $d->producto_id,
                'variante_id' => $d->variante_id,
            ];
        })->values()->all();

        $stockPromo = $promo->stockPredictivo();

        $this->dispatch('product-selected', [
            'tipo'             => 'promocion',
            'id'               => $promo->id,
            'nombre'           => $promo->nombre,
            'precio'           => (float) $promo->precio,
            'precio_normal'    => (float) $promo->precio,
            'es_cortesia'      => false,
            'cantidad'         => 1,
            'producto_id'      => $promo->id,
            'puede_cortesia'   => false,
            'es_decimal'       => false,
            'detalles_resumen' => $detallesResumen,
            'stock_max'        => $stockPromo,
            'venta_sin_stock'  => false,
        ]);
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
        if ($this->categoriaId === -1) {
            return collect();
        }

        $empresaId = Filament::getTenant()->id;

        $query = Producto::where('empresa_id', $empresaId)
            ->where('estado', 'activo')
            ->where('visible_en_carta', true)
            ->where('vendible', true)
            ->with([
                'preciosLista.lista',
                'variantesActivas' => fn ($q) => $q->with(['inventario', 'valores']),
                'inventario',
                'unidadMedida',
                'atributos.atributo',
                'atributos.detallesPrecios.valor',
                'atributos.detallesExclusiones',
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

        return $query
            ->orderByRaw("
                CASE
                    WHEN productos.control_de_stock = 0 THEN 0
                    WHEN productos.venta_sin_stock  = 1 THEN 0
                    WHEN EXISTS (
                        SELECT 1 FROM variantes v
                        WHERE v.producto_id = productos.id AND v.estado = ?
                    ) THEN
                        CASE WHEN (
                            SELECT COALESCE(SUM(i.stock_reserva), 0)
                            FROM inventarios i
                            INNER JOIN variantes v ON i.variante_id = v.id
                            WHERE v.producto_id = productos.id
                              AND v.estado      = ?
                              AND i.empresa_id  = productos.empresa_id
                        ) > 0 THEN 0 ELSE 1 END
                    ELSE
                        CASE WHEN (
                            SELECT COALESCE(SUM(i.stock_reserva), 0)
                            FROM inventarios i
                            WHERE i.producto_id = productos.id
                              AND i.variante_id IS NULL
                              AND i.empresa_id  = productos.empresa_id
                        ) > 0 THEN 0 ELSE 1 END
                END ASC
            ", ['activo', 'activo'])
            ->orderBy('orden')
            ->orderBy('nombre')
            ->take($this->perPage)
            ->get();
    }

    public function render()
    {
        return view('livewire.pdv.product-catalog', [
            'categorias'    => $this->getCategorias(),
            'productos'     => $this->getProductos(),
            'promociones'   => $this->getPromociones(),
            'hayPromociones'=> $this->getHayPromociones(),
        ]);
    }
}
