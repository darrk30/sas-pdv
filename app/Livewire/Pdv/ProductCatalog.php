<?php

namespace App\Livewire\Pdv;

use App\Enums\EstadoPromocion;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Promocion;
use App\Services\Pdv\BarcodeService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $svc       = app(BarcodeService::class);
        $result    = $svc->lookup($code, $empresaId);

        if (! $result) {
            Notification::make()
                ->title('Código no encontrado')
                ->body("No hay producto activo con código: {$code}")
                ->warning()
                ->send();
            return;
        }

        // Variante con código propio → agregar directamente al carrito
        if ($result['type'] === 'variante') {
            $variante = $result['data'];
            $producto = $variante->producto;

            $precioNormal = (float) $variante->precio_final;
            $precio       = $precioNormal;
            $esDecimal    = $producto->unidadMedida?->esContinua() ?? false;
            $stockMax     = $producto->control_de_stock
                ? $variante->stockDisponibleReserva($producto->empresa_id)
                : (! $producto->venta_sin_stock ? $this->_stockMaxReceta(null, $variante->id, $producto->empresa_id) : null);

            $this->dispatch('product-selected', [
                'tipo'            => 'variante',
                'id'              => $variante->id,
                'nombre'          => $producto->nombre . ' – ' . $variante->valores->map(fn ($pav) => $pav->valor?->nombre ?? '')->filter()->implode(' / '),
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
            return;
        }

        // Producto simple (sin variantes) → agregar directamente al carrito
        $producto = $result['data'];

        if ($producto->variantesActivas->isEmpty()) {
            $precioNormal = (float) $producto->precio_venta;
            $precio       = ($producto->porcentaje_descuento > 0 && $producto->precio_con_descuento)
                ? (float) $producto->precio_con_descuento
                : $precioNormal;
            $esDecimal = $producto->unidadMedida?->esContinua() ?? false;
            $stockMax  = $producto->control_de_stock
                ? (float) ($producto->inventario?->stock_reserva ?? 0)
                : (! $producto->venta_sin_stock ? $this->_stockMaxReceta($producto->id, null, $producto->empresa_id) : null);

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
            return;
        }

        // Producto con variantes pero sin código en variante → abrir modal de variantes
        $variantData = $svc->buildVariantData($producto);
        $this->dispatch('pdv-abrir-variantes', data: $variantData, precioLista: null);
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

        $empresaId = Filament::getTenant()->id;

        return Cache::remember("pdv_hay_promociones_{$empresaId}", 300, fn () =>
            Promocion::where('empresa_id', $empresaId)
                ->where('estado', EstadoPromocion::Activo->value)
                ->exists()
        );
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
            ->where('estado', true)
            ->where('activo_en_pdv', true)
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
            ->where('vendible', true)
            ->where(fn($q) => $q
                ->whereNull('categoria_id')
                ->orWhereHas('categoria', fn($cq) => $cq->where('estado', true)->where('activo_en_pdv', true))
            )
            ->with([
                'preciosLista.lista',
                'variantesActivas' => fn ($q) => $q->with(['inventario', 'valores.valor']),
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

        // Stock cacheado 30s por empresa — se guarda como array para compatibilidad con Redis
        $stockMap = Cache::remember("catalog_stock_{$empresaId}", 30, fn () =>
            DB::table('inventarios as i')
                ->leftJoin('variantes as v', fn ($j) =>
                    $j->on('v.id', '=', 'i.variante_id')->where('v.estado', 'activo')
                )
                ->leftJoin('productos as p', 'p.id', '=', DB::raw('COALESCE(v.producto_id, i.producto_id)'))
                ->where('i.empresa_id', $empresaId)
                ->selectRaw('
                    COALESCE(v.producto_id, i.producto_id) AS p_id,
                    CASE
                        WHEN MAX(CASE WHEN p.comparte_stock = 1 THEN 1 ELSE 0 END) = 1
                        THEN SUM(CASE WHEN i.variante_id IS NULL THEN i.stock_reserva ELSE 0 END)
                        WHEN MAX(CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END) = 1
                        THEN SUM(CASE WHEN v.id IS NOT NULL THEN i.stock_reserva ELSE 0 END)
                        ELSE SUM(CASE WHEN i.variante_id IS NULL THEN i.stock_reserva ELSE 0 END)
                    END AS stk
                ')
                ->groupByRaw('COALESCE(v.producto_id, i.producto_id)')
                ->pluck('stk', 'p_id')
                ->toArray()
        );

        $productos = $query
            ->select('productos.*')
            ->orderBy('productos.orden')
            ->orderBy('productos.nombre')
            ->take($this->perPage)
            ->get();

        // Ordenar en PHP: sin stock al fondo (igual que el ORDER BY anterior)
        return $productos->sortBy(function ($p) use ($stockMap) {
            if (! $p->control_de_stock || $p->venta_sin_stock) return 0;
            return (($stockMap[$p->id] ?? 0) > 0) ? 0 : 1;
        })->values();
    }

    // Calcula el stock máximo vendible basado en insumos de receta para productos sin control_de_stock.
    // Retorna null si no existe receta (sin limitación), o el mínimo de unidades fabricables.
    private function _stockMaxReceta(?int $productoId, ?int $varianteId, int $empresaId): ?float
    {
        $receta = \App\Models\Receta::where('empresa_id', $empresaId)
            ->when($varianteId,
                fn ($q) => $q->where('variante_id', $varianteId),
                fn ($q) => $q->where('producto_id', $productoId)->whereNull('variante_id')
            )
            ->with([
                'detalles.insumoProducto.unidadMedida',
                'detalles.insumoVariante.producto',
                'detalles.unidadMedida',
            ])
            ->first();

        if (! $receta || $receta->detalles->isEmpty()) return null;

        $stockMin = PHP_INT_MAX;

        foreach ($receta->detalles as $det) {
            $cantPorUnidad = (float) $det->cantidad;
            if ($cantPorUnidad <= 0) continue;

            // Conversión de unidades para insumo de producto simple
            if (! $det->insumo_variante_id && $det->insumo_producto_id && $det->unidadMedida) {
                $prodUnidad = $det->insumoProducto?->unidadMedida;
                if ($prodUnidad && $prodUnidad->id !== $det->unidad_medida_id) {
                    $fr = (float) $det->unidadMedida->factor_base;
                    $fp = (float) $prodUnidad->factor_base;
                    if ($fp > 0) $cantPorUnidad = $cantPorUnidad * $fr / $fp;
                }
            }

            if ($det->insumo_variante_id) {
                $var  = $det->insumoVariante;
                $prod = $var?->producto;
                if (! $prod?->control_de_stock) continue; // insumo sin tracking → no limita
                if ($prod->venta_sin_stock) continue;     // insumo permite negativo → no limita
                $inv = $prod->comparte_stock
                    ? \App\Models\Inventario::where('empresa_id', $empresaId)
                        ->where('producto_id', $var->producto_id)->whereNull('variante_id')->first()
                    : \App\Models\Inventario::where('empresa_id', $empresaId)
                        ->where('variante_id', $det->insumo_variante_id)->first();
                if (! $inv) { return 0.0; }
                $factor = $var->factor();
                $cantBase = $cantPorUnidad * $factor;
                $stockMin = min($stockMin, $cantBase > 0 ? (float) $inv->stock_reserva / $cantBase : 0);
            } elseif ($det->insumo_producto_id) {
                $prod = $det->insumoProducto;
                if (! $prod?->control_de_stock) continue; // insumo sin tracking → no limita
                if ($prod->venta_sin_stock) continue;     // insumo permite negativo → no limita
                $inv = \App\Models\Inventario::where('empresa_id', $empresaId)
                    ->where('producto_id', $det->insumo_producto_id)->whereNull('variante_id')->first();
                if (! $inv) { return 0.0; }
                $stockMin = min($stockMin, $cantPorUnidad > 0 ? (float) $inv->stock_reserva / $cantPorUnidad : 0);
            }
        }

        return $stockMin === PHP_INT_MAX ? null : floor($stockMin * 1000) / 1000;
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
